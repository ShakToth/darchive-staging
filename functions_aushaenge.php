<?php
// DIREKTER AUFRUF VERBOTEN
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Direct access to this file is not allowed.</p></body></html>');
}

// --- AUSHÄNGE KONFIGURATION ---
define('AUSHAENGE_DIR', __DIR__ . '/aushaenge/');
define('AUSHAENGE_DB_PATH', AUSHAENGE_DIR . 'aushaenge.db');
define('AUSHAENGE_UPLOAD_DIR', AUSHAENGE_DIR . 'bilder/');

// --- INITIALISIERUNG ---
function initAushaengeDB() {
    // Ordnerstruktur prüfen & anlegen
    if (!is_dir(AUSHAENGE_DIR)) mkdir(AUSHAENGE_DIR, 0755, true);
    if (!is_dir(AUSHAENGE_UPLOAD_DIR)) mkdir(AUSHAENGE_UPLOAD_DIR, 0755, true);
    
    try {
        $pdo = new PDO('sqlite:' . AUSHAENGE_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Tabelle erstellen, falls sie nicht existiert
        $pdo->exec("CREATE TABLE IF NOT EXISTS zettel (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titel TEXT NOT NULL,
            inhalt TEXT NOT NULL,
            signatur TEXT NOT NULL,
            bild_pfad TEXT NULL,
            datum DATETIME DEFAULT CURRENT_TIMESTAMP,
            author_id INTEGER NULL,
            format_type TEXT DEFAULT 'markdown'
        )");
        
        // Migration: Spalten hinzufügen falls sie fehlen (für bestehende DBs)
        try {
            $pdo->exec("ALTER TABLE zettel ADD COLUMN author_id INTEGER NULL");
        } catch (PDOException $e) {
            // Spalte existiert bereits, ignorieren
        }
        
        try {
            $pdo->exec("ALTER TABLE zettel ADD COLUMN format_type TEXT DEFAULT 'markdown'");
        } catch (PDOException $e) {
            // Spalte existiert bereits, ignorieren
        }

        // Migration: Wichtig-Siegel & Angeheftet
        try {
            $pdo->exec("ALTER TABLE zettel ADD COLUMN ist_wichtig INTEGER DEFAULT 0");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("ALTER TABLE zettel ADD COLUMN angeheftet INTEGER DEFAULT 0");
        } catch (PDOException $e) {}

        // Notizen-Tabelle (Kommentar-System)
        $pdo->exec("CREATE TABLE IF NOT EXISTS zettel_notizen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            zettel_id INTEGER NOT NULL,
            text TEXT NOT NULL,
            autor_name TEXT NOT NULL,
            autor_id INTEGER DEFAULT NULL,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (zettel_id) REFERENCES zettel(id) ON DELETE CASCADE
        )");

        return $pdo;
    } catch (PDOException $e) {
        error_log("Aushaenge DB Error: " . $e->getMessage());
        return null;
    }
}

// --- AUSHANG ERSTELLEN (inkl. sicherem Upload) ---
function createAushang($titel, $inhalt, $signatur, $fileArray = null, $formatType = 'markdown') {
    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Das Schwarze Brett klemmt (Datenbankfehler).'];
    
    // Author ID speichern (falls eingeloggt)
    $authorId = isLoggedIn() ? $_SESSION['user_id'] : null;
    
    $bildPfad = null;
    
    // Bild-Upload verarbeiten, falls vorhanden
    if ($fileArray && isset($fileArray['error']) && $fileArray['error'] === UPLOAD_ERR_OK) {
        // 1. Check: Dateigröße (Max. 5 MB)
        $maxSize = 5 * 1024 * 1024;
        if ($fileArray['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Die Skizze ist zu schwer! Maximal 5 MB erlaubt.'];
        }
        
        // 2. Check: Echter MIME-Type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            return ['success' => false, 'message' => 'Nur Bilder (JPG, PNG, WEBP) dürfen angepinnt werden.'];
        }
        
        // 3. Sicherer Dateiname (verhindert Path-Traversal und Code-Execution)
        $ext = pathinfo($fileArray['name'], PATHINFO_EXTENSION);
        // Wir erzwingen jpg, png oder webp zur Sicherheit nochmal
        $ext = strtolower($ext);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $ext = 'jpg'; // Fallback
        }
        
        $newFileName = uniqid('zettel_') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = AUSHAENGE_UPLOAD_DIR . $newFileName;
        
        if (move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
            // Relativer Pfad für das Frontend
            $bildPfad = 'aushaenge/bilder/' . $newFileName;
        } else {
            return ['success' => false, 'message' => 'Fehler beim Aufhängen des Bildes.'];
        }
    }
    
    // In die Datenbank eintragen
    $stmt = $db->prepare("INSERT INTO zettel (titel, inhalt, signatur, bild_pfad, author_id, format_type) VALUES (:titel, :inhalt, :signatur, :bild_pfad, :author_id, :format_type)");
    $result = $stmt->execute([
        ':titel' => trim($titel),
        ':inhalt' => trim($inhalt),
        ':signatur' => trim($signatur),
        ':bild_pfad' => $bildPfad,
        ':author_id' => $authorId,
        ':format_type' => $formatType
    ]);
    
    if ($result) {
        return ['success' => true, 'message' => 'Dein Aushang hängt sicher am Brett.'];
    } else {
        return ['success' => false, 'message' => 'Fehler beim Anpinnen in die Datenbank.'];
    }
}

// --- AUSHÄNGE LADEN ---
function getAushaenge($limit = null, $search = '') {
    $db = initAushaengeDB();
    if (!$db) return [];
    
    $query = "SELECT * FROM zettel";
    
    // Suche in der Kiste
    if (!empty($search)) {
        $query .= " WHERE titel LIKE :search OR inhalt LIKE :search OR signatur LIKE :search";
    }
    
    $query .= " ORDER BY angeheftet DESC, datum DESC";
    
    if ($limit !== null) {
        $query .= " LIMIT :limit";
    }
    
    $stmt = $db->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    if ($limit !== null) {
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- PRÜFEN OB BENUTZER EINEN AUSHANG BEARBEITEN DARF ---
function canEditAushang($aushangId) {
    if (!isLoggedIn()) return false;
    
    // Meister darf alles bearbeiten
    if (isMeister()) return true;
    
    $db = initAushaengeDB();
    if (!$db) return false;
    
    // Prüfen ob der User der Ersteller ist
    $stmt = $db->prepare("SELECT author_id FROM zettel WHERE id = :id");
    $stmt->execute([':id' => $aushangId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entry && $entry['author_id'] == $_SESSION['user_id']) {
        return true;
    }
    
    return false;
}

// --- AUSHANG BEARBEITEN ---
function updateAushang($id, $titel, $inhalt, $signatur, $formatType = null) {
    if (!canEditAushang($id)) {
        return ['success' => false, 'message' => 'Du darfst diesen Aushang nicht bearbeiten.'];
    }
    
    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];
    
    // Wenn kein formatType angegeben, behalte den alten bei
    if ($formatType === null) {
        $stmt = $db->prepare("SELECT format_type FROM zettel WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        $formatType = $entry['format_type'] ?? 'markdown';
    }
    
    $stmt = $db->prepare("UPDATE zettel SET titel = :titel, inhalt = :inhalt, signatur = :signatur, format_type = :format_type WHERE id = :id");
    $result = $stmt->execute([
        ':titel' => trim($titel),
        ':inhalt' => trim($inhalt),
        ':signatur' => trim($signatur),
        ':format_type' => $formatType,
        ':id' => $id
    ]);
    
    if ($result) {
        return ['success' => true, 'message' => 'Aushang erfolgreich aktualisiert.'];
    } else {
        return ['success' => false, 'message' => 'Fehler beim Aktualisieren.'];
    }
}

// --- AUSHANG RENDERN (abhängig vom Format) ---
function renderAushangContent($content, $formatType = 'markdown') {
    if ($formatType === 'html') {
        return sanitizeHTML($content);
    } else {
        return parseRichText($content);
    }
}

function renderAushangTitle($title, $formatType = 'markdown') {
    if ($formatType === 'html') {
        return sanitizeHTML($title);
    } else {
        return parseRichTextSimple($title);
    }
}

// --- AUSHANG LÖSCHEN (NUR ADMIN) ---
function deleteAushang($id) {
    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];
    
    // Erst schauen, ob wir ein Bild löschen müssen
    $stmt = $db->prepare("SELECT bild_pfad FROM zettel WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entry) {
        // Zettel aus DB löschen
        $delStmt = $db->prepare("DELETE FROM zettel WHERE id = :id");
        if ($delStmt->execute([':id' => $id])) {
            
            // Bilddatei vom Server putzen, wenn vorhanden
            if (!empty($entry['bild_pfad'])) {
                $fullPath = __DIR__ . '/' . $entry['bild_pfad'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            return ['success' => true, 'message' => 'Zettel wurde vom Brett gerissen.'];
        }
    }
    return ['success' => false, 'message' => 'Konnte den Zettel nicht finden.'];
}

// --- WICHTIG-SIEGEL TOGGLE (nur Meister) ---
function toggleWichtig($id) {
    if (!isMeister()) return ['success' => false, 'message' => 'Nur der Meister darf das.'];

    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];

    $stmt = $db->prepare("SELECT ist_wichtig FROM zettel WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) return ['success' => false, 'message' => 'Zettel nicht gefunden.'];

    $newValue = $entry['ist_wichtig'] ? 0 : 1;
    $stmt = $db->prepare("UPDATE zettel SET ist_wichtig = :val WHERE id = :id");
    $stmt->execute([':val' => $newValue, ':id' => $id]);

    $text = $newValue ? '🔴 Wichtig-Siegel gesetzt!' : 'Wichtig-Siegel entfernt.';
    return ['success' => true, 'message' => $text];
}

// --- ANHEFTEN TOGGLE (nur Meister) ---
function toggleAngeheftet($id) {
    if (!isMeister()) return ['success' => false, 'message' => 'Nur der Meister darf das.'];

    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];

    $stmt = $db->prepare("SELECT angeheftet FROM zettel WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) return ['success' => false, 'message' => 'Zettel nicht gefunden.'];

    $newValue = $entry['angeheftet'] ? 0 : 1;
    $stmt = $db->prepare("UPDATE zettel SET angeheftet = :val WHERE id = :id");
    $stmt->execute([':val' => $newValue, ':id' => $id]);

    $text = $newValue ? '📌 Zettel angeheftet!' : 'Zettel losgeheftet.';
    return ['success' => true, 'message' => $text];
}

// --- NOTIZ HINZUFÜGEN ---
function addNotiz($zettelId, $text, $autorName, $autorId = null) {
    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];

    // Prüfen ob Zettel existiert
    $stmt = $db->prepare("SELECT id FROM zettel WHERE id = :id");
    $stmt->execute([':id' => $zettelId]);
    if (!$stmt->fetch()) return ['success' => false, 'message' => 'Zettel nicht gefunden.'];

    $stmt = $db->prepare("INSERT INTO zettel_notizen (zettel_id, text, autor_name, autor_id) VALUES (:zettel_id, :text, :autor_name, :autor_id)");
    $result = $stmt->execute([
        ':zettel_id' => $zettelId,
        ':text' => trim($text),
        ':autor_name' => trim($autorName),
        ':autor_id' => $autorId
    ]);

    if ($result) {
        return ['success' => true, 'message' => '📎 Notiz angeheftet!'];
    }
    return ['success' => false, 'message' => 'Fehler beim Anheften.'];
}

// --- NOTIZEN LADEN ---
function getNotizen($zettelId) {
    $db = initAushaengeDB();
    if (!$db) return [];

    $stmt = $db->prepare("SELECT * FROM zettel_notizen WHERE zettel_id = :id ORDER BY erstellt_am ASC");
    $stmt->execute([':id' => $zettelId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- NOTIZ-ANZAHL ---
function getNotizCount($zettelId) {
    $db = initAushaengeDB();
    if (!$db) return 0;

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM zettel_notizen WHERE zettel_id = :id");
    $stmt->execute([':id' => $zettelId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['cnt'] ?? 0);
}

// --- NOTIZ LÖSCHEN ---
function deleteNotiz($id) {
    $db = initAushaengeDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];

    // Meister darf alles löschen, andere nur eigene
    if (!isMeister()) {
        $stmt = $db->prepare("SELECT autor_id FROM zettel_notizen WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $notiz = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$notiz || $notiz['autor_id'] != ($_SESSION['user_id'] ?? -1)) {
            return ['success' => false, 'message' => 'Nur eigene Notizen dürfen entfernt werden.'];
        }
    }

    $stmt = $db->prepare("DELETE FROM zettel_notizen WHERE id = :id");
    if ($stmt->execute([':id' => $id])) {
        return ['success' => true, 'message' => 'Notiz entfernt.'];
    }
    return ['success' => false, 'message' => 'Fehler beim Löschen.'];
}
?>
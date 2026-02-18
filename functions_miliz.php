<?php
// DIREKTER AUFRUF VERBOTEN
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Direct access to this file is not allowed.</p></body></html>');
}

// --- MILIZ KONFIGURATION ---
define('MILIZ_DB_PATH', __DIR__ . '/miliz/miliz.db');
define('MILIZ_UPLOAD_DIR', __DIR__ . '/miliz/');

// Kategorien der Miliz
define('MILIZ_CATEGORIES', [
    'befehle' => ['label' => '📜 Befehle & Verordnungen', 'color' => '#d4af37'],
    'steckbriefe' => ['label' => '🎖️ Steckbriefe der Milizionäre', 'color' => '#0070dd'],
    'gesucht' => ['label' => '⚔️ Gesuchte Personen', 'color' => '#ff8000'],
    'protokolle' => ['label' => '📋 Einsatz-Protokolle', 'color' => '#1eff00'],
    'waffenkammer' => ['label' => '🗡️ Waffenkammer', 'color' => '#a335ee'],
    'intern' => ['label' => '🔒 Interne Aushänge', 'color' => '#800000']
]);

// Initialisierung
if (!is_dir(MILIZ_UPLOAD_DIR)) mkdir(MILIZ_UPLOAD_DIR, 0755, true);
foreach (array_keys(MILIZ_CATEGORIES) as $cat) {
    $catDir = MILIZ_UPLOAD_DIR . $cat . '/';
    if (!is_dir($catDir)) mkdir($catDir, 0755, true);
}

// --- DATENBANK INITIALISIERUNG ---
function initMilizDB() {
    try {
        $db = new PDO('sqlite:' . MILIZ_DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Tabelle erstellen falls nicht vorhanden
        $db->exec("
            CREATE TABLE IF NOT EXISTS miliz_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                author TEXT DEFAULT 'Die Miliz',
                file_path TEXT DEFAULT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                priority INTEGER DEFAULT 0,
                visible INTEGER DEFAULT 1
            )
        ");

        // Migration: Status-Spalte für Steckbriefe/Gesuchte
        try {
            $db->exec("ALTER TABLE miliz_entries ADD COLUMN status TEXT DEFAULT 'aktiv'");
        } catch (PDOException $e) {
            // Spalte existiert bereits
        }

        // Briefkasten-Tabelle (anonyme Hinweise)
        $db->exec("CREATE TABLE IF NOT EXISTS miliz_briefkasten (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            betreff TEXT NOT NULL,
            nachricht TEXT NOT NULL,
            absender TEXT DEFAULT 'Anonym',
            erstellt_am INTEGER NOT NULL,
            gelesen INTEGER DEFAULT 0,
            gelesen_von TEXT DEFAULT NULL,
            gelesen_am INTEGER DEFAULT NULL
        )");

        // Waffenkammer-Inventar (echte Tabelle)
        $db->exec("CREATE TABLE IF NOT EXISTS miliz_waffenkammer (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            beschreibung TEXT DEFAULT '',
            bestand INTEGER DEFAULT 1,
            zustand TEXT DEFAULT 'gut',
            ausgegeben_an TEXT DEFAULT NULL,
            erstellt_am INTEGER NOT NULL,
            aktualisiert_am INTEGER NOT NULL
        )");

        return $db;
    } catch (PDOException $e) {
        error_log('Miliz DB Error: ' . $e->getMessage());
        return null;
    }
}

// --- EINTRAG ERSTELLEN ---
function createMilizEntry($category, $title, $content, $author = 'Die Miliz', $filePath = null, $priority = 0) {
    if (!hasPermission('miliz', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }
    
    $db = initMilizDB();
    if (!$db) {
        return ['type' => 'error', 'text' => '❌ Datenbank-Fehler.'];
    }
    
    try {
        $timestamp = time();
        $stmt = $db->prepare("
            INSERT INTO miliz_entries (category, title, content, author, file_path, created_at, updated_at, priority)
            VALUES (:category, :title, :content, :author, :file_path, :created_at, :updated_at, :priority)
        ");
        
        $stmt->execute([
            ':category' => $category,
            ':title' => $title,
            ':content' => $content,
            ':author' => $author,
            ':file_path' => $filePath,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
            ':priority' => $priority
        ]);
        
        return ['type' => 'success', 'text' => '✅ Eintrag erfolgreich erstellt!'];
    } catch (PDOException $e) {
        return ['type' => 'error', 'text' => '❌ Fehler beim Speichern.'];
    }
}

// --- GÜLTIGE STATUS-WERTE ---
define('MILIZ_STATUS_VALUES', [
    'aktiv'       => ['label' => 'Aktiv',       'icon' => '🟢', 'color' => '#1eff00'],
    'fluechtig'   => ['label' => 'Flüchtig',    'icon' => '🟠', 'color' => '#ff8000'],
    'inhaftiert'  => ['label' => 'Inhaftiert',   'icon' => '⚪', 'color' => '#9d9d9d'],
    'verstorben'  => ['label' => 'Verstorben',   'icon' => '💀', 'color' => '#800000']
]);

// --- EINTRÄGE LADEN (mit optionalem Status-Filter) ---
function getMilizEntries($category = null, $visibleOnly = true, $statusFilter = null) {
    $db = initMilizDB();
    if (!$db) return [];

    try {
        $sql = "SELECT * FROM miliz_entries WHERE 1=1";
        $params = [];

        if ($category) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        if ($visibleOnly) {
            $sql .= " AND visible = 1";
        }

        if ($statusFilter && array_key_exists($statusFilter, MILIZ_STATUS_VALUES)) {
            $sql .= " AND status = :status";
            $params[':status'] = $statusFilter;
        }

        $sql .= " ORDER BY priority DESC, created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// --- STATUS EINES EINTRAGS ÄNDERN ---
function updateMilizEntryStatus($id, $status) {
    if (!hasPermission('miliz', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }

    if (!array_key_exists($status, MILIZ_STATUS_VALUES)) {
        return ['type' => 'error', 'text' => '❌ Ungültiger Status.'];
    }

    $db = initMilizDB();
    if (!$db) return ['type' => 'error', 'text' => '❌ Datenbank-Fehler.'];

    try {
        $stmt = $db->prepare("UPDATE miliz_entries SET status = :status, updated_at = :updated_at WHERE id = :id");
        $stmt->execute([':status' => $status, ':updated_at' => time(), ':id' => $id]);
        $label = MILIZ_STATUS_VALUES[$status]['label'];
        return ['type' => 'success', 'text' => "Status auf '{$label}' gesetzt."];
    } catch (PDOException $e) {
        return ['type' => 'error', 'text' => '❌ Fehler beim Aktualisieren.'];
    }
}

// --- EINTRAG LÖSCHEN ---
function deleteMilizEntry($id) {
    if (!hasPermission('miliz', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }
    
    $db = initMilizDB();
    if (!$db) {
        return ['type' => 'error', 'text' => '❌ Datenbank-Fehler.'];
    }
    
    try {
        // Erst Datei löschen falls vorhanden
        $stmt = $db->prepare("SELECT file_path FROM miliz_entries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($entry && $entry['file_path'] && file_exists($entry['file_path'])) {
            unlink($entry['file_path']);
        }
        
        // Dann DB-Eintrag löschen
        $stmt = $db->prepare("DELETE FROM miliz_entries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        return ['type' => 'success', 'text' => '🔥 Eintrag wurde vernichtet!'];
    } catch (PDOException $e) {
        return ['type' => 'error', 'text' => '❌ Fehler beim Löschen.'];
    }
}

// --- MILIZ-DATEI UPLOAD ---
function handleMilizFileUpload($fileArray, $category) {
    if (!hasPermission('miliz', 'upload')) {
        return ['success' => false, 'message' => 'Zugriff verweigert.'];
    }
    
    // Gleiche Validierung wie normale Uploads
    if ($fileArray['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Datei zu groß!'];
    }
    
    $fileName = basename($fileArray['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Dateityp nicht erlaubt!'];
    }
    
    // MIME-Type prüfen
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, ALLOWED_MIMES)) {
            return ['success' => false, 'message' => 'Ungültiger Dateityp!'];
        }
    }
    
    // Dateiname säubern
    $fileName = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $fileName);
    
    // Zielverzeichnis
    $targetDir = MILIZ_UPLOAD_DIR . $category . '/';
    $targetPath = $targetDir . $fileName;
    
    // Duplikat-Schutz
    if (file_exists($targetPath)) {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $counter = 1;
        while (file_exists($targetDir . "{$name}_{$counter}.{$ext}")) {
            $counter++;
        }
        $fileName = "{$name}_{$counter}.{$ext}";
        $targetPath = $targetDir . $fileName;
    }
    
    // Upload
    if (move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'filename' => $fileName];
    } else {
        return ['success' => false, 'message' => 'Upload fehlgeschlagen.'];
    }
}

// --- STATISTIK ---
function getMilizStats() {
    $db = initMilizDB();
    if (!$db) return [];

    $stats = [];
    foreach (array_keys(MILIZ_CATEGORIES) as $cat) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM miliz_entries WHERE category = :category AND visible = 1");
        $stmt->execute([':category' => $cat]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats[$cat] = $result['count'];
    }

    return $stats;
}

// ============================================
// BÜRGER-BRIEFKASTEN (Anonyme Hinweise)
// ============================================

// Rate-Limiting Konstante
define('BRIEFKASTEN_COOLDOWN', 300); // 5 Minuten zwischen Nachrichten

/**
 * Anonymen Hinweis einwerfen (auch ohne Login)
 */
function createBriefkastenNachricht($betreff, $nachricht, $absender = 'Anonym') {
    // Rate-Limiting per Session
    if (isset($_SESSION['briefkasten_last_submit'])) {
        $elapsed = time() - $_SESSION['briefkasten_last_submit'];
        if ($elapsed < BRIEFKASTEN_COOLDOWN) {
            $remaining = BRIEFKASTEN_COOLDOWN - $elapsed;
            return ['success' => false, 'message' => "Bitte warte noch {$remaining} Sekunden."];
        }
    }

    $betreff   = trim($betreff);
    $nachricht = trim($nachricht);
    $absender  = trim($absender) ?: 'Anonym';

    if (empty($betreff) || empty($nachricht)) {
        return ['success' => false, 'message' => 'Betreff und Nachricht sind Pflicht.'];
    }

    if (mb_strlen($betreff) > 200 || mb_strlen($nachricht) > 2000) {
        return ['success' => false, 'message' => 'Text zu lang (max. 200 / 2000 Zeichen).'];
    }

    $db = initMilizDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];

    try {
        $stmt = $db->prepare("INSERT INTO miliz_briefkasten (betreff, nachricht, absender, erstellt_am) VALUES (:betreff, :nachricht, :absender, :erstellt_am)");
        $stmt->execute([
            ':betreff'     => $betreff,
            ':nachricht'   => $nachricht,
            ':absender'    => $absender,
            ':erstellt_am' => time()
        ]);

        $_SESSION['briefkasten_last_submit'] = time();
        return ['success' => true, 'message' => '📬 Dein Hinweis wurde anonym eingeworfen.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Fehler beim Speichern.'];
    }
}

/**
 * Alle Briefkasten-Nachrichten laden (nur Miliz/Meister)
 */
function getBriefkastenNachrichten($unreadOnly = false) {
    $db = initMilizDB();
    if (!$db) return [];

    try {
        $sql = "SELECT * FROM miliz_briefkasten";
        if ($unreadOnly) {
            $sql .= " WHERE gelesen = 0";
        }
        $sql .= " ORDER BY gelesen ASC, erstellt_am DESC";

        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Briefkasten-Nachricht als gelesen markieren
 */
function markBriefkastenGelesen($id) {
    if (!hasPermission('miliz', 'read')) return false;

    $db = initMilizDB();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("UPDATE miliz_briefkasten SET gelesen = 1, gelesen_von = :von, gelesen_am = :am WHERE id = :id");
        $stmt->execute([
            ':von' => $_SESSION['username'] ?? 'Unbekannt',
            ':am'  => time(),
            ':id'  => $id
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Briefkasten-Nachricht löschen (nur Meister)
 */
function deleteBriefkastenNachricht($id) {
    if (!isMeister()) return ['success' => false, 'message' => 'Nur der Meister darf das.'];

    $db = initMilizDB();
    if (!$db) return ['success' => false, 'message' => 'Datenbankfehler.'];

    try {
        $stmt = $db->prepare("DELETE FROM miliz_briefkasten WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true, 'message' => 'Nachricht gelöscht.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Fehler.'];
    }
}

/**
 * Anzahl ungelesener Briefkasten-Nachrichten
 */
function getBriefkastenUnreadCount() {
    $db = initMilizDB();
    if (!$db) return 0;

    try {
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM miliz_briefkasten WHERE gelesen = 0");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['cnt'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

// ============================================
// WAFFENKAMMER-INVENTAR (Echte Tabelle)
// ============================================

define('WAFFENKAMMER_ZUSTAND', [
    'gut'        => ['label' => 'Gut',        'class' => 'inventar-zustand--gut'],
    'beschaedigt'=> ['label' => 'Beschädigt', 'class' => 'inventar-zustand--beschaedigt'],
    'defekt'     => ['label' => 'Defekt',     'class' => 'inventar-zustand--defekt'],
    'ausgegeben' => ['label' => 'Ausgegeben', 'class' => 'inventar-zustand--ausgegeben']
]);

/**
 * Waffenkammer-Eintrag erstellen
 */
function createWaffenkammerItem($name, $beschreibung, $bestand, $zustand) {
    if (!hasPermission('miliz', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }

    if (!array_key_exists($zustand, WAFFENKAMMER_ZUSTAND)) {
        $zustand = 'gut';
    }

    $db = initMilizDB();
    if (!$db) return ['type' => 'error', 'text' => '❌ Datenbank-Fehler.'];

    try {
        $now = time();
        $stmt = $db->prepare("INSERT INTO miliz_waffenkammer (name, beschreibung, bestand, zustand, erstellt_am, aktualisiert_am) VALUES (:name, :beschreibung, :bestand, :zustand, :erstellt_am, :aktualisiert_am)");
        $stmt->execute([
            ':name'           => trim($name),
            ':beschreibung'   => trim($beschreibung),
            ':bestand'        => max(0, intval($bestand)),
            ':zustand'        => $zustand,
            ':erstellt_am'    => $now,
            ':aktualisiert_am'=> $now
        ]);
        return ['type' => 'success', 'text' => '✅ Gegenstand hinzugefügt!'];
    } catch (PDOException $e) {
        return ['type' => 'error', 'text' => '❌ Fehler.'];
    }
}

/**
 * Waffenkammer-Inventar laden
 */
function getWaffenkammerItems() {
    $db = initMilizDB();
    if (!$db) return [];

    try {
        $stmt = $db->query("SELECT * FROM miliz_waffenkammer ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Waffenkammer-Eintrag aktualisieren
 */
function updateWaffenkammerItem($id, $data) {
    if (!hasPermission('miliz', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }

    $db = initMilizDB();
    if (!$db) return ['type' => 'error', 'text' => '❌ Datenbank-Fehler.'];

    try {
        $sets = ['aktualisiert_am = :aktualisiert_am'];
        $params = [':id' => $id, ':aktualisiert_am' => time()];

        foreach (['name', 'beschreibung', 'bestand', 'zustand', 'ausgegeben_an'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        $sql = "UPDATE miliz_waffenkammer SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return ['type' => 'success', 'text' => '✅ Aktualisiert!'];
    } catch (PDOException $e) {
        return ['type' => 'error', 'text' => '❌ Fehler.'];
    }
}

/**
 * Waffenkammer-Eintrag löschen
 */
function deleteWaffenkammerItem($id) {
    if (!hasPermission('miliz', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }

    $db = initMilizDB();
    if (!$db) return ['type' => 'error', 'text' => '❌ Datenbank-Fehler.'];

    try {
        $stmt = $db->prepare("DELETE FROM miliz_waffenkammer WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['type' => 'success', 'text' => '🔥 Gegenstand entfernt!'];
    } catch (PDOException $e) {
        return ['type' => 'error', 'text' => '❌ Fehler.'];
    }
}
?>

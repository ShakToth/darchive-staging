<?php
// DIREKTER AUFRUF VERBOTEN
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Direct access to this file is not allowed.</p></body></html>');
}

// SICHERHEITS-EINSTELLUNGEN
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Session-Sicherheit - VOR session_start() konfigurieren
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
// Falls HTTPS (empfohlen!):
// ini_set('session.cookie_secure', 1);

session_start();

// ============================================
// ROLLENSYSTEM & AUTHENTIFIZIERUNG
// ============================================

// Verfügbare Sektionen für das Berechtigungssystem
define('PERMISSION_SECTIONS', ['bibliothek', 'miliz', 'aushaenge', 'verwaltung']);

/**
 * Verbindung zur Benutzerdatenbank herstellen
 */
function getUserDB() {
    static $db = null;
    if ($db === null) {
        try {
            $dbPath = __DIR__ . '/auth/users.db';
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('PRAGMA foreign_keys = ON');

            // Auto-Migration: Rollen-/Berechtigungstabellen erstellen falls nötig
            migrateRolesSystem($db);
        } catch (PDOException $e) {
            die("Datenbankfehler: " . $e->getMessage());
        }
    }
    return $db;
}

/**
 * Auto-Migration: Erstellt roles + role_permissions Tabellen
 * und entfernt den CHECK-Constraint aus der users-Tabelle
 */
function migrateRolesSystem($db) {
    // Prüfe ob Migration bereits durchgeführt
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='roles'");
    if ($check->fetch()) {
        return;
    }

    $db->beginTransaction();
    try {
        // 1. Rollen-Tabelle erstellen
        $db->exec("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                display_name TEXT NOT NULL,
                icon TEXT DEFAULT '',
                color TEXT DEFAULT '#9d9d9d',
                is_system INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 2. Standard-Rollen seeden
        $db->exec("
            INSERT INTO roles (name, display_name, icon, color, is_system) VALUES
                ('meister', 'Meister', '👑', '#ff8000', 1),
                ('bibliothekar', 'Bibliothekar', '📚', '#a335ee', 1),
                ('miliz', 'Miliz', '⚔️', '#0070dd', 1),
                ('buerger', 'Bürger', '👤', '#9d9d9d', 1)
        ");

        // 3. Berechtigungs-Tabelle erstellen
        $db->exec("
            CREATE TABLE role_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name TEXT NOT NULL,
                section TEXT NOT NULL,
                can_read INTEGER DEFAULT 0,
                can_write INTEGER DEFAULT 0,
                can_upload INTEGER DEFAULT 0,
                UNIQUE(role_name, section),
                FOREIGN KEY (role_name) REFERENCES roles(name) ON DELETE CASCADE
            )
        ");

        // 4. Standard-Berechtigungen seeden (entspricht bisherigem Verhalten)
        $defaultPerms = [
            // meister: Vollzugriff überall
            ['meister', 'bibliothek', 1, 1, 1],
            ['meister', 'miliz', 1, 1, 1],
            ['meister', 'aushaenge', 1, 1, 1],
            ['meister', 'verwaltung', 1, 1, 1],
            // bibliothekar: Bibliothek voll, Rest lesen
            ['bibliothekar', 'bibliothek', 1, 1, 1],
            ['bibliothekar', 'miliz', 1, 0, 0],
            ['bibliothekar', 'aushaenge', 1, 1, 0],
            ['bibliothekar', 'verwaltung', 0, 0, 0],
            // miliz: Miliz voll, Rest lesen
            ['miliz', 'bibliothek', 1, 0, 0],
            ['miliz', 'miliz', 1, 1, 1],
            ['miliz', 'aushaenge', 1, 1, 0],
            ['miliz', 'verwaltung', 0, 0, 0],
            // buerger: Nur lesen, Aushänge schreiben
            ['buerger', 'bibliothek', 1, 0, 0],
            ['buerger', 'miliz', 1, 0, 0],
            ['buerger', 'aushaenge', 1, 1, 0],
            ['buerger', 'verwaltung', 0, 0, 0],
        ];

        $permStmt = $db->prepare("
            INSERT INTO role_permissions (role_name, section, can_read, can_write, can_upload)
            VALUES (:role, :section, :read, :write, :upload)
        ");
        foreach ($defaultPerms as $p) {
            $permStmt->execute([
                ':role' => $p[0], ':section' => $p[1],
                ':read' => $p[2], ':write' => $p[3], ':upload' => $p[4]
            ]);
        }

        // 5. Users-Tabelle ohne CHECK-Constraint neu erstellen
        $db->exec("ALTER TABLE users RENAME TO users_backup");
        $db->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                FOREIGN KEY (role) REFERENCES roles(name)
            )
        ");
        $db->exec("INSERT INTO users SELECT * FROM users_backup");
        $db->exec("DROP TABLE users_backup");

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Rollen-Migration fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Benutzer-Login
 */
function loginUser($username, $password) {
    $db = getUserDB();
    $stmt = $db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Login erfolgreich
        session_regenerate_id(true); // Session-Fixation verhindern
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Rollen-Anzeigeinformationen in Session speichern
        $roleInfo = getRoleInfo($user['role']);
        if ($roleInfo) {
            $_SESSION['role_display'] = $roleInfo['display_name'];
            $_SESSION['role_icon'] = $roleInfo['icon'];
            $_SESSION['role_color'] = $roleInfo['color'];
        }

        // Last Login aktualisieren
        $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);

        return true;
    }
    return false;
}

/**
 * Benutzer-Logout
 */
function logoutUser() {
    session_destroy();
    session_start();
    generateCSRFToken(); // Neues Token für die Login-Seite
}

/**
 * Prüfen, ob ein Benutzer eingeloggt ist
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

/**
 * Aktuelle Benutzerrolle abrufen
 */
function getUserRole() {
    return isLoggedIn() ? $_SESSION['role'] : null;
}

/**
 * Aktuellen Benutzernamen abrufen
 */
function getUsername() {
    return isLoggedIn() ? $_SESSION['username'] : 'Gast';
}

/**
 * Prüfen, ob der Benutzer eine bestimmte Rolle hat
 */
function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role = getUserRole();
    
    // Hierarchie-System: Meister hat alle Rechte
    if ($role === 'meister') {
        return true;
    }
    
    return $role === $requiredRole;
}

/**
 * Prüfen, ob der Benutzer mindestens eine der angegebenen Rollen hat
 */
function hasAnyRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    foreach ($roles as $role) {
        if (hasRole($role)) {
            return true;
        }
    }
    return false;
}

/**
 * Prüfen, ob der Benutzer Meister ist (höchste Berechtigung)
 */
function isMeister() {
    return hasRole('meister');
}

/**
 * LEGACY: Kompatibilität mit altem isAdmin()
 * Kann später entfernt werden, wenn alle Dateien umgestellt sind
 */
function isAdmin() {
    return isMeister();
}

/**
 * Erzwinge Login - Weiterleitung zur Login-Seite wenn nicht eingeloggt
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Erzwinge bestimmte Rolle - 403 Fehler wenn nicht berechtigt
 */
function requireRole($requiredRole) {
    requireLogin();
    if (!hasRole($requiredRole)) {
        http_response_code(403);
        die("⛔ Zugriff verweigert. Erforderliche Rolle: " . htmlspecialchars($requiredRole));
    }
}

// ============================================
// BERECHTIGUNGSSYSTEM (Datenbankgesteuert)
// ============================================

/**
 * Prüft ob der aktuelle Benutzer eine bestimmte Berechtigung
 * in einem Bereich hat.
 *
 * @param string $section 'bibliothek', 'miliz', 'aushaenge', 'verwaltung'
 * @param string $action  'read', 'write', 'upload'
 * @return bool
 */
function hasPermission($section, $action = 'read') {
    if (!isLoggedIn()) {
        return false;
    }

    $role = getUserRole();

    // Meister hat immer Vollzugriff (hardcodiertes Sicherheitsnetz)
    if ($role === 'meister') {
        return true;
    }

    $columnMap = [
        'read'   => 'can_read',
        'write'  => 'can_write',
        'upload' => 'can_upload'
    ];

    $column = $columnMap[$action] ?? null;
    if (!$column) {
        return false;
    }

    // Cache pro Request um wiederholte DB-Abfragen zu vermeiden
    static $permCache = [];
    $cacheKey = $role . ':' . $section;

    if (!isset($permCache[$cacheKey])) {
        $db = getUserDB();
        $stmt = $db->prepare(
            "SELECT can_read, can_write, can_upload
             FROM role_permissions
             WHERE role_name = :role AND section = :section"
        );
        $stmt->execute([':role' => $role, ':section' => $section]);
        $permCache[$cacheKey] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'can_read' => 0, 'can_write' => 0, 'can_upload' => 0
        ];
    }

    return (bool)$permCache[$cacheKey][$column];
}

/**
 * Erzwinge Berechtigung - 403 Fehler wenn nicht berechtigt
 */
function requirePermission($section, $action = 'read') {
    requireLogin();
    if (!hasPermission($section, $action)) {
        http_response_code(403);
        die("⛔ Zugriff verweigert.");
    }
}

// ============================================
// ROLLENVERWALTUNG (CRUD)
// ============================================

/**
 * Rollen-Info abrufen (Name, Icon, Farbe)
 */
function getRoleInfo($roleName) {
    $db = getUserDB();
    try {
        $stmt = $db->prepare("SELECT * FROM roles WHERE name = :name");
        $stmt->execute([':name' => $roleName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Alle verfügbaren Rollen abrufen
 */
function getAllRoles() {
    $db = getUserDB();
    try {
        $stmt = $db->query("SELECT * FROM roles ORDER BY is_system DESC, name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Berechtigungen einer Rolle abrufen
 */
function getRolePermissions($roleName) {
    $db = getUserDB();
    try {
        $stmt = $db->prepare(
            "SELECT section, can_read, can_write, can_upload
             FROM role_permissions WHERE role_name = :role"
        );
        $stmt->execute([':role' => $roleName]);
        $perms = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $perms[$row['section']] = $row;
        }
        return $perms;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Neue Rolle erstellen (nur Meister)
 */
function createRole($name, $displayName, $icon, $color) {
    if (!isMeister()) return false;

    // Rollenname validieren (nur Kleinbuchstaben, Zahlen, Unterstriche)
    $name = strtolower(trim($name));
    if (!preg_match('/^[a-z][a-z0-9_]{1,30}$/', $name)) {
        return false;
    }

    $db = getUserDB();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            "INSERT INTO roles (name, display_name, icon, color, is_system)
             VALUES (:name, :display_name, :icon, :color, 0)"
        );
        $stmt->execute([
            ':name' => $name,
            ':display_name' => $displayName,
            ':icon' => $icon,
            ':color' => $color
        ]);

        // Berechtigungen für alle Sektionen initialisieren (alles aus)
        $permStmt = $db->prepare(
            "INSERT INTO role_permissions (role_name, section, can_read, can_write, can_upload)
             VALUES (:role, :section, 0, 0, 0)"
        );
        foreach (PERMISSION_SECTIONS as $section) {
            $permStmt->execute([':role' => $name, ':section' => $section]);
        }

        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Rolle löschen (nur Meister, keine System-Rollen)
 */
function deleteRole($roleName) {
    if (!isMeister()) return false;

    $db = getUserDB();

    // System-Rollen dürfen nicht gelöscht werden
    $stmt = $db->prepare("SELECT is_system FROM roles WHERE name = :name");
    $stmt->execute([':name' => $roleName]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role || $role['is_system']) {
        return false;
    }

    // Prüfe ob noch Benutzer diese Rolle haben
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
    $stmt->execute([':role' => $roleName]);
    if ($stmt->fetchColumn() > 0) {
        return false;
    }

    try {
        // FK CASCADE löscht automatisch die role_permissions
        $stmt = $db->prepare("DELETE FROM roles WHERE name = :name AND is_system = 0");
        $stmt->execute([':name' => $roleName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Berechtigungen einer Rolle aktualisieren (nur Meister)
 */
function updateRolePermissions($roleName, $section, $canRead, $canWrite, $canUpload) {
    if (!isMeister()) return false;

    // Meister-Berechtigungen dürfen nicht geändert werden
    if ($roleName === 'meister') return false;

    $db = getUserDB();
    try {
        $stmt = $db->prepare(
            "INSERT OR REPLACE INTO role_permissions
             (role_name, section, can_read, can_write, can_upload)
             VALUES (:role, :section, :read, :write, :upload)"
        );
        return $stmt->execute([
            ':role' => $roleName,
            ':section' => $section,
            ':read' => $canRead ? 1 : 0,
            ':write' => $canWrite ? 1 : 0,
            ':upload' => $canUpload ? 1 : 0
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Anzeige-Eigenschaften einer Rolle aktualisieren (nur Meister)
 */
function updateRoleDisplay($roleName, $displayName, $icon, $color) {
    if (!isMeister()) return false;

    $db = getUserDB();
    try {
        $stmt = $db->prepare(
            "UPDATE roles SET display_name = :display_name, icon = :icon, color = :color
             WHERE name = :name"
        );
        return $stmt->execute([
            ':name' => $roleName,
            ':display_name' => $displayName,
            ':icon' => $icon,
            ':color' => $color
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Anzahl Benutzer pro Rolle zählen
 */
function getUserCountByRole($roleName) {
    $db = getUserDB();
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
        $stmt->execute([':role' => $roleName]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Neuen Benutzer erstellen (nur für Meister)
 */
function createUser($username, $password, $role) {
    if (!isMeister()) {
        return false;
    }

    $db = getUserDB();

    // Rolle gegen die Datenbank validieren
    $roleCheck = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = :name");
    $roleCheck->execute([':name' => $role]);
    if ($roleCheck->fetchColumn() == 0) {
        return false;
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, role)
            VALUES (:username, :password_hash, :role)
        ");
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':role' => $role
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Benutzer-Passwort ändern
 */
function changePassword($userId, $newPassword) {
    // Nur eigenes Passwort oder als Meister
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] != $userId && !isMeister())) {
        return false;
    }
    
    $db = getUserDB();
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    
    try {
        $stmt = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $userId
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Alle Benutzer abrufen (nur für Meister)
 */
function getAllUsers() {
    if (!isMeister()) {
        return [];
    }

    $db = getUserDB();
    try {
        $stmt = $db->query("
            SELECT u.id, u.username, u.role, u.created_at, u.last_login,
                   r.display_name AS role_display, r.icon AS role_icon, r.color AS role_color
            FROM users u
            LEFT JOIN roles r ON u.role = r.name
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Benutzer löschen (nur für Meister)
 */
function deleteUser($userId) {
    if (!isMeister()) {
        return false;
    }
    
    // Verhindere dass der Meister sich selbst löscht
    if ($_SESSION['user_id'] == $userId) {
        return false;
    }
    
    $db = getUserDB();
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Benutzer-Rolle ändern (nur für Meister)
 */
function updateUserRole($userId, $newRole) {
    if (!isMeister()) {
        return false;
    }

    // Verhindere dass der Meister seine eigene Rolle ändert
    if ($_SESSION['user_id'] == $userId) {
        return false;
    }

    $db = getUserDB();

    // Rolle gegen die Datenbank validieren
    $roleCheck = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = :name");
    $roleCheck->execute([':name' => $newRole]);
    if ($roleCheck->fetchColumn() == 0) {
        return false;
    }

    try {
        $stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :id");
        $stmt->execute([
            ':role' => $newRole,
            ':id' => $userId
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Benutzer-Details abrufen
 */
function getUserById($userId) {
    $db = getUserDB();
    try {
        $stmt = $db->prepare("SELECT id, username, role, created_at, last_login FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// --- KONFIGURATION ---
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('FORBIDDEN_DIR', __DIR__ . '/uploads/verboten/');
// LEGACY: Altes Admin-Passwort - NICHT MEHR VERWENDET (siehe Rollensystem)
// define('ADMIN_PASSWORD', 'C6%p\\I{6l*6O£#3#');
// define('ADMIN_HASH', '$2a$16$E1wvy/.QAfFKOlg83XTwKuAH5vg1ZaMuUxxwmfv7tWH0ORNaqAZlG');

// Dateien die ignoriert werden
define('IGNORE_FILES', ['.', '..', '@eaDir', 'Thumbs.db', '.DS_Store', '.htaccess', '.htaccess_synology', '.htaccess_synology_v2', '.htaccess_fallback', 'functions.php', 'functions_wow.php', 'index.php', 'index_wow.php', 'style.css', 'style_wow.css', '.git', '.gitignore', 'composer.json', 'package.json', 'test.php', 'security-test.html']);

// Upload-Sicherheit
define('MAX_FILE_SIZE', 320 * 1024 * 1024); // 320 MB
define('ALLOWED_EXTENSIONS', ['pdf', 'txt', 'md', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'epub', 'html', 'htm']);
define('ALLOWED_MIMES', [
    'application/pdf',
    'text/plain',
    'text/markdown',
    'text/html',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
    'application/x-rar-compressed',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4',
    'video/quicktime',
    'application/epub+zip'
]);

// Login Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 Minuten

// Initialisierung
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(FORBIDDEN_DIR)) mkdir(FORBIDDEN_DIR, 0755, true);

// --- CSRF PROTECTION ---
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Alias für generateCSRFToken() - für Kompatibilität
 */
function getCSRFToken() {
    return generateCSRFToken();
}

// --- HELPER FUNKTIONEN ---
// Hinweis: isAdmin() ist jetzt weiter oben als Legacy-Funktion definiert (nutzt Rollensystem)

// --- WOW ITEM QUALITY SYSTEM ---
function getItemQuality($filename, $isForbidden = false) {
    // Verbotene Dateien sind immer Legendary
    if ($isForbidden) {
        return 'legendary';
    }

    // Manuelle Qualität aus DB hat Vorrang
    $meta = getFileMetadata($filename);
    if ($meta && $meta['quality'] !== null && $meta['quality'] !== '') {
        return $meta['quality'];
    }

    // Fallback: Auto-Erkennung nach Dateiendung
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Common (Grau) - Einfache Textdateien
    if (in_array($ext, ['txt', 'md'])) {
        return 'common';
    }

    // Uncommon (Grün) - Office Dokumente
    if (in_array($ext, ['doc', 'docx'])) {
        return 'uncommon';
    }

    // Rare (Blau) - PDFs und Spreadsheets
    if (in_array($ext, ['pdf', 'xls', 'xlsx'])) {
        return 'rare';
    }

    // Epic (Lila) - Spezielle Formate
    if (in_array($ext, ['epub', 'zip', 'rar', 'mp4', 'mov'])) {
        return 'epic';
    }

    // Default: Common für Bilder und unbekannte
    return 'common';
}

function getQualityLabel($quality) {
    $labels = [
        'common' => 'Gewöhnlich',
        'uncommon' => 'Ungewöhnlich',
        'rare' => 'Selten',
        'epic' => 'Episch',
        'legendary' => 'Legendär'
    ];
    return $labels[$quality] ?? 'Gewöhnlich';
}

// ============================================
// BIBLIOTHEK-DATENBANK (Datei-Metadaten)
// ============================================

define('BIBLIOTHEK_DB_PATH', __DIR__ . '/uploads/bibliothek.db');

/**
 * Verbindung zur Bibliothek-Datenbank herstellen
 * Speichert Qualität, Beschreibung, Ausleih-Log pro Datei
 */
function getBibliothekDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . BIBLIOTHEK_DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('PRAGMA foreign_keys = ON');

            // Auto-Migration
            migrateBibliothekDB($db);
        } catch (PDOException $e) {
            error_log('Bibliothek DB Error: ' . $e->getMessage());
            return null;
        }
    }
    return $db;
}

/**
 * Auto-Migration für die Bibliothek-Datenbank
 */
function migrateBibliothekDB($db) {
    // Datei-Metadaten
    $db->exec("CREATE TABLE IF NOT EXISTS file_metadata (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT UNIQUE NOT NULL,
        category TEXT DEFAULT 'normal',
        quality TEXT DEFAULT NULL,
        description TEXT DEFAULT '',
        uploaded_by TEXT DEFAULT '',
        uploaded_at INTEGER NOT NULL DEFAULT 0,
        last_read_by TEXT DEFAULT '',
        last_read_at INTEGER DEFAULT NULL,
        kopien INTEGER DEFAULT 1
    )");

    // Rückwärtskompatibilität: kopien-Spalte zu bestehenden DBs hinzufügen
    try { $db->exec("ALTER TABLE file_metadata ADD COLUMN kopien INTEGER DEFAULT 1"); } catch (PDOException $e) {}

    // Ausleih-Logbuch mit Rückgabe-Unterstützung
    $db->exec("CREATE TABLE IF NOT EXISTS read_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        reader_name TEXT NOT NULL,
        read_at INTEGER NOT NULL,
        zurueckgegeben_am INTEGER DEFAULT NULL,
        zurueckgegeben_von TEXT DEFAULT NULL
    )");

    // Rückwärtskompatibilität: Rückgabe-Spalten zu bestehenden DBs hinzufügen
    try { $db->exec("ALTER TABLE read_log ADD COLUMN zurueckgegeben_am INTEGER DEFAULT NULL"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE read_log ADD COLUMN zurueckgegeben_von TEXT DEFAULT NULL"); } catch (PDOException $e) {}
}

/**
 * Datei-Metadaten abrufen
 */
function getFileMetadata($filename) {
    $db = getBibliothekDB();
    if (!$db) return null;

    $stmt = $db->prepare("SELECT * FROM file_metadata WHERE filename = :filename");
    $stmt->execute([':filename' => $filename]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Datei-Metadaten erstellen oder aktualisieren
 */
function saveFileMetadata($filename, $data = []) {
    $db = getBibliothekDB();
    if (!$db) return false;

    $existing = getFileMetadata($filename);

    if ($existing) {
        // Update
        $sets = [];
        $params = [':filename' => $filename];
        foreach (['quality', 'description', 'category', 'uploaded_by', 'last_read_by', 'last_read_at', 'kopien'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($sets)) return true;
        $sql = "UPDATE file_metadata SET " . implode(', ', $sets) . " WHERE filename = :filename";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO file_metadata (filename, category, quality, description, uploaded_by, uploaded_at)
            VALUES (:filename, :category, :quality, :description, :uploaded_by, :uploaded_at)");
        return $stmt->execute([
            ':filename' => $filename,
            ':category' => $data['category'] ?? 'normal',
            ':quality' => $data['quality'] ?? null,
            ':description' => $data['description'] ?? '',
            ':uploaded_by' => $data['uploaded_by'] ?? '',
            ':uploaded_at' => $data['uploaded_at'] ?? time()
        ]);
    }
}

/**
 * Qualität einer Datei manuell setzen
 */
function setFileQuality($filename, $quality) {
    $validQualities = ['common', 'uncommon', 'rare', 'epic', 'legendary', null];
    if (!in_array($quality, $validQualities)) return false;

    return saveFileMetadata($filename, ['quality' => $quality]);
}

/**
 * Datei als gelesen markieren
 */
function markFileAsRead($filename, $readerName) {
    $db = getBibliothekDB();
    if (!$db) return false;

    // Lese-Log eintragen
    $stmt = $db->prepare("INSERT INTO read_log (filename, reader_name, read_at) VALUES (:filename, :reader_name, :read_at)");
    $stmt->execute([
        ':filename' => $filename,
        ':reader_name' => $readerName,
        ':read_at' => time()
    ]);

    // Metadaten aktualisieren
    return saveFileMetadata($filename, [
        'last_read_by' => $readerName,
        'last_read_at' => time()
    ]);
}

/**
 * Lese-Logbuch einer Datei abrufen (inkl. Rückgabe-Info)
 */
function getReadLog($filename, $limit = 5) {
    $db = getBibliothekDB();
    if (!$db) return [];

    $stmt = $db->prepare("SELECT id, reader_name, read_at, zurueckgegeben_am, zurueckgegeben_von
                          FROM read_log
                          WHERE filename = :filename
                          ORDER BY read_at DESC
                          LIMIT :limit");
    $stmt->bindValue(':filename', $filename, PDO::PARAM_STR);
    $stmt->bindValue(':limit',    $limit,    PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Aktive Ausleihen (noch nicht zurückgegeben) für eine Datei
 */
function getAktiveAusleihen($filename) {
    $db = getBibliothekDB();
    if (!$db) return [];

    $stmt = $db->prepare("SELECT id, reader_name, read_at
                          FROM read_log
                          WHERE filename = :filename
                            AND zurueckgegeben_am IS NULL
                          ORDER BY read_at DESC");
    $stmt->execute([':filename' => $filename]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ausleihe als zurückgegeben markieren
 */
function zurueckgebenAusleihe($ausleiheId, $zurueckgebenVon = '') {
    $db = getBibliothekDB();
    if (!$db) return false;

    $stmt = $db->prepare("UPDATE read_log
                          SET zurueckgegeben_am = :am, zurueckgegeben_von = :von
                          WHERE id = :id AND zurueckgegeben_am IS NULL");
    return $stmt->execute([
        ':am'  => time(),
        ':von' => $zurueckgebenVon ?: null,
        ':id'  => (int)$ausleiheId,
    ]);
}

/**
 * Anzahl der Exemplare einer Datei setzen
 */
function setKopienAnzahl($filename, $kopien) {
    $kopien = max(1, (int)$kopien);
    return saveFileMetadata($filename, ['kopien' => $kopien]);
}

/**
 * Anzahl der Exemplare einer Datei lesen (Default: 1)
 */
function getKopienAnzahl($filename) {
    $meta = getFileMetadata($filename);
    return $meta ? max(1, (int)($meta['kopien'] ?? 1)) : 1;
}

// LEGACY: Alte Login-Funktion für Abwärtskompatibilität (nutzt jetzt Rollensystem)
// DEPRECATED: Verwende stattdessen loginUser()
function login($inputPassword) {
    // Migration: Versuche Login mit dem Standard-Meister-Account
    return loginUser('meister', $inputPassword) 
        ? ['success' => true, 'message' => 'Login erfolgreich!']
        : ['success' => false, 'message' => 'Falsches Zauberwort!'];
}

// LEGACY: Alte Logout-Funktion
// DEPRECATED: Verwende stattdessen logoutUser()
function logout() {
    logoutUser();
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📕', 'txt' => '📜', 'md' => '📝',
        'doc' => '📘', 'docx' => '📘', 'xls' => '📊', 'xlsx' => '📊',
        'zip' => '📦', 'rar' => '📦', 'mp4' => '🎬', 'mov' => '🎬',
        'jpg' => '🖼️', 'png' => '🖼️', 'jpeg' => '🖼️', 'gif' => '🖼️', 'webp' => '🖼️',
        'epub' => '📚'
    ];
    return $icons[$ext] ?? '📄';
}

// UPLOAD MIT VALIDIERUNG
function handleUpload($fileArray, $targetCategory = 'normal') {
    // Berechtigung prüfen: Upload-Recht für Bibliothek
    if (!hasPermission('bibliothek', 'upload')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }

    // 1. Dateigröße prüfen
    if ($fileArray['size'] > MAX_FILE_SIZE) {
        return ['type' => 'error', 'text' => '⚠️ Datei zu groß! Maximum: ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB'];
    }

    // 2. Extension prüfen
    $fileName = basename($fileArray['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['type' => 'error', 'text' => '⚠️ Dateityp nicht erlaubt! Nur: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }

    // 3. MIME-Type prüfen (zusätzliche Sicherheit)
    // FALLBACK: Falls finfo nicht verfügbar ist
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_MIMES)) {
            return ['type' => 'error', 'text' => '⚠️ Ungültiger Dateityp erkannt!'];
        }
    }

    // 4. Dateiname säubern
    $fileName = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $fileName);

    // 5. Duplikat-Schutz
    $targetDir = ($targetCategory === 'forbidden') ? FORBIDDEN_DIR : UPLOAD_DIR;
    $targetPath = $targetDir . $fileName;
    
    if (file_exists($targetPath)) {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $counter = 1;
        while (file_exists($targetDir . "{$name}_{$counter}.{$ext}")) {
            $counter++;
        }
        $fileName = "{$name}_{$counter}.{$ext}";
        $targetPath = $targetDir . $fileName;
    }

    // 6. Upload durchführen
    if (move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        // Metadaten in Bibliothek-DB speichern
        $uploadQuality = $_POST['upload_quality'] ?? null;
        $validQualities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
        if ($uploadQuality && !in_array($uploadQuality, $validQualities)) {
            $uploadQuality = null;
        }
        saveFileMetadata($fileName, [
            'category' => $targetCategory,
            'quality' => $uploadQuality,
            'uploaded_by' => $_SESSION['username'] ?? '',
            'uploaded_at' => time()
        ]);

        return ['type' => 'success', 'text' => "✅ Schriftrolle '{$fileName}' erfolgreich archiviert!"];
    } else {
        return ['type' => 'error', 'text' => '❌ Fehler beim Upload. Schreibrechte prüfen!'];
    }
}

// LÖSCHEN
function handleDelete($filename, $category) {
    // Berechtigung prüfen: Schreib-Recht für Bibliothek
    if (!hasPermission('bibliothek', 'write')) {
        return ['type' => 'error', 'text' => '🚫 Zugriff verweigert.'];
    }

    $targetDir = ($category === 'forbidden') ? FORBIDDEN_DIR : UPLOAD_DIR;
    $filePath = $targetDir . basename($filename);

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            // Metadaten aus Bibliothek-DB entfernen
            $db = getBibliothekDB();
            if ($db) {
                $stmt = $db->prepare("DELETE FROM file_metadata WHERE filename = :filename");
                $stmt->execute([':filename' => basename($filename)]);
                $stmt = $db->prepare("DELETE FROM read_log WHERE filename = :filename");
                $stmt->execute([':filename' => basename($filename)]);
            }
            return ['type' => 'success', 'text' => "🔥 '{$filename}' wurde verbrannt!"];
        }
    }
    return ['type' => 'error', 'text' => '❌ Datei nicht gefunden.'];
}

// DATEIEN LADEN
function getFiles($mode = 'normal', $searchQuery = '') {
    $dir = ($mode === 'forbidden') ? FORBIDDEN_DIR : UPLOAD_DIR;
    $webPath = ($mode === 'forbidden') ? 'uploads/verboten/' : 'uploads/';

    if (!is_dir($dir)) return [];
    
    $allFiles = scandir($dir);
    $results = [];

    foreach ($allFiles as $file) {
        // 1. Ignorierte Dateien überspringen
        if (in_array($file, IGNORE_FILES)) continue;

        // 2. Ordner ignorieren
        if (is_dir($dir . $file)) continue;

        // 3. Suche anwenden
        $match = true;
        if ($searchQuery !== '') {
            $match = false;
            if (stripos($file, $searchQuery) !== false) {
                $match = true;
            } elseif (preg_match('/\.(txt|md)$/i', $file)) {
                $content = @file_get_contents($dir . $file);
                if ($content && stripos($content, $searchQuery) !== false) {
                    $match = true;
                }
            }
        }

        if ($match) {
            $filePath = $dir . $file;
            $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
            $isForbidden = ($mode === 'forbidden');
            $meta = getFileMetadata($file);

            $results[] = [
                'name' => $file,
                'path' => $webPath . rawurlencode($file),
                'icon' => getFileIcon($file),
                'is_image' => $isImage,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'quality' => getItemQuality($file, $isForbidden),
                'description' => $meta['description'] ?? '',
                'uploaded_by' => $meta['uploaded_by'] ?? '',
                'last_read_by' => $meta['last_read_by'] ?? '',
                'last_read_at' => $meta['last_read_at'] ?? null,
                'quality_manual' => ($meta && $meta['quality'] !== null && $meta['quality'] !== '')
            ];
        }
    }
    return $results;
}

// KATEGORIEÜBERGREIFENDE SUCHE
function getAllFiles($searchQuery = '') {
    $normalFiles = getFiles('normal', $searchQuery);
    $forbiddenFiles = getFiles('forbidden', $searchQuery);
    
    // Markiere die Herkunft
    foreach ($normalFiles as &$file) {
        $file['category'] = 'normal';
        $file['category_label'] = '📚 Normal';
    }
    foreach ($forbiddenFiles as &$file) {
        $file['category'] = 'forbidden';
        $file['category_label'] = '⛔ Verboten';
    }
    
    return array_merge($normalFiles, $forbiddenFiles);
}

// HELPER: Dateigröße formatieren
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// HELPER: Datum formatieren
function formatDate($timestamp) {
    return date('d.m.Y H:i', $timestamp);
}

// ============================================
// RICH TEXT PARSER (Markdown-Style)
// ============================================

/**
 * Vollständiger Markdown-Parser (XSS-sicher, serverseitig)
 * Unterstützt: Überschriften, **fett**, *kursiv*, ~~durchgestrichen~~,
 * `inline-code`, Codeblöcke (```lang), Tabellen, Listen (ul/ol,
 * verschachtelt, Checkboxen), Blockquotes, Links, Bilder, HR,
 * Fußnoten, numerierte Listen
 */
function parseRichText($text) {
    // --- 1. Zeilenenden normalisieren ---
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // --- 2. Codeblöcke extrahieren (VOR Escaping – Inhalt schützen) ---
    $codeBlocks  = [];
    $inlineCodes = [];
    $cbIdx = 0;
    $icIdx = 0;

    $text = preg_replace_callback(
        '/```(\w*)\n?([\s\S]*?)```/m',
        function ($m) use (&$codeBlocks, &$cbIdx) {
            $lang  = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $inner = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $label = $lang ? '<span class="md-code-lang">' . $lang . '</span>' : '';
            $codeBlocks[$cbIdx] = '<pre class="md-code-block">' . $label . '<code>' . $inner . '</code></pre>';
            $token = "\x00CB{$cbIdx}\x00";
            $cbIdx++;
            return $token;
        },
        $text
    );

    $text = preg_replace_callback(
        '/`([^`\n]+)`/',
        function ($m) use (&$inlineCodes, &$icIdx) {
            $inlineCodes[$icIdx] = '<code class="md-inline-code">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            $token = "\x00IC{$icIdx}\x00";
            $icIdx++;
            return $token;
        },
        $text
    );

    // --- 3. Fußnoten-Definitionen sammeln (vor Escaping) ---
    $footnotes = [];
    $text = preg_replace_callback(
        '/^\[\^([^\]]+)\]:\s*(.+)$/m',
        function ($m) use (&$footnotes) {
            $footnotes[$m[1]] = $m[2]; // Wird später escaped
            return '';
        },
        $text
    );

    // --- 4. HTML escapen (jetzt sicher: Code-Tokens sind drin) ---
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // --- 5. Überschriften (# bis ######) ---
    $text = preg_replace_callback(
        '/^(#{1,6})\s+(.+)$/m',
        function ($m) {
            $lvl = strlen($m[1]);
            return '<h' . $lvl . ' class="md-h' . $lvl . '">' . $m[2] . '</h' . $lvl . '>';
        },
        $text
    );

    // --- 6. Blockquotes (&gt; durch htmlspecialchars) ---
    $text = preg_replace_callback(
        '/(^&gt;\s?.+\n?)+/m',
        function ($m) {
            $inner = preg_replace('/^&gt;\s?/m', '', $m[0]);
            return '<blockquote class="md-blockquote">' . trim($inner) . '</blockquote>';
        },
        $text
    );

    // --- 7. Horizontale Linie ---
    $text = preg_replace('/^([-_*]){3,}\s*$/m', '<hr class="md-hr">', $text);

    // --- 8. Tabellen ---
    $text = preg_replace_callback(
        '/(^\|.+\|\n)(^\|[-| :]+\|\n)((?:^\|.+\|\n?)*)/m',
        function ($m) {
            $parseRow = function ($line) {
                $cells = explode('|', trim(trim($line), '|'));
                return array_map('trim', $cells);
            };
            $headers = $parseRow($m[1]);
            $rowLines = array_filter(explode("\n", trim($m[3])));
            $html = '<table class="md-table"><thead><tr>';
            foreach ($headers as $h) {
                $html .= '<th>' . $h . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rowLines as $row) {
                if (trim($row) === '') continue;
                $cells = $parseRow($row);
                $html .= '<tr>';
                foreach ($cells as $c) {
                    $html .= '<td>' . $c . '</td>';
                }
                $html .= '</tr>';
            }
            return $html . '</tbody></table>';
        },
        $text
    );

    // --- 9. Verschachtelte Listen (ul/ol/Checkboxen) ---
    $text = _mdParseLists($text);

    // --- 10. Inline-Formatierung ---
    $text = preg_replace('/~~(.+?)~~/s',            '<del>$1</del>',                 $text);
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/s',   '<strong><em>$1</em></strong>',   $text);
    $text = preg_replace('/\*\*(.+?)\*\*/s',        '<strong>$1</strong>',            $text);
    $text = preg_replace('/__(.+?)__/s',             '<strong>$1</strong>',            $text);
    $text = preg_replace('/\*(.+?)\*/s',             '<em>$1</em>',                   $text);
    $text = preg_replace('/_(.+?)_/s',              '<em>$1</em>',                   $text);
    $text = preg_replace('/\^(.+?)\^/',             '<sup>$1</sup>',                 $text);

    // --- 11. Fußnoten-Referenzen ---
    $fnCounter = 0;
    $text = preg_replace_callback(
        '/\[\^([^\]]+)\]/',
        function ($m) use (&$fnCounter) {
            $fnCounter++;
            $label = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            return '<sup class="md-fn-ref"><a href="#fn-' . $label . '" id="fnref-' . $label . '">[' . $fnCounter . ']</a></sup>';
        },
        $text
    );

    // --- 12. Links und Bilder ---
    // Bilder: ![alt](url) — nur http(s) + relative Pfade
    $text = preg_replace_callback(
        '/!\[([^\]]*)\]\(((?:https?:\/\/|\.\.?\/)[^\)]+)\)/',
        function ($m) {
            $alt = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<img src="' . $url . '" alt="' . $alt . '" class="md-img">';
        },
        $text
    );
    // Links: [text](url)
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(((?:https?:\/\/|\.\.?\/)[^\)]+)\)/',
        function ($m) {
            $label = $m[1];
            $url   = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="md-link">' . $label . '</a>';
        },
        $text
    );

    // --- 13. Paragraphen ---
    $blocks = preg_split('/\n{2,}/', $text);
    $out = [];
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        // Block-Elemente nicht nochmal wrappen
        if (preg_match('/^<(h[1-6]|ul|ol|li|table|blockquote|pre|hr|div|footer)[\s>]/', $block)
            || strpos($block, "\x00CB") !== false) {
            $out[] = $block;
        } else {
            $out[] = '<p class="md-p">' . str_replace("\n", '<br>', $block) . '</p>';
        }
    }
    $text = implode("\n", $out);

    // --- 14. Fußnoten-Abschnitt am Ende ---
    if (!empty($footnotes)) {
        $fnHtml = '<footer class="md-footnotes"><ol>';
        foreach ($footnotes as $id => $def) {
            $label = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
            $fnHtml .= '<li id="fn-' . $label . '">' . htmlspecialchars($def, ENT_QUOTES, 'UTF-8')
                     . ' <a href="#fnref-' . $label . '">↩</a></li>';
        }
        $fnHtml .= '</ol></footer>';
        $text .= "\n" . $fnHtml;
    }

    // --- 15. Platzhalter wiederherstellen ---
    $text = preg_replace_callback('/\x00CB(\d+)\x00/', function ($m) use ($codeBlocks) {
        return $codeBlocks[(int)$m[1]] ?? '';
    }, $text);
    $text = preg_replace_callback('/\x00IC(\d+)\x00/', function ($m) use ($inlineCodes) {
        return $inlineCodes[(int)$m[1]] ?? '';
    }, $text);

    return $text;
}

/**
 * Hilfsfunktion: Verschachtelte Markdown-Listen verarbeiten
 * Unterstützt: ul (- / *), ol (1.), Checkboxen (- [ ] / - [x])
 * Wird intern von parseRichText() aufgerufen.
 */
function _mdParseLists($text) {
    $lines  = explode("\n", $text);
    $result = [];
    $stack  = []; // ['type' => 'ul'|'ol', 'indent' => int]

    $closeUntil = function ($indent) use (&$stack, &$result) {
        while (!empty($stack) && end($stack)['indent'] >= $indent) {
            $top = array_pop($stack);
            $result[] = '</' . $top['type'] . '>';
        }
    };

    foreach ($lines as $line) {
        // Erkennt: "  - item", "  * item", "  1. item",
        //          "  - [ ] item", "  - [x] item"
        if (preg_match('/^( *)(?:(\d+)\.\s+|[-*]\s+(?:\[([ xX])\]\s+)?)(.+)$/', $line, $m)) {
            $rawIndent = strlen($m[1]);
            $isOrdered = ($m[2] !== '');
            $listType  = $isOrdered ? 'ol' : 'ul';
            $checked   = isset($m[3]) && $m[3] !== '' ? strtolower($m[3]) : null;
            $itemText  = $m[4];

            // Gleiche Tiefe + anderer Listentyp → aktuelle schließen
            if (!empty($stack) && end($stack)['indent'] === $rawIndent
                && end($stack)['type'] !== $listType) {
                $top = array_pop($stack);
                $result[] = '</' . $top['type'] . '>';
            }

            // Neue/tiefere Liste öffnen
            if (empty($stack) || end($stack)['indent'] < $rawIndent) {
                $result[] = '<' . $listType . ' class="md-' . $listType . '">';
                $stack[]  = ['type' => $listType, 'indent' => $rawIndent];
            }

            // Flachere Einrückung → Listen schließen
            if (!empty($stack) && end($stack)['indent'] > $rawIndent) {
                $closeUntil($rawIndent);
                if (empty($stack) || end($stack)['indent'] !== $rawIndent) {
                    $result[] = '<' . $listType . ' class="md-' . $listType . '">';
                    $stack[]  = ['type' => $listType, 'indent' => $rawIndent];
                }
            }

            // Checkbox-Item
            if ($checked !== null) {
                $chkAttr = ($checked === 'x') ? ' checked disabled' : ' disabled';
                $itemHtml = '<input type="checkbox"' . $chkAttr . '> ' . $itemText;
            } else {
                $itemHtml = $itemText;
            }
            $result[] = '<li class="md-li">' . $itemHtml . '</li>';

        } else {
            // Kein Listen-Element → alle offenen Listen schließen
            $closeUntil(-1);
            $result[] = $line;
        }
    }
    $closeUntil(-1);

    return implode("\n", $result);
}

/**
 * Einfachere Version: Nur Fett/Kursiv ohne Listen
 * Für kurze Texte wie Titel
 */
function parseRichTextSimple($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
    return $text;
}

/**
 * Leichter BBCode-Parser für sichere Bibliothek-/Miliz-Anzeige
 */
function parseBBCodeSimple($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $patterns = [
        '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
        '/\[s\](.*?)\[\/s\]/is' => '<del>$1</del>',
        '/\[quote\](.*?)\[\/quote\]/is' => '<blockquote>$1</blockquote>',
        '/\[code\](.*?)\[\/code\]/is' => '<pre><code>$1</code></pre>',
        '/\[url=(https?:\/\/[^\]\s]+)\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>',
        '/\[url\](https?:\/\/[^\[]+)\[\/url\]/is' => '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }

    $lines = explode("\n", $text);
    $inList = false;
    $result = [];

    foreach ($lines as $line) {
        if (preg_match('/^\[\*\]\s*(.+)$/', trim($line), $matches)) {
            if (!$inList) {
                $result[] = '<ul>';
                $inList = true;
            }
            $result[] = '<li>' . $matches[1] . '</li>';
        } else {
            if ($inList) {
                $result[] = '</ul>';
                $inList = false;
            }
            $result[] = $line;
        }
    }

    if ($inList) {
        $result[] = '</ul>';
    }

    return nl2br(implode("\n", $result));
}

/**
 * HTML Sanitizer - Erlaubt nur sichere HTML-Tags, blockiert JavaScript
 * Whitelist-Ansatz: Nur explizit erlaubte Tags werden durchgelassen
 */
function sanitizeHTML($html) {
    // Erlaubte Tags (Design-Tags, keine Scripte/Iframes/Forms)
    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'div', 'span', 'blockquote', 'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'hr', 'pre', 'code', 'small', 'mark', 'del', 'ins', 'sub', 'sup'
    ];
    
    // Strip alle Tags außer den erlaubten
    $html = strip_tags($html, '<' . implode('><', $allowedTags) . '>');
    
    // Entferne gefährliche Attribute
    // Erlaubt nur: style, href, src, alt, title, class
    $html = preg_replace_callback(
        '/<([a-z][a-z0-9]*)([^>]*)>/i',
        function($matches) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];
            
            // Entferne alle Event-Handler (onclick, onload, onerror, etc.)
            $attrs = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\'>]*["\']?/i', '', $attrs);
            
            // Entferne javascript: Links
            $attrs = preg_replace('/href\s*=\s*["\']?javascript:[^"\'>]*["\']?/i', '', $attrs);
            $attrs = preg_replace('/src\s*=\s*["\']?javascript:[^"\'>]*["\']?/i', '', $attrs);
            
            // Entferne data: URIs (können für XSS genutzt werden)
            $attrs = preg_replace('/href\s*=\s*["\']?data:[^"\'>]*["\']?/i', '', $attrs);
            $attrs = preg_replace('/src\s*=\s*["\']?data:[^"\'>]*["\']?/i', '', $attrs);
            
            // Sichere Attribute filtern (whitelist)
            $safeAttrs = [];
            if (preg_match_all('/(style|href|src|alt|title|class)\s*=\s*["\']([^"\'>]*)["\']?/i', $attrs, $attrMatches, PREG_SET_ORDER)) {
                foreach ($attrMatches as $attr) {
                    $attrName = strtolower($attr[1]);
                    $attrValue = $attr[2];
                    
                    // Spezielle Checks
                    if ($attrName === 'style') {
                        // Entferne gefährliche CSS (expression, behavior, -moz-binding)
                        $attrValue = preg_replace('/expression\s*\(/i', '', $attrValue);
                        $attrValue = preg_replace('/behavior\s*:/i', '', $attrValue);
                        $attrValue = preg_replace('/-moz-binding\s*:/i', '', $attrValue);
                    }
                    
                    if ($attrName === 'href' && $tag === 'a') {
                        // Nur http(s) und mailto Links erlauben
                        if (!preg_match('/^(https?:\/\/|mailto:)/i', $attrValue)) {
                            continue; // Skip dieses Attribut
                        }
                    }
                    
                    $safeAttrs[] = $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            
            $safeAttrsStr = !empty($safeAttrs) ? ' ' . implode(' ', $safeAttrs) : '';
            return '<' . $tag . $safeAttrsStr . '>';
        },
        $html
    );
    
    return $html;
}
?>

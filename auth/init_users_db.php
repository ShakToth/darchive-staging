<?php
// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Datenbank-Initialisierung für das Rollensystem
$dbPath = __DIR__ . '/users.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');

    // 1. Rollen-Tabelle erstellen
    $db->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            display_name TEXT NOT NULL,
            icon TEXT DEFAULT '',
            color TEXT DEFAULT '#9d9d9d',
            is_system INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Standard-Rollen seeden (falls noch nicht vorhanden)
    $roleCheck = $db->query("SELECT COUNT(*) FROM roles");
    if ($roleCheck->fetchColumn() == 0) {
        $db->exec("
            INSERT INTO roles (name, display_name, icon, color, is_system) VALUES
                ('meister', 'Meister', '👑', '#ff8000', 1),
                ('bibliothekar', 'Bibliothekar', '📚', '#a335ee', 1),
                ('miliz', 'Miliz', '⚔️', '#0070dd', 1),
                ('buerger', 'Bürger', '👤', '#9d9d9d', 1)
        ");
        echo "✅ Standard-Rollen erstellt.<br>";
    }

    // 2. Berechtigungs-Tabelle erstellen
    $db->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
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

    // Standard-Berechtigungen seeden (falls noch nicht vorhanden)
    $permCheck = $db->query("SELECT COUNT(*) FROM role_permissions");
    if ($permCheck->fetchColumn() == 0) {
        $defaultPerms = [
            ['meister', 'bibliothek', 1, 1, 1],
            ['meister', 'miliz', 1, 1, 1],
            ['meister', 'aushaenge', 1, 1, 1],
            ['meister', 'verwaltung', 1, 1, 1],
            ['bibliothekar', 'bibliothek', 1, 1, 1],
            ['bibliothekar', 'miliz', 1, 0, 0],
            ['bibliothekar', 'aushaenge', 1, 1, 0],
            ['bibliothekar', 'verwaltung', 0, 0, 0],
            ['miliz', 'bibliothek', 1, 0, 0],
            ['miliz', 'miliz', 1, 1, 1],
            ['miliz', 'aushaenge', 1, 1, 0],
            ['miliz', 'verwaltung', 0, 0, 0],
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
        echo "✅ Standard-Berechtigungen erstellt.<br>";
    }

    // 3. Benutzer-Tabelle erstellen (ohne CHECK-Constraint, FK auf roles)
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME,
            FOREIGN KEY (role) REFERENCES roles(name)
        )
    ");

    // Standard-Meister-Account erstellen (falls noch nicht vorhanden)
    // WICHTIG: Nach dem ersten Login dieses Passwort ändern!
    $defaultUsername = 'meister';
    $defaultPassword = 'DH2025!Change'; // UNBEDINGT ÄNDERN!
    $defaultPasswordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->execute([':username' => $defaultUsername]);

    if ($stmt->fetchColumn() == 0) {
        $insertStmt = $db->prepare("
            INSERT INTO users (username, password_hash, role)
            VALUES (:username, :password_hash, :role)
        ");
        $insertStmt->execute([
            ':username' => $defaultUsername,
            ':password_hash' => $defaultPasswordHash,
            ':role' => 'meister'
        ]);
        echo "✅ Standard-Meister-Account erstellt: Username: 'meister' | Passwort: 'DH2025!Change'<br>";
        echo "⚠️ <strong>BITTE SOFORT ÄNDERN!</strong><br>";
    } else {
        echo "ℹ️ Benutzer-Datenbank bereits initialisiert.<br>";
    }

    echo "✅ Datenbank erfolgreich initialisiert!";

} catch (PDOException $e) {
    die("❌ Fehler bei der Datenbank-Initialisierung: " . $e->getMessage());
}
?>

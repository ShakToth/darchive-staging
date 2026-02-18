<?php
// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Debug-Modus (später entfernen)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Functions laden
require_once 'functions.php';

$error = '';
$success = '';

// WICHTIG: Datenbank-Prüfung VOR allen anderen Checks
$dbPath = __DIR__ . '/auth/users.db';
$dbExists = file_exists($dbPath);

if (!$dbExists) {
    $error = '⚠️ Datenbank nicht initialisiert! Bitte zuerst <a href="auth/init_users_db.php" style="color: #d4af37; text-decoration: underline; font-weight: bold;">hier klicken</a> um die Datenbank zu erstellen.';
} else {
    // Nur wenn DB existiert, können wir Login-Checks machen
    
    // Wenn bereits eingeloggt, zur Startseite weiterleiten
    if (isLoggedIn()) {
        header('Location: index.php');
        exit;
    }

    // Login-Verarbeitung
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if (verifyCSRFToken($_POST['csrf_token'])) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (loginUser($username, $password)) {
                header('Location: index.php');
                exit;
            } else {
                $error = 'Ungültiger Benutzername oder Passwort.';
            }
        } else {
            $error = 'Ungültiges Sicherheitstoken.';
        }
    }
}

$csrfToken = getCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dämmerhafen - Anmeldung</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        
        .login-container {
            background: var(--bg-parchment);
            border: 3px solid var(--accent-gold);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.7);
            min-width: 400px;
            max-width: 90%;
            text-align: center;
        }
        
        .login-container h1 {
            font-family: var(--font-elegant);
            color: var(--accent-gold);
            margin-bottom: 10px;
            margin-top: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            font-size: 2.5rem;
        }
        
        .login-subtitle {
            font-family: var(--font-body);
            color: var(--text-ink);
            margin-bottom: 25px;
            font-style: italic;
        }
        
        .login-wappen {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
        }
        
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 2px solid var(--accent-bronze);
            background: rgba(255,255,255,0.9);
            font-family: var(--font-body);
            font-size: 16px;
            border-radius: 5px;
            box-sizing: border-box;
        }
        
        .login-form input:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 5px rgba(212, 175, 55, 0.5);
        }
        
        .login-form button {
            margin-top: 20px;
            padding: 12px 40px;
            background: var(--accent-gold);
            color: var(--text-dark);
            border: none;
            font-family: var(--font-elegant);
            font-size: 18px;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .login-form button:hover {
            background: var(--accent-bronze);
            transform: scale(1.02);
        }
        
        .login-form button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: rgba(200,0,0,0.2);
            border: 2px solid darkred;
            color: darkred;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
            line-height: 1.5;
        }
        
        .error-message a {
            color: #d4af37;
            text-decoration: underline;
        }
        
        .login-hint {
            margin-top: 30px;
            font-size: 0.85rem;
            color: #666;
            padding: 15px;
            background: rgba(0,0,0,0.05);
            border-radius: 5px;
        }
        
        .login-hint code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .login-hint strong {
            color: darkred;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; min-height: 100vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); display: flex; align-items: center; justify-content: center; font-family: var(--font-body);">
    
    <div class="login-container">
        <img src="wappen.png" alt="Wappen" class="login-wappen">
        
        <h1>🏰 Dämmerhafen</h1>
        <p class="login-subtitle">
            Identifiziert Euch, werter Besucher.
        </p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <input type="text" name="username" placeholder="Benutzername" required autofocus <?php echo !$dbExists ? 'disabled' : ''; ?>>
            <input type="password" name="password" placeholder="Passwort" required <?php echo !$dbExists ? 'disabled' : ''; ?>>
            
            <button type="submit" name="login" <?php echo !$dbExists ? 'disabled' : ''; ?>>
                ⚔️ Eintreten
            </button>
        </form>
        
        <?php if ($dbExists): ?>
        <div class="login-hint">
            Standard-Login: <code>meister</code> / <code>DH2025!Change</code><br>
            <strong>⚠️ Bitte nach erstem Login ändern!</strong>
        </div>
        <?php endif; ?>
    </div>
    
</body>
</html>

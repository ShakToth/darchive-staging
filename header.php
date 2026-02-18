<?php
// DIREKTER AUFRUF VERBOTEN
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Direct access to this file is not allowed.</p></body></html>');
}

// SICHERHEITS-HEADER
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Functions einbinden
require_once __DIR__ . '/functions.php';

// Optional: Zusätzliche Functions je nach Seite
if (isset($includeAushaenge) && $includeAushaenge) {
    require_once __DIR__ . '/functions_aushaenge.php';
}
if (isset($includeMiliz) && $includeMiliz) {
    require_once __DIR__ . '/functions_miliz.php';
}

// --- GLOBALES LOGIN/LOGOUT HANDLING ---
// Bewahre $message falls von der Seite gesetzt (z.B. miliz.php), sonst null
$message = $message ?? null;

// Login-Handling
if (isset($_POST['header_login']) && isset($_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $username = trim($_POST['header_username'] ?? '');
        $password = $_POST['header_password'] ?? '';
        
        if (loginUser($username, $password)) {
            $currentPage = basename($_SERVER['PHP_SELF']);
            $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
            header("Location: {$currentPage}{$queryString}");
            exit;
        } else {
            $message = ['type' => 'error', 'text' => 'Ungültiger Benutzername oder Passwort!'];
        }
    } else {
        $message = ['type' => 'error', 'text' => '🚫 Ungültige Anfrage (CSRF)!'];
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header("Location: index.php");
    exit;
}

$csrfToken = generateCSRFToken();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($currentPage === 'index.php'): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <title><?php echo $pageTitle ?? 'Dämmerhafen'; ?></title>
    <link rel="stylesheet" href="style.css?v2.3=<?php echo filemtime('style.css'); ?>">
    <?php if (file_exists(__DIR__ . '/dist/app.css')): ?>
    <link rel="stylesheet" href="dist/app.css?v=<?php echo filemtime(__DIR__ . '/dist/app.css'); ?>">
    <?php endif; ?>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
</head>
<body class="<?php echo $bodyClass ?? ''; ?>">

<?php if (!isset($hideTopNav) || !$hideTopNav): ?>
<!-- TOP NAVIGATION -->
<div class="top-nav">
    <div class="nav-left">
        <a href="index.php" class="nav-wappen" <?php if($currentPage === 'index.php') echo 'style="color: var(--accent-gold);"'; ?>>
            <img src="wappen.png" alt="" style="height: 35px; margin-right: 10px;">
            <span style="font-family: var(--font-heading); font-size: 1.3rem;">Dämmerhafen</span>
        </a>
    </div>
    
    <!-- DESKTOP NAVIGATION -->
    <div class="nav-center nav-desktop-only">
        <a href="bibliothek.php" class="nav-link" <?php if($currentPage === 'bibliothek.php') echo 'style="color: var(--accent-gold);"'; ?>>Die Bibliothek</a>
        <a href="miliz.php" class="nav-link" <?php if($currentPage === 'miliz.php') echo 'style="color: var(--accent-gold);"'; ?>>Die Miliz</a>
        <a href="verwaltung.php" class="nav-link" <?php if($currentPage === 'verwaltung.php') echo 'style="color: var(--accent-gold);"'; ?>>Die Verwaltung</a>
        <a href="aushaenge.php" class="nav-link" <?php if($currentPage === 'aushaenge.php') echo 'style="color: var(--accent-gold);"'; ?>>Aushänge</a>
    </div>
    
    <!-- DESKTOP LOGIN/LOGOUT -->
    <div class="nav-right nav-desktop-only">
        <?php if (isLoggedIn()): ?>
            <span class="admin-badge">🔑 <?php echo htmlspecialchars(getUsername()); ?> (<?php echo htmlspecialchars($_SESSION['role_icon'] ?? '') . ' ' . htmlspecialchars($_SESSION['role_display'] ?? getUserRole()); ?>)</span>
            <?php if (isMeister()): ?>
                <a href="admin.php" class="nav-btn" style="text-decoration:none; margin: 0 10px;" title="Administration">⚙️</a>
            <?php endif; ?>
            <a href="?action=logout" class="btn-logout">Abmelden</a>
        <?php else: ?>
            <a href="login.php" class="nav-btn" style="text-decoration:none;">Anmelden</a>
        <?php endif; ?>
    </div>
    
    <!-- MOBILE HAMBURGER BUTTON -->
    <div class="nav-mobile-burger">
        <button class="burger-btn" onclick="toggleMobileMenu()" aria-label="Menu">
            <span class="burger-line"></span>
            <span class="burger-line"></span>
            <span class="burger-line"></span>
        </button>
    </div>
</div>

<!-- MOBILE MENU OVERLAY -->
<div id="mobileMenuOverlay" class="mobile-menu-overlay">
    <div class="mobile-menu-content">
        <button class="mobile-menu-close" onclick="toggleMobileMenu()">&times;</button>
        
        <div class="mobile-menu-header">
            <img src="wappen.png" alt="Dämmerhafen" style="height: 60px; margin-bottom: 10px;">
            <h2 style="font-family: var(--font-heading); color: var(--accent-gold); margin: 0;">Dämmerhafen</h2>
        </div>
        
        <nav class="mobile-menu-nav">
            <a href="bibliothek.php" class="mobile-menu-link" <?php if($currentPage === 'bibliothek.php') echo 'style="color: var(--accent-gold);"'; ?>>
                <span class="mobile-menu-icon">📚</span>
                Die Bibliothek
            </a>
            <a href="miliz.php" class="mobile-menu-link" <?php if($currentPage === 'miliz.php') echo 'style="color: var(--accent-gold);"'; ?>>
                <span class="mobile-menu-icon">⚔️</span>
                Die Miliz
            </a>
            <a href="verwaltung.php" class="mobile-menu-link" <?php if($currentPage === 'verwaltung.php') echo 'style="color: var(--accent-gold);"'; ?>>
                <span class="mobile-menu-icon">📋</span>
                Die Verwaltung
            </a>
            <a href="aushaenge.php" class="mobile-menu-link" <?php if($currentPage === 'aushaenge.php') echo 'style="color: var(--accent-gold);"'; ?>>
                <span class="mobile-menu-icon">📌</span>
                Aushänge
            </a>
        </nav>
        
        <div class="mobile-menu-auth">
            <?php if (isLoggedIn()): ?>
                <div style="text-align: center; padding: 15px; background: rgba(40, 167, 69, 0.1); border-radius: 8px; margin-bottom: 15px;">
                    <span class="admin-badge" style="font-size: 1.1rem;">🔑 <?php echo htmlspecialchars(getUsername()); ?></span>
                    <div style="font-size: 0.85rem; color: #666; margin-top: 5px;">Rolle: <?php echo htmlspecialchars(($_SESSION['role_icon'] ?? '') . ' ' . ($_SESSION['role_display'] ?? getUserRole())); ?></div>
                </div>
                <?php if (isMeister()): ?>
                    <a href="admin.php" class="nav-btn" style="width: 100%; margin-bottom: 10px; text-decoration: none; display: block; text-align: center;">⚙️ Administration</a>
                <?php endif; ?>
                <a href="?action=logout" class="mobile-menu-logout">Abmelden</a>
            <?php else: ?>
                <a href="login.php" class="nav-btn" style="width: 100%; text-decoration: none; display: block; text-align: center;">Anmelden</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMobileMenu() {
    const overlay = document.getElementById('mobileMenuOverlay');
    const body = document.body;
    
    if (overlay.classList.contains('active')) {
        overlay.classList.remove('active');
        body.style.overflow = '';
    } else {
        overlay.classList.add('active');
        body.style.overflow = 'hidden';
    }
}

// Schließe Menu bei ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('mobileMenuOverlay');
        if (overlay.classList.contains('active')) {
            toggleMobileMenu();
        }
    }
});

// Schließe Menu beim Klicken außerhalb
document.getElementById('mobileMenuOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) {
        toggleMobileMenu();
    }
});
</script>
<?php endif; ?>

<?php if ($message): ?>
    <?php if (file_exists(__DIR__ . '/dist/app.js')): ?>
    <!-- Vue-Insel: Toast-Nachricht -->
    <div data-vue-island="ToastNachricht"
         data-props='<?php echo htmlspecialchars(json_encode($message, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>'>
    </div>
    <?php else: ?>
    <!-- Fallback ohne Vue -->
    <div id="headerMessage" style="position:fixed; top:80px; left:50%; transform:translateX(-50%);
         z-index:9999; max-width:600px; transition: opacity 0.8s ease;"
         class="msg <?php echo $message['type']; ?>">
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
    <script>
        (function() {
            var el = document.getElementById('headerMessage');
            if (!el) return;
            setTimeout(function() { el.style.opacity = '0'; }, 2500);
            setTimeout(function() { if (el && el.parentNode) el.parentNode.removeChild(el); }, 3500);
        })();
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php
// SICHERHEITS-HEADER
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once 'functions.php';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dämmerhafen</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body class="rp-view-room">

<!-- TOP NAVIGATION -->
<div class="top-nav">
    <div class="nav-left">
        <a href="daemmerhafen.php" class="nav-wappen" style="color: var(--accent-gold);">
            <img src="wappen.png" alt="" style="height: 35px; margin-right: 10px;">
            <span style="font-family: var(--font-heading); font-size: 1.3rem;">Dämmerhafen</span>
        </a>
    </div>
    
    <!-- DESKTOP NAVIGATION -->
    <div class="nav-center nav-desktop-only">
        <a href="index.php" class="nav-link">Die Bibliothek</a>
        <a href="miliz.php" class="nav-link">Die Miliz</a>
        <a href="verwaltung.php" class="nav-link">Die Verwaltung</a>
        <a href="aushaenge.php" class="nav-link">Aushänge</a>
    </div>
    
    <!-- DESKTOP LOGIN/LOGOUT -->
    <div class="nav-right nav-desktop-only">
        <?php if (isAdmin()): ?>
            <span class="admin-badge">🔒 Meister</span>
            <a href="index.php?action=logout" class="btn-logout">Abmelden</a>
        <?php else: ?>
            <form method="post" action="index.php" style="margin:0; display:flex; gap:10px; align-items:center;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="password" name="login_pw" placeholder="Zauberwort..." class="nav-input">
                <button type="submit" class="nav-btn">Login</button>
            </form>
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
            <a href="index.php" class="mobile-menu-link">
                <span class="mobile-menu-icon">📚</span>
                Die Bibliothek
            </a>
            <a href="miliz.php" class="mobile-menu-link">
                <span class="mobile-menu-icon">⚔️</span>
                Die Miliz
            </a>
            <a href="verwaltung.php" class="mobile-menu-link">
                <span class="mobile-menu-icon">📋</span>
                Die Verwaltung
            </a>
            <a href="aushaenge.php" class="mobile-menu-link">
                <span class="mobile-menu-icon">📌</span>
                Aushänge
            </a>
        </nav>
        
        <div class="mobile-menu-auth">
            <?php if (isAdmin()): ?>
                <div style="text-align: center; padding: 15px; background: rgba(40, 167, 69, 0.1); border-radius: 8px; margin-bottom: 15px;">
                    <span class="admin-badge" style="font-size: 1.1rem;">🔒 Meister</span>
                </div>
                <a href="index.php?action=logout" class="mobile-menu-logout">Abmelden</a>
            <?php else: ?>
                <form method="post" action="index.php" class="mobile-menu-login">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="password" name="login_pw" placeholder="Zauberwort..." class="nav-input" style="width: 100%; margin-bottom: 10px;">
                    <button type="submit" class="nav-btn" style="width: 100%;">Login</button>
                </form>
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

<!-- VOLLBILD HINTERGRUNDBILD -->
<img src="dammerhafen.jpg" alt="Dämmerhafen" class="rp-bg-fullscreen">

<!-- OPTIONALE HOTSPOTS FÜR SPÄTERE ERWEITERUNGEN -->
<!-- Hier kannst du später klickbare Bereiche hinzufügen -->

</body>
</html>

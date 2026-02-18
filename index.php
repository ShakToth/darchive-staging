<?php
$pageTitle = 'Dämmerhafen - Portal';
$bodyClass = 'rp-view-room';
require_once 'header.php';
?>

<!-- FULLSCREEN BACKGROUND -->
<img src="dammerhafen.jpg" alt="Dämmerhafen" class="rp-bg-fullscreen">

<!-- BOTTOM NAVIGATION -->
<div class="miliz-bottom-nav">
    <a href="bibliothek.php" class="miliz-nav-btn">
        <span class="miliz-nav-icon">📚</span>
        <span class="miliz-nav-label">Die Bibliothek</span>
    </a>
    
    <a href="miliz.php" class="miliz-nav-btn">
        <span class="miliz-nav-icon">⚔️</span>
        <span class="miliz-nav-label">Die Miliz</span>
    </a>
    
    <a href="verwaltung.php" class="miliz-nav-btn">
        <span class="miliz-nav-icon">📋</span>
        <span class="miliz-nav-label">Die Verwaltung</span>
    </a>
    
    <a href="aushaenge.php" class="miliz-nav-btn">
        <span class="miliz-nav-icon">📌</span>
        <span class="miliz-nav-label">Aushänge</span>
    </a>
</div>

<?php require_once 'footer.php'; ?>

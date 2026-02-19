<?php
$rawView = isset($_GET['cat']) ? trim((string)$_GET['cat']) : 'room';
$viewAliases = [
    'room'       => 'room',
    'bibliothek' => 'room',
    'images'     => 'images',
    'image'      => 'images',
    'bilder'     => 'images',
    'zeichnungen'=> 'images',
    'books'      => 'books',
    'book'       => 'books',
    'buecher'    => 'books',
    'bücher'     => 'books',
    'archive'    => 'archive',
    'archiv'     => 'archive',
    'index'      => 'index',
    'alle'       => 'index',
    'forbidden'  => 'forbidden',
    'verboten'   => 'forbidden'
];
$view = $viewAliases[strtolower($rawView)] ?? 'index';
$pageTitle = 'Die Bibliothek';
$bodyClass = ($view === 'room') ? 'rp-view-room' : '';

require_once 'functions.php';

// --- POST-HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'], $_POST['delete_cat'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'write')) {
        $category = ($_POST['delete_cat'] === 'forbidden') ? 'forbidden' : 'normal';
        $message = handleDelete($_POST['delete_file'], $category);
    } else {
        $message = ['type' => 'error', 'text' => '🚫 Ungültige Anfrage!'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'upload')) {
        $target  = $_POST['target_cat'] ?? 'normal';
        $message = handleUpload($_FILES['file'], $target);
    } else {
        $message = ['type' => 'error', 'text' => '🚫 Ungültige Anfrage (CSRF)!'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_quality'], $_POST['quality_file'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'write')) {
        $qualityFile = basename($_POST['quality_file']);
        $newQuality  = trim((string)$_POST['set_quality']);
        if ($newQuality === '' || $newQuality === 'auto') $newQuality = null;
        if (setFileQuality($qualityFile, $newQuality)) {
            $message = ['type' => 'success', 'text' => 'Qualität aktualisiert.'];
        } else {
            $message = ['type' => 'error', 'text' => 'Qualität konnte nicht aktualisiert werden.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $readFile   = basename($_POST['mark_read']);
        $readerName = trim((string)($_POST['reader_name'] ?? ''));
        if ($readerName === '') $readerName = $_SESSION['username'] ?? 'Unbekannt';
        if (markFileAsRead($readFile, $readerName)) {
            $message = ['type' => 'success', 'text' => '📖 Ausleihe für ' . htmlspecialchars($readerName, ENT_QUOTES, 'UTF-8') . ' eingetragen.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zurueckgeben_id'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $ausleiheId  = (int)$_POST['zurueckgeben_id'];
        $vonName     = trim((string)($_POST['zurueckgeben_von'] ?? ''));
        if (zurueckgebenAusleihe($ausleiheId, $vonName)) {
            $message = ['type' => 'success', 'text' => '✅ Rückgabe eingetragen.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_kopien'], $_POST['kopien_file'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'write')) {
        $kopienFile = basename($_POST['kopien_file']);
        $kopienAnz  = max(1, (int)$_POST['set_kopien']);
        setKopienAnzahl($kopienFile, $kopienAnz);
        $message = ['type' => 'success', 'text' => 'Exemplaranzahl aktualisiert.'];
    }
}

require_once 'header.php';

// --- STATISTIK ---
function getBibliothekStats() {
    $stats = ['images' => 0, 'books' => 0, 'archive' => 0, 'forbidden' => 0, 'total' => 0];
    foreach (getFiles('normal', '') as $file) {
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $is_img  = in_array($ext, ['jpg','jpeg','png','gif','webp']);
        $is_book = in_array($ext, ['pdf','txt','md','doc','docx','epub','html','htm']);
        if ($is_img)       $stats['images']++;
        elseif ($is_book)  $stats['books']++;
        else               $stats['archive']++;
        $stats['total']++;
    }
    $stats['forbidden']  = count(getFiles('forbidden', ''));
    $stats['total']     += $stats['forbidden'];
    return $stats;
}
$bibliothekStats = getBibliothekStats();

// --- VIEW LOGIK ---
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$files = [];

if ($query !== '' && !isset($_GET['cat'])) {
    $view  = 'search';
    $files = getAllFiles($query);
} elseif ($view === 'forbidden') {
    $files = getFiles('forbidden', $query);
} elseif ($view === 'index') {
    $files = getAllFiles('');
    usort($files, fn($a,$b) => strcasecmp($a['name'], $b['name']));
} elseif ($view !== 'room') {
    foreach (getFiles('normal', $query) as $file) {
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $is_img  = in_array($ext, ['jpg','jpeg','png','gif','webp']);
        $is_book = in_array($ext, ['pdf','txt','md','doc','docx','epub','html','htm']);
        if      ($view === 'images'  && $is_img)              { $file['category'] = 'normal'; $files[] = $file; }
        elseif  ($view === 'books'   && $is_book)             { $file['category'] = 'normal'; $files[] = $file; }
        elseif  ($view === 'archive' && !$is_img && !$is_book){ $file['category'] = 'normal'; $files[] = $file; }
    }
}

// --- HILFSFUNKTION: Dateiname für Anzeige aufbereiten ---
// Unterstriche → Leerzeichen, Erweiterung entfernen
function prettyFilename($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    return str_replace(['_', '-'], [' ', ' – '], $name);
}
?>

<?php if ($view === 'room'): ?>
    <img src="room.jpg" alt="Room" class="rp-bg-fullscreen">

    <a href="?cat=images"    class="rp-hotspot" style="top:34.4189%;left:27.6429%;width:17.5%;height:28.0062%;">
        <span class="rp-hotspot-label">🖼️ Zeichnungen</span>
    </a>
    <a href="?cat=books"     class="rp-hotspot" style="top:43.4719%;left:50.0714%;width:11.5714%;height:17.3643%;">
        <span class="rp-hotspot-label">📚 Bücher</span>
    </a>
    <a href="?cat=index"     class="rp-hotspot" style="top:49.458%;left:62.0714%;width:5.35714%;height:22.2894%;">
        <span class="rp-hotspot-label">📇 Index</span>
    </a>
    <a href="?cat=forbidden" class="rp-hotspot" style="top:24.909%;left:69.90%;width:21.0714%;height:26.4516%;">
        <span class="rp-hotspot-label" style="color:#ff5555;">⛔ Verboten</span>
    </a>
    <a href="?cat=archive"   class="rp-hotspot" style="top:50.818%;left:68.1429%;width:18.4286%;height:20.3151%;">
        <span class="rp-hotspot-label">📦 Archiv</span>
    </a>

    <div class="miliz-bottom-nav">
        <a href="?cat=images" class="miliz-nav-btn">
            <span class="miliz-nav-icon">🖼️</span>
            <span class="miliz-nav-label">Zeichnungen</span>
            <?php if ($bibliothekStats['images'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-uncommon);"><?= $bibliothekStats['images'] ?></span><?php endif; ?>
        </a>
        <a href="?cat=books" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📚</span>
            <span class="miliz-nav-label">Bücher</span>
            <?php if ($bibliothekStats['books'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-rare);"><?= $bibliothekStats['books'] ?></span><?php endif; ?>
        </a>
        <a href="?cat=archive" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📦</span>
            <span class="miliz-nav-label">Archiv</span>
            <?php if ($bibliothekStats['archive'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-epic);"><?= $bibliothekStats['archive'] ?></span><?php endif; ?>
        </a>
        <a href="?cat=forbidden" class="miliz-nav-btn">
            <span class="miliz-nav-icon">⛔</span>
            <span class="miliz-nav-label">Verboten</span>
            <?php if ($bibliothekStats['forbidden'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-legendary);"><?= $bibliothekStats['forbidden'] ?></span><?php endif; ?>
        </a>
        <a href="?cat=index" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📇</span>
            <span class="miliz-nav-label">Index (Alle)</span>
            <?php if ($bibliothekStats['total'] > 0): ?><span class="miliz-nav-counter" style="background:var(--accent-gold);"><?= $bibliothekStats['total'] ?></span><?php endif; ?>
        </a>
    </div>

<?php else: ?>
    <div class="rp-container <?= ($view === 'forbidden' || (isset($files[0]) && $files[0]['category'] === 'forbidden')) ? 'rp-container--forbidden' : '' ?>">
        <header>
            <h1>
                <?php
                    if      ($view === 'books')     echo '📚 Die Bücher';
                    elseif  ($view === 'images')    echo '🖼️ Die Zeichnungen';
                    elseif  ($view === 'archive')   echo '📦 Das Archiv';
                    elseif  ($view === 'forbidden') echo '⛔ Verbotene Schriften';
                    elseif  ($view === 'index')     echo '📇 Index (Alle Dateien)';
                    else                            echo '🔍 Suche in allen Bereichen';
                ?>
            </h1>
        </header>

        <div class="rp-controls">
            <?php if (hasPermission('bibliothek', 'upload')): ?>
                <form id="uploadForm" method="post" enctype="multipart/form-data" style="flex:1;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="target_cat" value="<?= $view === 'forbidden' ? 'forbidden' : 'normal' ?>">
                    <div id="dropZone" class="rp-upload-zone" onclick="document.getElementById('fileInput').click();">
                        <span class="rp-upload-zone__icon">📤</span>
                        <span class="rp-upload-zone__text">Datei per Drag & Drop oder Klick hinzufügen</span>
                        <input type="file" name="file" id="fileInput" style="display:none;">
                    </div>
                    <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                        <label for="upload_quality" class="rp-label" style="margin:0;">Qualität:</label>
                        <select id="upload_quality" name="upload_quality" class="rp-input" style="width:auto;min-width:180px;padding:6px 10px;">
                            <option value="">Auto</option>
                            <option value="common">Gewöhnlich</option>
                            <option value="uncommon">Ungewöhnlich</option>
                            <option value="rare">Selten</option>
                            <option value="epic">Episch</option>
                            <option value="legendary">Legendär</option>
                        </select>
                    </div>
                </form>
            <?php endif; ?>
            <form method="get" style="flex:1;text-align:right;">
                <input type="text" name="q" class="rp-input" placeholder="🔍 Alle Bereiche durchsuchen..." value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" style="width:70%;">
                <button type="submit" class="rp-btn rp-btn--primary">Suchen</button>
            </form>
        </div>

        <div class="rp-grid rp-grid--artifacts">
            <?php if (empty($files)): ?>
                <p style="text-align:center;width:100%;color:#888;">Hier liegt nichts...</p>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <?php
                    $isForbidden   = ($file['category'] ?? '') === 'forbidden';
                    $quality       = $file['quality'] ?? 'common';
                    $qualityClass  = 'quality-' . $quality;
                    $ext           = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $isPdf         = ($ext === 'pdf');
                    $isViewerText  = in_array($ext, ['txt','md','markdown','bbcode','bbc','html','htm'], true);
                    $isBook        = in_array($ext, ['pdf','txt','md','doc','docx','epub','html','htm']);

                    // Ausleihe-Daten
                    $lastReadBy      = trim((string)($file['last_read_by'] ?? ''));
                    $lastReadAt      = isset($file['last_read_at']) ? intval($file['last_read_at']) : null;
                    $readLog         = $isBook ? getReadLog($file['name'], 50) : [];
                    $aktiveAusleihen = $isBook ? getAktiveAusleihen($file['name']) : [];
                    $kopien          = $isBook ? getKopienAnzahl($file['name']) : 1;
                    $verfuegbar      = max(0, $kopien - count($aktiveAusleihen));
                    $currentHolder   = $lastReadBy;

                    // Schöner Anzeigename: Unterstriche → Leerzeichen
                    $prettyName = prettyFilename($file['name']);

                    $bookIconMap = [
                        'pdf'  => '📕', 'txt'  => '📜', 'md'   => '📝',
                        'doc'  => '📘', 'docx' => '📘', 'epub' => '📚',
                        'html' => '🌐', 'htm'  => '🌐',
                    ];
                    $bookIcon = $bookIconMap[$ext] ?? '📚';

                    // Qualitäts-Farbe für Cover-Akzent
                    $qualityAccents = [
                        'common'    => '#9d9d9d',
                        'uncommon'  => '#1eff00',
                        'rare'      => '#0070dd',
                        'epic'      => '#a335ee',
                        'legendary' => '#ff8000',
                    ];
                    $accentColor = $qualityAccents[$quality] ?? '#9d9d9d';
                    ?>

                    <div class="rp-card-wrapper <?= $qualityClass ?>">

                        <?php /* ── HAUPT-KARTE ── */ ?>
                        <?php if ($file['is_image']): ?>
                            <a href="javascript:void(0);"
                               onclick="openLightbox('<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>', 'image')"
                               class="rp-card rp-card--artifact <?= $qualityClass ?> <?= $isForbidden ? 'quality-legendary' : '' ?>">
                        <?php elseif ($isPdf): ?>
                            <a href="javascript:void(0);"
                               onclick="openLightbox('<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>', 'pdf')"
                               class="rp-card rp-card--artifact <?= $qualityClass ?> <?= $isForbidden ? 'quality-legendary' : '' ?>">
                        <?php elseif ($isViewerText): ?>
                            <a href="javascript:void(0);"
                               onclick="openLightbox('<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>', 'text')"
                               class="rp-card rp-card--artifact <?= $qualityClass ?> <?= $isForbidden ? 'quality-legendary' : '' ?>">
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>"
                               class="rp-card rp-card--artifact <?= $qualityClass ?> <?= $isForbidden ? 'quality-legendary' : '' ?>"
                               target="_blank">
                        <?php endif; ?>

                            <?php /* ── VORSCHAU ── */ ?>
                            <div class="rp-card__preview">
                                <?php if ($file['is_image']): ?>
                                    <img src="<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>"
                                         loading="lazy"
                                         alt="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php elseif ($isBook): ?>
                                    <div class="rp-book-cover rp-book-cover--<?= $ext ?>"
                                         style="--accent: <?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>"
                                         aria-hidden="true">
                                        <span class="rp-book-cover__icon"><?= htmlspecialchars($bookIcon, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="rp-book-cover__title"><?= htmlspecialchars($prettyName, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="rp-book-cover__ext">.<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="rp-card__icon"><?= htmlspecialchars($file['icon'] ?? '📄', ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>

                            <?php /* ── INFO-ZEILE ── */ ?>
                            <div class="rp-card__info">
                                <span class="rp-card__filename"><?= htmlspecialchars($prettyName, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($isBook && $currentHolder !== ''): ?>
                                    <span class="rp-card__read-meta">📖 <?= htmlspecialchars($currentHolder, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <span class="rp-card__file-meta">
                                    <?= htmlspecialchars(formatFileSize($file['size']), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (isset($file['category_label'])): ?>
                                        <span class="rp-badge rp-badge--category <?= $isForbidden ? 'rp-badge--forbidden' : '' ?>">
                                            <?= htmlspecialchars(strip_tags($file['category_label']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </span>

                                <?php /* ── AKTIONS-ZEILE (innerhalb der Karte, ganz unten) ── */ ?>
                                <div class="rp-card__action-row" onclick="event.preventDefault();event.stopPropagation();">

                                    <?php /* Links: Ausleihen + Zurückgeben */ ?>
                                    <?php if (!$file['is_image']): ?>
                                        <form method="post" onsubmit="event.stopPropagation();return prepareBorrowerName(this);" style="margin:0;display:contents;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="mark_read" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="reader_name" value="">
                                            <button type="submit" class="rp-card__pill-btn rp-card__pill-btn--gold" title="Ausleihen"><span>📖</span></button>
                                        </form>
                                        <?php if (!empty($aktiveAusleihen)): ?>
                                            <form method="post" onsubmit="event.stopPropagation();return prepareReturnName(this);" style="margin:0;display:contents;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="zurueckgeben_id" value="">
                                                <input type="hidden" name="zurueckgeben_von" value="">
                                                <input type="hidden" class="js-aktive-ausleihen" value="<?= htmlspecialchars(json_encode(
                                                    array_map(fn($a) => ['id' => $a['id'], 'name' => $a['reader_name']], $aktiveAusleihen)
                                                ), ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="rp-card__pill-btn rp-card__pill-btn--green" title="Zurückgeben"><span>✓</span></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php /* Rechts: Exemplare-Zahl (+ Setter) + Löschen gestapelt */ ?>
                                    <div class="rp-card__action-right">
                                        <?php if ($isBook && hasPermission('bibliothek', 'write')): ?>
                                            <form method="post" onsubmit="event.stopPropagation();return true;" style="margin:0;display:contents;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="kopien_file" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="set_kopien" class="js-kopien-val" value="<?= $kopien ?>">
                                                <button type="submit" class="rp-card__pill-btn rp-card__pill-btn--gold"
                                                        title="Exemplare: <?= $verfuegbar ?>/<?= $kopien ?> verfügbar. Klicken zum Ändern."
                                                        onclick="event.stopPropagation();var v=prompt('Anzahl Exemplare:','<?= $kopien ?>');if(v===null)return false;this.form.querySelector('.js-kopien-val').value=parseInt(v)||1;return true;">
                                                    <span><?= $kopien ?></span>
                                                </button>
                                            </form>
                                        <?php elseif ($isBook): ?>
                                            <span class="rp-card__kopien-label" title="<?= $verfuegbar ?>/<?= $kopien ?> verfügbar"><?= $kopien ?></span>
                                        <?php endif; ?>
                                        <?php if (hasPermission('bibliothek', 'write')): ?>
                                            <form method="post" onsubmit="event.stopPropagation();return confirm('Endgültig vernichten?');" style="margin:0;display:contents;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="delete_cat" value="<?= htmlspecialchars($file['category'] ?? $view, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="rp-card__pill-btn rp-card__pill-btn--red" title="Vernichten"><span>🔥</span></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                </div>

                            </div>

                        </a><?php /* Ende Haupt-Link */ ?>

                        <?php /* ── WOW-TOOLTIP ── */ ?>
                        <div class="rp-tooltip">
                            <div class="rp-tooltip__header">
                                <?= htmlspecialchars($prettyName, ENT_QUOTES, 'UTF-8') ?>
                                <span style="font-size:0.7em;opacity:0.55;display:block;margin-top:2px;">
                                    .<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>
                                    &nbsp;·&nbsp;<?= htmlspecialchars(formatFileSize($file['size']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="rp-tooltip__body">
                                <div>📅 <?= htmlspecialchars(formatDate($file['modified']), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (isset($file['category_label'])): ?>
                                    <div>📂 <?= htmlspecialchars(strip_tags($file['category_label']), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>

                                <?php if ($isBook): ?>
                                    <?php /* Exemplare-Zeile */ ?>
                                    <div style="margin-top:7px;padding-top:6px;border-top:1px solid rgba(212,175,55,0.18);">
                                        <?php
                                            $verfuegbarColor = $verfuegbar <= 0 ? 'var(--quality-legendary)' : ($verfuegbar < $kopien ? 'var(--quality-uncommon)' : 'var(--quality-uncommon)');
                                        ?>
                                        <span style="color:var(--accent-gold);">📚 Exemplare:</span>
                                        <strong style="color:<?= $verfuegbar > 0 ? '#1eff00' : '#ff4444' ?>;"><?= $verfuegbar ?></strong>
                                        <span style="opacity:0.6;">/ <?= $kopien ?> verfügbar</span>
                                        <?php if (count($aktiveAusleihen) > 0): ?>
                                            <div style="margin-top:4px;font-size:0.82em;color:rgba(255,200,100,0.9);">
                                                Ausgeliehen an:
                                                <?php foreach ($aktiveAusleihen as $a): ?>
                                                    <div style="margin-left:8px;">
                                                        📖 <?= htmlspecialchars($a['reader_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        <span style="opacity:0.55;">(<?= htmlspecialchars(formatDate(intval($a['read_at'])), ENT_QUOTES, 'UTF-8') ?>)</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size:0.82em;opacity:0.55;margin-top:2px;">Alle Exemplare verfügbar</div>
                                        <?php endif; ?>
                                    </div>

                                    <?php /* Verlauf (scrollbar bei vielen Einträgen) */ ?>
                                    <?php if (!empty($readLog)): ?>
                                        <div style="margin-top:8px;padding-top:6px;border-top:1px solid rgba(212,175,55,0.15);">
                                            <div style="font-size:0.72em;opacity:0.55;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">
                                                Verlauf (<?= count($readLog) ?>)
                                            </div>
                                            <div class="rp-tooltip__log">
                                                <?php foreach ($readLog as $log): ?>
                                                    <?php $zurück = !empty($log['zurueckgegeben_am']); ?>
                                                    <div class="rp-tooltip__log-row <?= $zurück ? 'rp-tooltip__log-row--returned' : 'rp-tooltip__log-row--active' ?>">
                                                        <span><?= $zurück ? '✅' : '📖' ?> <?= htmlspecialchars($log['reader_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="rp-tooltip__log-date"><?= htmlspecialchars(formatDate(intval($log['read_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rp-card__actions">
                            <?php if (hasPermission('bibliothek', 'write')): ?>
                                <form method="post" style="margin:0; display:inline-flex;" class="rp-card__action-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="quality_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                    <select name="set_quality" onchange="this.form.submit()" class="rp-card__quality-select rp-card__action-control" title="Qualitaet aendern">
                                        <option value="" disabled>★ Qualität</option>
                                        <option value="auto">↻ Automatisch</option>
                                        <option value="common" <?php echo $quality === 'common' ? 'selected' : ''; ?>>• Gewöhnlich</option>
                                        <option value="uncommon" <?php echo $quality === 'uncommon' ? 'selected' : ''; ?>>• Ungewöhnlich</option>
                                        <option value="rare" <?php echo $quality === 'rare' ? 'selected' : ''; ?>>• Selten</option>
                                        <option value="epic" <?php echo $quality === 'epic' ? 'selected' : ''; ?>>• Episch</option>
                                        <option value="legendary" <?php echo $quality === 'legendary' ? 'selected' : ''; ?>>• Legendär</option>
                                    </select>
                                </form>
                            <?php endif; ?>

                            <?php if (!$file['is_image']): ?>
                                <form method="post" style="margin:0; display:inline-flex;" class="rp-card__borrow-form rp-card__action-form" onsubmit="return prepareBorrowerName(this);">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="mark_read" value="<?php echo htmlspecialchars($file['name']); ?>">
                                    <input type="hidden" name="reader_name" value="">
                                    <button type="submit" class="rp-btn rp-btn--small rp-card__action-btn rp-card__action-btn--borrow" title="Ausleihe eintragen">
                                        <span class="rp-card__action-icon">📖</span>
                                        <span>Ausleihen</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (hasPermission('bibliothek', 'write')): ?>
                            <form method="post" style="margin: 0;" class="rp-card__action-form" onsubmit="return confirm('Endgültig vernichten?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                <input type="hidden" name="delete_cat" value="<?php echo $file['category'] ?? $view; ?>">
                                <button type="submit" class="rp-btn rp-btn--delete rp-btn--delete--artifact rp-card__action-btn rp-card__action-btn--delete" title="Verbrennen">
                                    <span class="rp-card__action-icon">🔥</span>
                                    <span>Löschen</span>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>

                    </div><?php /* Ende rp-card-wrapper */ ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTTOM NAV -->
    <div class="miliz-bottom-nav">
        <a href="bibliothek.php" class="miliz-nav-btn miliz-nav-back">
            <span class="miliz-nav-icon">🚪</span>
            <span class="miliz-nav-label">Zurück zum Raum</span>
        </a>
        <?php if ($view !== 'images'): ?>
        <a href="?cat=images" class="miliz-nav-btn">
            <span class="miliz-nav-icon">🖼️</span>
            <span class="miliz-nav-label">Zeichnungen</span>
            <?php if ($bibliothekStats['images'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-uncommon);"><?= $bibliothekStats['images'] ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if ($view !== 'books'): ?>
        <a href="?cat=books" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📚</span>
            <span class="miliz-nav-label">Bücher</span>
            <?php if ($bibliothekStats['books'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-rare);"><?= $bibliothekStats['books'] ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if ($view !== 'forbidden'): ?>
        <a href="?cat=forbidden" class="miliz-nav-btn">
            <span class="miliz-nav-icon">⛔</span>
            <span class="miliz-nav-label">Verboten</span>
            <?php if ($bibliothekStats['forbidden'] > 0): ?><span class="miliz-nav-counter" style="background:var(--quality-legendary);"><?= $bibliothekStats['forbidden'] ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if ($view !== 'index'): ?>
        <a href="?cat=index" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📇</span>
            <span class="miliz-nav-label">Index</span>
            <?php if ($bibliothekStats['total'] > 0): ?><span class="miliz-nav-counter" style="background:var(--accent-gold);"><?= $bibliothekStats['total'] ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (hasPermission('bibliothek', 'upload')): ?>
<script>
const dropZone   = document.getElementById('dropZone');
const fileInput  = document.getElementById('fileInput');
const uploadForm = document.getElementById('uploadForm');

if (dropZone) {
    ['dragenter','dragover','dragleave','drop'].forEach(e => {
        dropZone.addEventListener(e, preventDefaults, false);
        document.body.addEventListener(e, preventDefaults, false);
    });
    ['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('dragover'), false));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('dragover'), false));
    dropZone.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length > 0) { fileInput.files = files; uploadForm.submit(); }
    }, false);
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) uploadForm.submit();
    });
}
function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
</script>
<?php endif; ?>

<script>
function prepareBorrowerName(form) {
    const input = form.querySelector('input[name="reader_name"]');
    if (!input) return true;
    const name = window.prompt('Wer leiht dieses Buch aus?', input.value || '');
    if (name === null) return false;
    input.value = name.trim();
    return true;
}

function prepareReturnName(form) {
    const raw       = form.querySelector('.js-aktive-ausleihen');
    const idInput   = form.querySelector('input[name="zurueckgeben_id"]');
    const vonInput  = form.querySelector('input[name="zurueckgeben_von"]');
    if (!raw || !idInput) return false;

    let ausleihen = [];
    try { ausleihen = JSON.parse(raw.value); } catch(e) {}

    let chosenId = null;
    let returnName = '';

    if (ausleihen.length === 1) {
        // Nur eine aktive Ausleihe — direkt nehmen
        chosenId   = ausleihen[0].id;
        returnName = window.prompt(
            'Wer gibt zurück? (Bestätigung)',
            ausleihen[0].name
        );
        if (returnName === null) return false;
    } else if (ausleihen.length > 1) {
        // Mehrere aktive Ausleihen — Auswahl
        let list = ausleihen.map((a, i) => (i + 1) + '. ' + a.name).join('\n');
        const choice = window.prompt(
            'Welches Exemplar wird zurückgegeben?\n' + list + '\n\nNummer eingeben:',
            '1'
        );
        if (choice === null) return false;
        const idx = parseInt(choice, 10) - 1;
        if (isNaN(idx) || idx < 0 || idx >= ausleihen.length) {
            alert('Ungültige Auswahl.');
            return false;
        }
        chosenId   = ausleihen[idx].id;
        returnName = window.prompt('Name des Rückgebenden:', ausleihen[idx].name);
        if (returnName === null) return false;
    } else {
        return false;
    }

    idInput.value  = chosenId;
    vonInput.value = returnName.trim();
    return true;
}
</script>

<?php require_once 'footer.php'; ?>

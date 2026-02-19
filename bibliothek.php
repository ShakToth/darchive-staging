<?php
$rawView = isset($_GET['cat']) ? trim((string)$_GET['cat']) : 'room';
$viewAliases = [
    'room' => 'room',
    'bibliothek' => 'room',
    'images' => 'images',
    'image' => 'images',
    'bilder' => 'images',
    'zeichnungen' => 'images',
    'books' => 'books',
    'book' => 'books',
    'buecher' => 'books',
    'bücher' => 'books',
    'archive' => 'archive',
    'archiv' => 'archive',
    'index' => 'index',
    'alle' => 'index',
    'forbidden' => 'forbidden',
    'verboten' => 'forbidden'
];
$view = $viewAliases[strtolower($rawView)] ?? 'index';
$pageTitle = 'Die Bibliothek';
$bodyClass = ($view === 'room') ? 'rp-view-room' : '';

require_once 'functions.php';
// Lesezugriff für alle - nur Aktionen sind geschützt

// Vor header.php: Spezifische Aktionen die Nachrichten erzeugen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file']) && isset($_POST['delete_cat']) && isset($_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'write')) {
        $category = ($_POST['delete_cat'] === 'forbidden') ? 'forbidden' : 'normal';
        $message = handleDelete($_POST['delete_file'], $category);
    } else {
        $message = ['type' => 'error', 'text' => '🚫 Ungültige Anfrage!'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'upload')) {
        $target = $_POST['target_cat'] ?? 'normal';
        $message = handleUpload($_FILES['file'], $target);
    } else {
        $message = ['type' => 'error', 'text' => '🚫 Ungültige Anfrage (CSRF)!'];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_quality'], $_POST['quality_file'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('bibliothek', 'write')) {
        $qualityFile = basename($_POST['quality_file']);
        $newQuality = trim((string)$_POST['set_quality']);
        if ($newQuality === '' || $newQuality === 'auto') {
            $newQuality = null;
        }
        if (setFileQuality($qualityFile, $newQuality)) {
            $message = ['type' => 'success', 'text' => 'Qualitaet aktualisiert.'];
        } else {
            $message = ['type' => 'error', 'text' => 'Qualitaet konnte nicht aktualisiert werden.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $readFile = basename($_POST['mark_read']);
        $readerName = trim((string)($_POST['reader_name'] ?? ''));
        if ($readerName === '') {
            $readerName = $_SESSION['username'] ?? 'Unbekannt';
        }
        if (markFileAsRead($readFile, $readerName)) {
            $message = ['type' => 'success', 'text' => 'Ausleihe eingetragen.'];
        }
    }
}
require_once 'header.php';

// --- STATISTIK FÜR BOTTOM-NAV ---
function getBibliothekStats() {
    $stats = [
        'images' => 0,
        'books' => 0,
        'archive' => 0,
        'forbidden' => 0,
        'total' => 0
    ];
    
    // Normal Files
    $normalFiles = getFiles('normal', '');
    foreach ($normalFiles as $file) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $is_book = in_array($ext, ['pdf', 'txt', 'md', 'doc', 'docx', 'epub']);
        
        if ($is_img) $stats['images']++;
        elseif ($is_book) $stats['books']++;
        else $stats['archive']++;
        
        $stats['total']++;
    }
    
    // Forbidden Files
    $forbiddenFiles = getFiles('forbidden', '');
    $stats['forbidden'] = count($forbiddenFiles);
    $stats['total'] += $stats['forbidden'];
    
    return $stats;
}

$bibliothekStats = getBibliothekStats();

// --- VIEW LOGIK ---
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$files = [];

if ($query !== '' && !isset($_GET['cat'])) {
    $view = 'search';
    $files = getAllFiles($query);
} elseif ($view === 'forbidden') {
    $files = getFiles('forbidden', $query);
} elseif ($view === 'index') {
    $files = getAllFiles('');
    usort($files, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
} elseif ($view !== 'room') {
    $allFiles = getFiles('normal', $query);
    foreach ($allFiles as $file) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $is_book = in_array($ext, ['pdf', 'txt', 'md', 'doc', 'docx', 'epub']);
        
        if ($view === 'images' && $is_img) {
            $file['category'] = 'normal';
            $files[] = $file;
        } elseif ($view === 'books' && $is_book) {
            $file['category'] = 'normal';
            $files[] = $file;
        } elseif ($view === 'archive' && !$is_img && !$is_book) {
            $file['category'] = 'normal';
            $files[] = $file;
        }
    }
}
?>

<?php if ($view === 'room'): ?>
    <img src="room.jpg" alt="Room" class="rp-bg-fullscreen">

    <!-- HOTSPOTS -->
    <a href="?cat=images" class="rp-hotspot" style="top: 34.4189%; left: 27.6429%; width: 17.5%; height: 28.0062%;">
        <span class="rp-hotspot-label">🖼️ Zeichnungen</span>
    </a>
    
    <a href="?cat=books" class="rp-hotspot" style="top: 43.4719%; left: 50.0714%; width: 11.5714%; height: 17.3643%;">
        <span class="rp-hotspot-label">📚 Bücher</span>
    </a>
    
    <a href="?cat=index" class="rp-hotspot" style="top: 49.458%; left: 62.0714%; width: 5.35714%; height: 22.2894%;">
        <span class="rp-hotspot-label">📇 Index</span>
    </a>
    
    <a href="?cat=forbidden" class="rp-hotspot" style="top: 24.909%; left: 69.90%; width: 21.0714%; height: 26.4516%;">
        <span class="rp-hotspot-label" style="color: #ff5555;">⛔ Verboten</span>
    </a>
    
    <a href="?cat=archive" class="rp-hotspot" style="top: 50.818%; left: 68.1429%; width: 18.4286%; height: 20.3151%;">
        <span class="rp-hotspot-label">📦 Archiv</span>
    </a>

    <!-- BOTTOM NAVIGATION FÜR MOBILE -->
    <div class="miliz-bottom-nav">
        <a href="?cat=images" class="miliz-nav-btn">
            <span class="miliz-nav-icon">🖼️</span>
            <span class="miliz-nav-label">Zeichnungen</span>
            <?php if ($bibliothekStats['images'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-uncommon);">
                    <?php echo $bibliothekStats['images']; ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?cat=books" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📚</span>
            <span class="miliz-nav-label">Bücher</span>
            <?php if ($bibliothekStats['books'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-rare);">
                    <?php echo $bibliothekStats['books']; ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?cat=archive" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📦</span>
            <span class="miliz-nav-label">Archiv</span>
            <?php if ($bibliothekStats['archive'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-epic);">
                    <?php echo $bibliothekStats['archive']; ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?cat=forbidden" class="miliz-nav-btn">
            <span class="miliz-nav-icon">⛔</span>
            <span class="miliz-nav-label">Verboten</span>
            <?php if ($bibliothekStats['forbidden'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-legendary);">
                    <?php echo $bibliothekStats['forbidden']; ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?cat=index" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📇</span>
            <span class="miliz-nav-label">Index (Alle)</span>
            <?php if ($bibliothekStats['total'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--accent-gold);">
                    <?php echo $bibliothekStats['total']; ?>
                </span>
            <?php endif; ?>
        </a>
    </div>

<?php else: ?>
    <div class="rp-container <?php echo ($view === 'forbidden' || (isset($files[0]) && $files[0]['category'] === 'forbidden')) ? 'rp-container--forbidden' : ''; ?>">
        <header>
            <h1>
                <?php 
                    if ($view === 'books') echo '📚 Die Bücher';
                    elseif ($view === 'images') echo '🖼️ Die Zeichnungen';
                    elseif ($view === 'archive') echo '📦 Das Archiv';
                    elseif ($view === 'forbidden') echo '⛔ Verbotene Schriften';
                    elseif ($view === 'index') echo '📇 Index (Alle Dateien)';
                    else echo '🔍 Suche in allen Bereichen';
                ?>
            </h1>
        </header>

        <div class="rp-controls">
            <?php if (hasPermission('bibliothek', 'upload')): ?>
                <form id="uploadForm" method="post" enctype="multipart/form-data" style="flex: 1;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_cat" value="<?php echo $view === 'forbidden' ? 'forbidden' : 'normal'; ?>">
                    
                    <div id="dropZone" class="rp-upload-zone" onclick="document.getElementById('fileInput').click();">
                        <span class="rp-upload-zone__icon">📤</span>
                        <span class="rp-upload-zone__text">Datei per Drag & Drop oder Klick hinzufügen</span>
                        <input type="file" name="file" id="fileInput" style="display:none;">
                    </div>
                    <div style="margin-top:8px; display:flex; align-items:center; gap:8px;">
                        <label for="upload_quality" class="rp-label" style="margin:0;">Qualitaet:</label>
                        <select id="upload_quality" name="upload_quality" class="rp-input" style="width:auto; min-width:180px; padding:6px 10px;">
                            <option value="">Auto</option>
                            <option value="common">Common</option>
                            <option value="uncommon">Uncommon</option>
                            <option value="rare">Rare</option>
                            <option value="epic">Epic</option>
                            <option value="legendary">Legendary</option>
                        </select>
                    </div>
                </form>
            <?php endif; ?>

            <form method="get" style="flex:1; text-align:right;">
                <input type="text" name="q" class="rp-input" placeholder="🔍 Alle Bereiche durchsuchen..." value="<?php echo htmlspecialchars($query); ?>" style="width: 70%;">
                <button type="submit" class="rp-btn rp-btn--primary">Suchen</button>
            </form>
        </div>

        <div class="rp-grid rp-grid--artifacts">
            <?php if (empty($files)): ?>
                <p style="text-align:center; width:100%; color: #888;">Hier liegt nichts...</p>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <?php 
                    $isForbidden = ($file['category'] ?? '') === 'forbidden';
                    $quality = $file['quality'] ?? 'common';
                    $qualityClass = 'quality-' . $quality;
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $isPdf = ($ext === 'pdf');
                    $isViewerText = in_array($ext, ['txt', 'md', 'markdown', 'bbcode', 'bbc', 'html', 'htm'], true);
                    $isBook = in_array($ext, ['pdf', 'txt', 'md', 'doc', 'docx', 'epub']);
                    $lastReadBy = trim((string)($file['last_read_by'] ?? ''));
                    $lastReadAt = isset($file['last_read_at']) ? intval($file['last_read_at']) : null;
                    $readLog = $isBook ? getReadLog($file['name'], 3) : [];
                    ?>
                    
                    <div class="rp-card-wrapper <?php echo $qualityClass; ?>">
                        <?php if ($file['is_image']): ?>
                            <a href="javascript:void(0);" onclick="openLightbox('<?php echo $file['path']; ?>', 'image')" class="rp-card rp-card--artifact <?php echo $qualityClass; ?> <?php echo $isForbidden ? 'quality-legendary' : ''; ?>">
                        <?php elseif ($isPdf): ?>
                            <a href="javascript:void(0);" onclick="openLightbox('<?php echo $file['path']; ?>', 'pdf')" class="rp-card rp-card--artifact <?php echo $qualityClass; ?> <?php echo $isForbidden ? 'quality-legendary' : ''; ?>">
                        <?php elseif ($isViewerText): ?>
                            <a href="javascript:void(0);" onclick="openLightbox('<?php echo $file['path']; ?>', 'text')" class="rp-card rp-card--artifact <?php echo $qualityClass; ?> <?php echo $isForbidden ? 'quality-legendary' : ''; ?>">
                        <?php else: ?>
                            <a href="<?php echo $file['path']; ?>" class="rp-card rp-card--artifact <?php echo $qualityClass; ?> <?php echo $isForbidden ? 'quality-legendary' : ''; ?>" target="_blank">
                        <?php endif; ?>
                            <div class="rp-card__preview">
                                <?php if ($file['is_image']): ?>
                                    <img src="<?php echo $file['path']; ?>" loading="lazy" alt="<?php echo htmlspecialchars($file['name']); ?>">
                                <?php elseif ($isBook): ?>
                                    <?php
                                    $bookIconMap = [
                                        'pdf' => '📕',
                                        'txt' => '📜',
                                        'md' => '📝',
                                        'doc' => '📘',
                                        'docx' => '📘',
                                        'epub' => '📚'
                                    ];
                                    $bookIcon = $bookIconMap[$ext] ?? '📚';
                                    ?>
                                    <div class="rp-book-cover rp-book-cover--<?php echo $ext; ?>" aria-hidden="true">
                                        <span class="rp-book-cover__icon"><?php echo $bookIcon; ?></span>
                                        <span class="rp-book-cover__title"><?php echo htmlspecialchars(pathinfo($file['name'], PATHINFO_FILENAME)); ?></span>
                                        <span class="rp-book-cover__ext">.<?php echo htmlspecialchars($ext); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="rp-card__icon"><?php echo $file['icon']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="rp-card__info">
                                <span class="rp-card__filename"><?php echo htmlspecialchars($file['name']); ?></span>
                                <?php if ($isBook): ?>
                                    <span class="rp-card__read-meta"><?php echo $lastReadBy !== '' ? ('📖 Zuletzt: ' . htmlspecialchars($lastReadBy)) : '📖 Noch ungelesen'; ?></span>
                                <?php endif; ?>
                                <span class="rp-card__file-meta">
                                    <?php echo formatFileSize($file['size']); ?>
                                    <?php if (isset($file['category_label'])): ?>
                                        <span class="rp-badge rp-badge--category <?php echo $isForbidden ? 'rp-badge--forbidden' : ''; ?>">
                                            <?php echo $file['category_label']; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </a>

                        <div class="rp-tooltip">
                            <div class="rp-tooltip__header">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </div>
                            <div class="rp-tooltip__body">
                                <div>📊 Größe: <?php echo formatFileSize($file['size']); ?></div>
                                <div>📅 Archiviert: <?php echo formatDate($file['modified']); ?></div>
                                <?php if (isset($file['category_label'])): ?>
                                    <div>📂 Kategorie: <?php echo strip_tags($file['category_label']); ?></div>
                                <?php endif; ?>
                                <?php if ($isBook): ?>
                                    <?php if ($lastReadBy !== '' && $lastReadAt): ?>
                                        <div>📖 Zuletzt gelesen von: <?php echo htmlspecialchars($lastReadBy); ?> (<?php echo formatDate($lastReadAt); ?>)</div>
                                    <?php else: ?>
                                        <div>📖 Zuletzt gelesen von: Noch nicht gelesen</div>
                                    <?php endif; ?>
                                    <?php if (!empty($readLog)): ?>
                                        <div style="margin-top:6px; opacity:0.9;">🗒️ Letzte Leser:
                                            <?php foreach ($readLog as $idx => $log): ?>
                                                <div style="font-size:0.85em; margin-left:10px;">- <?php echo htmlspecialchars($log['reader_name']); ?> (<?php echo formatDate(intval($log['read_at'])); ?>)</div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rp-card__actions">
                            <?php if (hasPermission('bibliothek', 'write')): ?>
                                <form method="post" style="margin:0; display:inline-flex;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="quality_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                    <select name="set_quality" onchange="this.form.submit()" class="rp-card__quality-select" title="Qualitaet aendern">
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
                                <form method="post" style="margin:0; display:inline-flex;" class="rp-card__borrow-form" onsubmit="return prepareBorrowerName(this);">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="mark_read" value="<?php echo htmlspecialchars($file['name']); ?>">
                                    <input type="hidden" name="reader_name" value="">
                                    <button type="submit" class="rp-btn rp-btn--small" title="Ausleihe eintragen">📖 Ausleihen</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (hasPermission('bibliothek', 'write')): ?>
                            <form method="post" style="margin: 0;" onsubmit="return confirm('Endgültig vernichten?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                <input type="hidden" name="delete_cat" value="<?php echo $file['category'] ?? $view; ?>">
                                <button type="submit" class="rp-btn rp-btn--delete rp-btn--delete--artifact" title="Verbrennen">🔥</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- BOTTOM NAVIGATION AUCH IN LISTEN-ANSICHT -->
    <div class="miliz-bottom-nav">
        <a href="bibliothek.php" class="miliz-nav-btn miliz-nav-back">
            <span class="miliz-nav-icon">🚪</span>
            <span class="miliz-nav-label">Zurück zum Raum</span>
        </a>
        
        <?php if ($view !== 'images'): ?>
        <a href="?cat=images" class="miliz-nav-btn">
            <span class="miliz-nav-icon">🖼️</span>
            <span class="miliz-nav-label">Zeichnungen</span>
            <?php if ($bibliothekStats['images'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-uncommon);">
                    <?php echo $bibliothekStats['images']; ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <?php if ($view !== 'books'): ?>
        <a href="?cat=books" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📚</span>
            <span class="miliz-nav-label">Bücher</span>
            <?php if ($bibliothekStats['books'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-rare);">
                    <?php echo $bibliothekStats['books']; ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <?php if ($view !== 'forbidden'): ?>
        <a href="?cat=forbidden" class="miliz-nav-btn">
            <span class="miliz-nav-icon">⛔</span>
            <span class="miliz-nav-label">Verboten</span>
            <?php if ($bibliothekStats['forbidden'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--quality-legendary);">
                    <?php echo $bibliothekStats['forbidden']; ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <?php if ($view !== 'index'): ?>
        <a href="?cat=index" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📇</span>
            <span class="miliz-nav-label">Index</span>
            <?php if ($bibliothekStats['total'] > 0): ?>
                <span class="miliz-nav-counter" style="background: var(--accent-gold);">
                    <?php echo $bibliothekStats['total']; ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (hasPermission('bibliothek', 'upload')): ?>
<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadForm = document.getElementById('uploadForm');

if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    dropZone.addEventListener('drop', handleDrop, false);

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            uploadForm.submit();
        }
    });
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    dropZone.classList.add('dragover');
}

function unhighlight(e) {
    dropZone.classList.remove('dragover');
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files.length > 0) {
        fileInput.files = files;
        uploadForm.submit();
    }
}
</script>
<?php endif; ?>

<script>
function prepareBorrowerName(form) {
    const input = form.querySelector('input[name="reader_name"]');
    if (!input) return true;
    const current = input.value || '';
    const name = window.prompt('Wer leiht dieses Buch aus?', current);
    if (name === null) {
        return false;
    }
    input.value = name.trim();
    return true;
}
</script>

<?php require_once 'footer.php'; ?>

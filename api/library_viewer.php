<?php
require_once __DIR__ . '/../functions.php';

$fileParam = trim((string)($_GET['file'] ?? ''));
if ($fileParam === '') {
    http_response_code(400);
    echo '<p>Keine Datei angegeben.</p>';
    exit;
}

// Nur den Pfadanteil verwenden (kein Host, kein Query)
$relativePath = ltrim(parse_url($fileParam, PHP_URL_PATH) ?: '', '/');
$fileName = basename($relativePath);
if ($fileName === '' || $fileName === '.' || $fileName === '..') {
    http_response_code(400);
    echo '<p>Ungültiger Dateiname.</p>';
    exit;
}

// Nur Dateien aus den eigenen Upload-Verzeichnissen
$candidates = [
    UPLOAD_DIR    . $fileName,
    FORBIDDEN_DIR . $fileName
];

$realFile = null;
foreach ($candidates as $candidate) {
    $real = realpath($candidate);
    if ($real !== false && is_file($real)) {
        // Path-Traversal-Schutz: Datei muss innerhalb UPLOAD_DIR / FORBIDDEN_DIR liegen
        $upReal  = realpath(UPLOAD_DIR)    ?: '';
        $fobReal = realpath(FORBIDDEN_DIR) ?: '';
        if (strpos($real, $upReal) === 0 || strpos($real, $fobReal) === 0) {
            $realFile = $real;
            break;
        }
    }
}

if ($realFile === null) {
    http_response_code(404);
    echo '<p>Datei nicht gefunden.</p>';
    exit;
}

$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$allowed = ['txt', 'md', 'markdown', 'bbcode', 'bbc', 'html', 'htm'];
if (!in_array($ext, $allowed, true)) {
    http_response_code(415);
    echo '<p>Format wird im Viewer nicht unterstützt.</p>';
    exit;
}

$content = file_get_contents($realFile);
if ($content === false) {
    http_response_code(500);
    echo '<p>Datei konnte nicht gelesen werden.</p>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

// HTML-Dokumente: Vollständige Dokumente (DOCTYPE / <html>) bekommen einen
// sandboxed iframe. Teilfragmente werden mit sanitizeHTML() eingebettet.
if (in_array($ext, ['html', 'htm'], true)) {
    $isFullDoc = (stripos($content, '<!DOCTYPE') !== false)
              || (stripos($content, '<html') !== false);

    if ($isFullDoc) {
        // Sicherer iframe: allow-scripts für externe Fonts (@import / <link>),
        // KEIN allow-same-origin (isoliert vom Parent-Origin), KEIN allow-forms,
        // allow-top-navigation, allow-popups.
        // Die URL wird als data:-URI eingebettet, damit kein Pfad nach draußen geht.
        $b64 = base64_encode($content);
        ?>
<div class="library-viewer library-viewer--fullhtml">
    <div class="library-viewer__title-bar">
        <span class="library-viewer__title"><?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="library-viewer__hint">HTML-Dokument (geschützte Vorschau)</span>
    </div>
    <iframe
        class="library-viewer__html-frame"
        sandbox="allow-scripts"
        referrerpolicy="no-referrer"
        src="data:text/html;base64,<?= $b64 ?>"
        title="<?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?>"
    ></iframe>
</div>
<?php
        exit;
    }

    // Teilfragment: mit Sanitizer einbetten
    $rendered = sanitizeHTML($content);
    ?>
<div class="library-viewer">
    <h2 class="library-viewer__title"><?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="library-viewer__content"><?= $rendered ?></div>
</div>
<?php
    exit;
}

// Markdown / BBCode / Plain Text
switch ($ext) {
    case 'md':
    case 'markdown':
        $rendered = parseRichText($content);
        break;
    case 'bbcode':
    case 'bbc':
        $rendered = parseBBCodeSimple($content);
        break;
    default:
        $rendered = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
}
?>
<div class="library-viewer">
    <h2 class="library-viewer__title"><?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="library-viewer__content"><?= $rendered ?></div>
</div>

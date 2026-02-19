<?php
require_once __DIR__ . '/../functions.php';

$fileParam = trim((string)($_GET['file'] ?? ''));
if ($fileParam === '') {
    http_response_code(400);
    echo '<p>Keine Datei angegeben.</p>';
    exit;
}

$relativePath = ltrim(parse_url($fileParam, PHP_URL_PATH) ?: '', '/');
$fileName = basename($relativePath);
if ($fileName === '' || $fileName === '.' || $fileName === '..') {
    http_response_code(400);
    echo '<p>Ungültiger Dateiname.</p>';
    exit;
}

$candidates = [
    UPLOAD_DIR . $fileName,
    FORBIDDEN_DIR . $fileName
];

$realFile = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $realFile = $candidate;
        break;
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

$rendered = '';
switch ($ext) {
    case 'html':
    case 'htm':
        $rendered = sanitizeHTML($content);
        break;
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

header('Content-Type: text/html; charset=UTF-8');
?>
<div class="library-viewer">
    <h2 class="library-viewer__title"><?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="library-viewer__content"><?= $rendered ?></div>
</div>

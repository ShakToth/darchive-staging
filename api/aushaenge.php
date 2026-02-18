<?php
/**
 * Dämmerhafen 2.0 — Aushänge API Endpoint
 *
 * GET  /api/aushaenge.php          — Alle Zettel auflisten
 * GET  /api/aushaenge.php?id=X     — Einzelnen Zettel laden
 * POST /api/aushaenge.php          — Neuen Zettel erstellen
 * DELETE /api/aushaenge.php?id=X   — Zettel löschen (meister-only)
 */

require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../functions_aushaenge.php';

allowMethods(['GET', 'POST', 'DELETE']);

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: Zettel auflisten ---
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Einzelnen Zettel laden
        $zettel = getZettelById((int)$id);
        if (!$zettel) {
            jsonError('Zettel nicht gefunden', 404);
        }
        jsonResponse($zettel);
    }

    // Alle Zettel laden
    $zettel = getAllZettel();
    jsonResponse([
        'zettel' => $zettel,
        'count' => count($zettel)
    ]);
}

// --- POST: Neuen Zettel erstellen ---
if ($method === 'POST') {
    validateApiCsrf();
    requireApiPermission('aushaenge', 'write');

    // Daten je nach Content-Type lesen
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'multipart/form-data') !== false) {
        // FormData (mit möglichem Bild)
        $titel = trim($_POST['titel'] ?? '');
        $inhalt = trim($_POST['inhalt'] ?? '');
        $signatur = trim($_POST['signatur'] ?? '');
        $formatType = $_POST['format_type'] ?? 'standard';
    } else {
        // JSON Body
        $data = getJsonBody();
        $titel = trim($data['titel'] ?? '');
        $inhalt = trim($data['inhalt'] ?? '');
        $signatur = trim($data['signatur'] ?? '');
        $formatType = $data['format_type'] ?? 'standard';
    }

    // Validierung
    if (empty($titel)) {
        jsonError('Titel ist erforderlich');
    }
    if (empty($inhalt)) {
        jsonError('Inhalt ist erforderlich');
    }

    // Bild verarbeiten (falls vorhanden)
    $bildPfad = '';
    if (!empty($_FILES['bild']) && $_FILES['bild']['error'] === UPLOAD_ERR_OK) {
        $erlaubteTypen = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['bild']['tmp_name']);

        if (!in_array($mime, $erlaubteTypen)) {
            jsonError('Nur Bilder (JPG, PNG, GIF, WebP) erlaubt');
        }

        $ext = pathinfo($_FILES['bild']['name'], PATHINFO_EXTENSION);
        $dateiname = 'zettel_' . uniqid() . '_' . substr(md5(mt_rand()), 0, 8) . '.' . $ext;
        $zielPfad = __DIR__ . '/../aushaenge/bilder/' . $dateiname;

        if (move_uploaded_file($_FILES['bild']['tmp_name'], $zielPfad)) {
            $bildPfad = 'aushaenge/bilder/' . $dateiname;
        }
    }

    // Zettel erstellen
    $result = createZettel($titel, $inhalt, $signatur, $bildPfad, $formatType);

    if ($result) {
        jsonSuccess('Zettel wurde angeheftet!', ['id' => $result]);
    } else {
        jsonError('Zettel konnte nicht erstellt werden');
    }
}

// --- DELETE: Zettel löschen ---
if ($method === 'DELETE') {
    validateApiCsrf();
    requireApiMeister();

    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonError('Zettel-ID erforderlich');
    }

    $result = deleteZettel((int)$id);

    if ($result) {
        jsonSuccess('Zettel wurde entfernt');
    } else {
        jsonError('Zettel konnte nicht gelöscht werden');
    }
}

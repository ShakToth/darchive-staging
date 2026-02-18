<?php
/**
 * Dämmerhafen 2.0 — Upload API Endpoint
 *
 * POST /api/upload.php — Datei hochladen (Bibliothek)
 *
 * Request: multipart/form-data mit Datei im Feld "datei"
 * Optional: "quality" (common|uncommon|rare|epic|legendary)
 *
 * Response: JSON mit Upload-Ergebnis
 */

require_once __DIR__ . '/index.php';

// Nur POST erlaubt
allowMethods(['POST']);

// Auth & CSRF prüfen
validateApiCsrf();
requireApiPermission('bibliothek', 'upload');

// Datei vorhanden?
if (empty($_FILES['datei'])) {
    jsonError('Keine Datei übermittelt');
}

// Upload-Fehler prüfen
if ($_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Datei überschreitet die maximale Größe (PHP-Limit)',
        UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet die maximale Formulargröße',
        UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei übermittelt',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
        UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden',
        UPLOAD_ERR_EXTENSION => 'Upload durch PHP-Erweiterung gestoppt'
    ];
    $errorMsg = $errorMessages[$_FILES['datei']['error']] ?? 'Unbekannter Upload-Fehler';
    jsonError($errorMsg);
}

// Quality-Parameter (optional)
$quality = $_POST['quality'] ?? 'common';
$validQualities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
if (!in_array($quality, $validQualities)) {
    $quality = 'common';
}

// Legendäre Dateien → verboten-Ordner
$targetDir = ($quality === 'legendary') ? 'uploads/verboten/' : 'uploads/';

// Bestehende handleUpload()-Funktion nutzen
// Simuliert den normalen Form-Upload-Flow
$_POST['upload_quality'] = $quality;

$result = handleUpload();

if ($result && isset($result['type'])) {
    if ($result['type'] === 'success') {
        jsonSuccess($result['text'], [
            'filename' => basename($_FILES['datei']['name']),
            'quality' => $quality
        ]);
    } else {
        jsonError($result['text']);
    }
} else {
    jsonError('Upload fehlgeschlagen — unerwarteter Fehler');
}

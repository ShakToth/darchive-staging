<?php
/**
 * Dämmerhafen 2.0 — Miliz API Endpoint
 *
 * GET    /api/miliz.php                  — Alle Einträge auflisten
 * GET    /api/miliz.php?category=X       — Nach Kategorie filtern
 * GET    /api/miliz.php?id=X             — Einzelnen Eintrag laden
 * POST   /api/miliz.php                  — Neuen Eintrag erstellen
 * DELETE  /api/miliz.php?id=X            — Eintrag löschen
 */

require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../functions_miliz.php';

allowMethods(['GET', 'POST', 'DELETE']);

$method = $_SERVER['REQUEST_METHOD'];

// Erlaubte Kategorien
$erlaubteKategorien = ['befehle', 'steckbriefe', 'gesucht', 'protokolle', 'waffenkammer', 'intern'];

// --- GET: Einträge auflisten ---
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $category = $_GET['category'] ?? null;

    if ($id) {
        // Einzelnen Eintrag laden
        $eintrag = getMilizEntry((int)$id);
        if (!$eintrag) {
            jsonError('Eintrag nicht gefunden', 404);
        }
        jsonResponse($eintrag);
    }

    // Nach Kategorie filtern oder alle laden
    if ($category) {
        if (!in_array($category, $erlaubteKategorien)) {
            jsonError('Ungültige Kategorie. Erlaubt: ' . implode(', ', $erlaubteKategorien));
        }
        $eintraege = getMilizEntriesByCategory($category);
    } else {
        $eintraege = getAllMilizEntries();
    }

    jsonResponse([
        'entries' => $eintraege,
        'count' => count($eintraege),
        'category' => $category
    ]);
}

// --- POST: Neuen Eintrag erstellen ---
if ($method === 'POST') {
    validateApiCsrf();
    requireApiPermission('miliz', 'write');

    // Daten je nach Content-Type lesen
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'multipart/form-data') !== false) {
        $category = trim($_POST['category'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);
    } else {
        $data = getJsonBody();
        $category = trim($data['category'] ?? '');
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $priority = (int)($data['priority'] ?? 0);
    }

    // Validierung
    if (empty($category) || !in_array($category, $erlaubteKategorien)) {
        jsonError('Ungültige oder fehlende Kategorie');
    }
    if (empty($title)) {
        jsonError('Titel ist erforderlich');
    }

    // Datei-Upload (falls vorhanden)
    $filePath = '';
    if (!empty($_FILES['datei']) && $_FILES['datei']['error'] === UPLOAD_ERR_OK) {
        requireApiPermission('miliz', 'upload');
        $uploadResult = handleMilizFileUpload($category);
        if ($uploadResult && isset($uploadResult['path'])) {
            $filePath = $uploadResult['path'];
        }
    }

    // Eintrag erstellen
    $result = createMilizEntry($category, $title, $content, $filePath, $priority);

    if ($result) {
        jsonSuccess('Eintrag erstellt', ['id' => $result]);
    } else {
        jsonError('Eintrag konnte nicht erstellt werden');
    }
}

// --- DELETE: Eintrag löschen ---
if ($method === 'DELETE') {
    validateApiCsrf();
    requireApiPermission('miliz', 'write');

    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonError('Eintrags-ID erforderlich');
    }

    $result = deleteMilizEntry((int)$id);

    if ($result) {
        jsonSuccess('Eintrag gelöscht');
    } else {
        jsonError('Eintrag konnte nicht gelöscht werden');
    }
}

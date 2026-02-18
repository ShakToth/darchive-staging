<?php
/**
 * Dämmerhafen 2.0 — API Basis-Funktionen
 *
 * Gemeinsame Hilfsfunktionen für alle JSON-API-Endpoints.
 * Wird von den einzelnen Endpoint-Dateien eingebunden.
 *
 * KEIN direkter Aufruf — nur include/require.
 */

// Direkter Aufruf verboten
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die(json_encode(['error' => 'Direkter Zugriff nicht erlaubt']));
}

// Core-Funktionen laden
require_once __DIR__ . '/../functions.php';

/**
 * Sendet eine JSON-Antwort und beendet das Script
 *
 * @param mixed $data Response-Daten
 * @param int $code HTTP-Statuscode
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sendet eine Erfolgs-Antwort
 *
 * @param string $message Erfolgsmeldung
 * @param array $extra Zusätzliche Daten
 */
function jsonSuccess($message, $extra = []) {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $extra));
}

/**
 * Sendet eine Fehler-Antwort
 *
 * @param string $message Fehlermeldung
 * @param int $code HTTP-Statuscode
 * @param array $extra Zusätzliche Daten
 */
function jsonError($message, $code = 400, $extra = []) {
    jsonResponse(array_merge(['error' => $message], $extra), $code);
}

/**
 * Prüft ob der Benutzer angemeldet ist, sonst 401
 */
function requireApiAuth() {
    if (!isLoggedIn()) {
        jsonError('Nicht angemeldet', 401);
    }
}

/**
 * Prüft ob der Benutzer die nötige Berechtigung hat, sonst 403
 *
 * @param string $section Bereich (z.B. 'bibliothek', 'miliz')
 * @param string $action Aktion ('read', 'write', 'upload')
 */
function requireApiPermission($section, $action) {
    requireApiAuth();
    if (!hasPermission($section, $action)) {
        jsonError('Keine Berechtigung', 403);
    }
}

/**
 * Prüft ob der Benutzer Meister ist, sonst 403
 */
function requireApiMeister() {
    requireApiAuth();
    if (!isMeister()) {
        jsonError('Nur für den Meister zugänglich', 403);
    }
}

/**
 * Validiert das CSRF-Token aus dem X-CSRF-Token Header
 * Gibt 403 zurück wenn ungültig.
 */
function validateApiCsrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !verifyCSRFToken($token)) {
        jsonError('Ungültiges CSRF-Token', 403);
    }
}

/**
 * Liest den JSON-Body eines POST/PUT/DELETE Requests
 *
 * @return array Geparstes JSON als Array
 */
function getJsonBody() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Ungültiges JSON im Request-Body');
    }
    return $data ?? [];
}

/**
 * Erlaubt nur bestimmte HTTP-Methoden
 *
 * @param array $methods Erlaubte Methoden (z.B. ['GET', 'POST'])
 */
function allowMethods($methods) {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        header('Allow: ' . implode(', ', $methods));
        jsonError('Methode nicht erlaubt', 405);
    }
}

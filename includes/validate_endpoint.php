<?php

declare(strict_types=1);

require_once __DIR__ . '/connect_endpoint.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/checksession.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
}

$csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
    jsonResponse(['success' => false, 'message' => 'CSRF validation failed'], 403);
}

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Set user ID from session
global $userId;
$userId = $_SESSION['user_id'] ?? 0;

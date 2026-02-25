<?php
require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

function apiJsonResponse(bool $success, ?array $data = null, ?string $message = null, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $payload = ['success' => $success];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    if ($message !== null) {
        $payload['message'] = $message;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    apiJsonResponse(false, null, 'Unauthorized', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJsonResponse(false, null, 'Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    apiJsonResponse(false, null, 'Invalid JSON body', 400);
}

if (!verifyCsrfToken($input['csrf_token'] ?? null)) {
    apiJsonResponse(false, null, 'Invalid CSRF token', 403);
}

$type = (string) ($input['type'] ?? '');
if (!in_array($type, ['telegram', 'email'], true)) {
    apiJsonResponse(false, null, 'Invalid notification type', 400);
}

$enabled = !empty($input['enabled']) ? 1 : 0;
$pdo = getDbConnection();

// Get or create user settings
$stmt = $pdo->prepare("SELECT notify_telegram, notify_email FROM settings WHERE user_id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ($settings) {
    $stmt = $pdo->prepare("UPDATE settings SET " . ($type === 'telegram' ? 'notify_telegram' : 'notify_email') . " = :enabled, updated_at = datetime('now') WHERE user_id = :user_id");
    $stmt->execute([
        ':enabled' => $enabled,
        ':user_id' => $_SESSION['user_id'],
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO settings (user_id, notify_telegram, notify_email, created_at, updated_at) VALUES (:user_id, :notify_telegram, :notify_email, datetime('now'), datetime('now'))");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':notify_telegram' => $type === 'telegram' ? $enabled : 0,
        ':notify_email' => $type === 'email' ? $enabled : 0,
    ]);
}

$notifications = [
    'telegram' => $type === 'telegram' ? (bool) $enabled : !empty($settings['notify_telegram']),
    'email' => $type === 'email' ? (bool) $enabled : !empty($settings['notify_email']),
];

apiJsonResponse(true, ['notifications' => $notifications], 'Notification setting updated');

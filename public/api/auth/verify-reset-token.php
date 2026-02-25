<?php
require_once __DIR__ . '/../../../includes/connect.php';

header('Content-Type: application/json');

if (
    $_SERVER['REQUEST_METHOD'] !== 'GET'
) {
    http_response_code(405);
    echo jsonResponse(false, 'Method not allowed');
    exit;
}

$selector = $_GET['selector'] ?? '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !preg_match('/^[a-f0-9]{16}$/i', $selector)
    || !preg_match('/^[a-f0-9]{64}$/i', $token)
) {
    echo jsonResponse(false, '无效的重置链接');
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT token_hash FROM password_resets WHERE email = :email AND selector = :selector AND expires_at > datetime('now')");
$stmt->execute([':email' => $email, ':selector' => $selector]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

$tokenHash = hash('sha256', $token);
if (!$reset || !hash_equals((string) ($reset['token_hash'] ?? ''), $tokenHash)) {
    echo jsonResponse(false, '重置链接已过期或无效');
    exit;
}

echo jsonResponse(true, '链接有效');

function jsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

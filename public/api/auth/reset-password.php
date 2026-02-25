<?php
require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo jsonResponse(false, null, 'Method not allowed');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo jsonResponse(false, null, 'Invalid CSRF token');
    exit;
}

$selector = $_POST['selector'] ?? '';
$token = $_POST['token'] ?? '';
$email = $_POST['email'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-f0-9]{16}$/i', $selector) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    echo jsonResponse(false, null, '无效的重置链接');
    exit;
}

if (strlen($newPassword) < 8) {
    echo jsonResponse(false, null, '新密码长度至少8位');
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo jsonResponse(false, null, '两次输入的密码不一致');
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT token_hash FROM password_resets WHERE email = :email AND selector = :selector AND expires_at > datetime('now')");
$stmt->execute([':email' => $email, ':selector' => $selector]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

$tokenHash = hash('sha256', $token);
if (!$reset || !hash_equals((string) ($reset['token_hash'] ?? ''), $tokenHash)) {
    echo jsonResponse(false, null, '重置链接已过期或无效');
    exit;
}

// Update password
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash, updated_at = datetime('now') WHERE email = :email");
$stmt->execute([':password_hash' => $newHash, ':email' => $email]);

// Delete used token
$stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
$stmt->execute([':email' => $email]);

echo jsonResponse(true, null, '密码重置成功，请返回登录');

function jsonResponse($success, $data = null, $message = '') {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

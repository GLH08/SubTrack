<?php
require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo jsonResponse(false, null, 'Method not allowed');
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo jsonResponse(false, null, 'Unauthorized');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo jsonResponse(false, null, 'Invalid CSRF token');
    exit;
}

$pdo = getDbConnection();

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate input
if ($currentPassword === '') {
    echo jsonResponse(false, null, '请输入当前密码');
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

// Get user
$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo jsonResponse(false, null, '用户不存在');
    exit;
}

// Verify current password
if (!password_verify($currentPassword, $user['password_hash'])) {
    echo jsonResponse(false, null, '当前密码错误');
    exit;
}

// Update password
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash, updated_at = datetime('now') WHERE id = :id");
$stmt->execute([
    ':id' => $_SESSION['user_id'],
    ':password_hash' => $newHash
]);

echo jsonResponse(true, null, '密码修改成功');

function jsonResponse($success, $data = null, $message = '') {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

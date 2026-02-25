<?php
/**
 * Endpoint: Update User Profile
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/checksession.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo jsonResponse(false, null, 'Method not allowed');
    exit;
}

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];

$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

// Validate username
if ($username === '') {
    echo jsonResponse(false, null, '用户名不能为空');
    exit;
}

if (strlen($username) < 2 || strlen($username) > 50) {
    echo jsonResponse(false, null, '用户名长度应为2-50个字符');
    exit;
}

// Check if username is already taken by another user
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
$stmt->execute([':username' => $username, ':id' => $userId]);
if ($stmt->fetch()) {
    echo jsonResponse(false, null, '用户名已被使用');
    exit;
}

// Validate email if provided
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo jsonResponse(false, null, '邮箱格式无效');
    exit;
}

// Update user profile
$stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, updated_at = datetime('now') WHERE id = :id");
$stmt->execute([
    ':username' => $username,
    ':email' => $email ?: null,
    ':id' => $userId
]);

// Update session username
$_SESSION['username'] = $username;

echo jsonResponse(true, null, '个人资料已更新');

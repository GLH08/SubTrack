<?php
/**
 * Update User Profile Endpoint
 */

require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method Not Allowed');
    }

    if (!isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        throw new Exception('CSRF token validation failed');
    }

    // Validate session
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    $userId = $_SESSION['user_id'];
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validate input
    if (empty($username)) {
        throw new Exception('用户名不能为空');
    }

    if (empty($email)) {
        throw new Exception('邮箱不能为空');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('邮箱格式不正确');
    }

    // Check if email is already used by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        throw new Exception('该邮箱已被使用');
    }

    // Update user profile
    $stmt = $pdo->prepare("
        UPDATE users SET username = ?, email = ?, updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$username, $email, $userId]);

    // Update session
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;

    $response['success'] = true;
    $response['message'] = '个人资料已更新';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

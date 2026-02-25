<?php
/**
 * Delete Account Endpoint
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
    $confirmText = $_POST['confirm_text'] ?? '';

    // Validate confirmation
    if ($confirmText !== 'DELETE') {
        throw new Exception('请输入 DELETE 确认删除');
    }

    // Prevent deleting the final account to avoid lockout.
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = (int) $stmt->fetchColumn();
    if ($userCount <= 1) {
        throw new Exception('不能删除最后一个账户');
    }

    $password = $_POST['password'] ?? '';

    // Verify password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new Exception('密码错误');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Delete all user data in correct order (due to foreign keys)
    // Delete payment records
    $stmt = $pdo->prepare("DELETE FROM payments WHERE subscription_id IN (SELECT id FROM subscriptions WHERE user_id = ?)");
    $stmt->execute([$userId]);

    // Delete subscriptions
    $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Keep shared categories and payment methods intact (global data)

    // Delete user settings
    $stmt = $pdo->prepare("DELETE FROM settings WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    // Commit transaction
    $pdo->commit();

    // Destroy session
    session_destroy();

    $response['success'] = true;
    $response['message'] = '账户已删除';

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

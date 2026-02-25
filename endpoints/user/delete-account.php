<?php
/**
 * Endpoint: Delete User Account
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

try {
    $pdo->beginTransaction();

    // Delete user settings
    $stmt = $pdo->prepare("DELETE FROM settings WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    // Delete payment records related to user's subscriptions
    $stmt = $pdo->prepare("DELETE FROM payments WHERE subscription_id IN (SELECT id FROM subscriptions WHERE user_id = :user_id)");
    $stmt->execute([':user_id' => $userId]);

    // Delete subscriptions
    $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    // Delete login tokens
    $stmt = $pdo->prepare("DELETE FROM login_tokens WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    // Delete password reset tokens
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email IN (SELECT email FROM users WHERE id = :user_id)");
    $stmt->execute([':user_id' => $userId]);

    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);

    $pdo->commit();

    // Destroy session
    session_destroy();

    echo jsonResponse(true, null, '账户已删除');

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete account error: " . $e->getMessage());
    echo jsonResponse(false, null, '删除失败，请稍后重试');
}

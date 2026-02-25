<?php

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID不能为空']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Check in use
    // Set payment_method_id to NULL for subscriptions using this method
    $stmt = $pdo->prepare("UPDATE subscriptions SET payment_method_id = NULL WHERE payment_method_id = :id");
    $stmt->execute(['id' => $id]);

    // Delete
    $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '支付方式已删除']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

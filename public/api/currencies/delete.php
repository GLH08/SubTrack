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

    // Check if currency is used by any subscription
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE currency_id = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '无法删除已使用的货币']);
        exit;
    }

    // Delete currency (assuming system currencies like CNY/USD might be protected? For now allow delete)
    // Maybe block deleting CNY/USD?
    if ($id == 1) { // Assuming 1 is base currency
         echo json_encode(['success' => false, 'message' => '无法删除基础货币']);
         exit;
    }

    $stmt = $pdo->prepare("DELETE FROM currencies WHERE id = :id");
    $stmt->execute(['id' => $id]);

    echo json_encode(['success' => true, 'message' => '货币已删除']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

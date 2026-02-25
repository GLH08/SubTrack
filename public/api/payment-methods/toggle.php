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

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID无效']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Get current status
    $stmt = $pdo->prepare("SELECT enabled FROM payment_methods WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'message' => '支付方式不存在']);
        exit;
    }

    // Toggle status
    $newStatus = $current['enabled'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE payment_methods SET enabled = :enabled WHERE id = :id");
    $stmt->execute(['enabled' => $newStatus, 'id' => $id]);

    echo json_encode([
        'success' => true,
        'message' => $newStatus ? '已启用' : '已停用',
        'enabled' => $newStatus
    ]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

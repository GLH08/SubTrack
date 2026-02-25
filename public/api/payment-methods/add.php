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

$name = trim($_POST['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => '名称不能为空']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Check duplication
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_methods WHERE name = :name");
    $stmt->execute(['name' => $name]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '支付方式已存在']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO payment_methods (name, enabled) VALUES (:name, 1)");
    $stmt->execute(['name' => $name]);

    echo json_encode(['success' => true, 'message' => '支付方式添加成功']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

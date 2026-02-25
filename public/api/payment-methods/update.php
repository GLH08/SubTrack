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
$name = trim($_POST['name'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID无效']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => '名称不能为空']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Check if payment method exists
    $stmt = $pdo->prepare("SELECT id FROM payment_methods WHERE id = :id");
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '支付方式不存在']);
        exit;
    }

    // Check for duplicate name (excluding current payment method)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_methods WHERE name = :name AND id != :id");
    $stmt->execute(['name' => $name, 'id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '支付方式已存在']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE payment_methods SET name = :name WHERE id = :id");
    $stmt->execute(['name' => $name, 'id' => $id]);

    echo json_encode(['success' => true, 'message' => '支付方式更新成功']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

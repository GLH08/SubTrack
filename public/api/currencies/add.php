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

$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$symbol = trim($_POST['symbol'] ?? '');
$rate = floatval($_POST['rate_to_cny'] ?? 1.0);

if (empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => '代码和名称不能为空']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Check for duplicate code
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM currencies WHERE code = :code");
    $stmt->execute(['code' => $code]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '货币代码已存在']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO currencies (code, name, symbol, rate_to_cny) VALUES (:code, :name, :symbol, :rate)");
    $stmt->execute(['code' => $code, 'name' => $name, 'symbol' => $symbol, 'rate' => $rate]);

    echo json_encode(['success' => true, 'message' => '货币添加成功']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

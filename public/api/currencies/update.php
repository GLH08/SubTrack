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
$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$symbol = trim($_POST['symbol'] ?? '');
$rate = floatval($_POST['rate_to_cny'] ?? 1.0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID无效']);
    exit;
}

if (empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => '代码和名称不能为空']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Check if currency exists
    $stmt = $pdo->prepare("SELECT id FROM currencies WHERE id = :id");
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '货币不存在']);
        exit;
    }

    // Check for duplicate code (excluding current currency)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM currencies WHERE code = :code AND id != :id");
    $stmt->execute(['code' => $code, 'id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '货币代码已存在']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE currencies SET code = :code, name = :name, symbol = :symbol, rate_to_cny = :rate WHERE id = :id");
    $stmt->execute(['code' => $code, 'name' => $name, 'symbol' => $symbol, 'rate' => $rate, 'id' => $id]);

    echo json_encode(['success' => true, 'message' => '货币更新成功']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

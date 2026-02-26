<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

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

$csrfToken = $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));

if ($name === '') {
    echo json_encode(['success' => false, 'message' => '分类名称不能为空']);
    exit;
}


try {
    $pdo = getDbConnection();

    // Check if category already exists
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '分类已存在']);
        exit;
    }

    // Insert new category (without icon field)
    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
    $stmt->execute([':name' => $name]);

    echo json_encode([
        'success' => true,
        'message' => '分类添加成功',
        'data' => [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name
        ]
    ]);

} catch (PDOException $e) {
    error_log('Add category error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '添加失败']);
}

<?php

declare(strict_types=1);

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

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的分类ID']);
    exit;
}


try {
    $pdo = getDbConnection();

    // Check if category exists
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '分类不存在']);
        exit;
    }

    // Update subscriptions to remove category reference
    $stmt = $pdo->prepare('UPDATE subscriptions SET category_id = NULL WHERE category_id = :id');
    $stmt->execute([':id' => $id]);

    // Delete category
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);

    echo json_encode([
        'success' => true,
        'message' => '分类删除成功'
    ]);

} catch (PDOException $e) {
    error_log('Delete category error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '删除失败']);
}

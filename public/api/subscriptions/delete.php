<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/validate_endpoint.php';

$userId = (int) $_SESSION['user_id'];
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => '无效的订阅ID'], 400);
}

$stmt = $pdo->prepare('SELECT id FROM subscriptions WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => '订阅不存在或无权操作'], 404);
}

$stmt = $pdo->prepare('DELETE FROM payments WHERE subscription_id = :id');
$stmt->execute([':id' => $id]);

$stmt = $pdo->prepare('DELETE FROM subscriptions WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);

jsonResponse([
    'success' => true,
    'message' => '订阅已删除',
]);

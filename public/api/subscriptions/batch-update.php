<?php
/**
 * Endpoint: Batch Update Subscription Status
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/validate_endpoint.php';

$ids = $_POST['ids'] ?? [];
$status = $_POST['status'] ?? '';

if (empty($ids) || !is_array($ids)) {
    jsonResponse(['success' => false, 'message' => '未选择订阅'], 400);
}

$validStatuses = ['active', 'paused', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    jsonResponse(['success' => false, 'message' => '无效的状态'], 400);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = datetime('now') WHERE id IN ({$placeholders}) AND user_id = ?");
$params = array_merge([$status], $ids, [$userId]);
$stmt->execute($params);

jsonResponse([
    'success' => true,
    'message' => '批量更新成功',
    'updated' => $stmt->rowCount(),
]);

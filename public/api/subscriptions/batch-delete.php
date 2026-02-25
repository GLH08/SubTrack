<?php
/**
 * Endpoint: Batch Delete Subscriptions
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/validate_endpoint.php';

$ids = $_POST['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    jsonResponse(['success' => false, 'message' => '未选择订阅'], 400);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

// Delete payment records first
$stmt = $pdo->prepare("DELETE FROM payments WHERE subscription_id IN ({$placeholders}) AND subscription_id IN (SELECT id FROM subscriptions WHERE user_id = ?)");
$params = array_merge($ids, [$userId]);
$stmt->execute($params);

// Delete subscriptions
$stmt = $pdo->prepare("DELETE FROM subscriptions WHERE id IN ({$placeholders}) AND user_id = ?");
$params = array_merge($ids, [$userId]);
$stmt->execute($params);

jsonResponse([
    'success' => true,
    'message' => '批量删除成功',
    'deleted' => $stmt->rowCount(),
]);

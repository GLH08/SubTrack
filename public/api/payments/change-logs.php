<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';

function apiJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    apiJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = (int) $_SESSION['user_id'];
$paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;

if ($paymentId <= 0) {
    apiJsonResponse(['success' => false, 'message' => '无效的扣款记录ID'], 400);
}

$pdo = getDbConnection();

$ownershipStmt = $pdo->prepare('SELECT p.id FROM payments p INNER JOIN subscriptions s ON s.id = p.subscription_id WHERE p.id = :payment_id AND s.user_id = :user_id LIMIT 1');
$ownershipStmt->execute([
    ':payment_id' => $paymentId,
    ':user_id' => $userId,
]);

if (!$ownershipStmt->fetch()) {
    apiJsonResponse(['success' => false, 'message' => '扣款记录不存在或无权操作'], 404);
}

$stmt = $pdo->prepare('SELECT l.*, u.username AS changed_by_username FROM payment_change_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.payment_id = :payment_id ORDER BY l.created_at DESC, l.id DESC');
$stmt->execute([':payment_id' => $paymentId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

apiJsonResponse([
    'success' => true,
    'logs' => $logs,
]);

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
$pdo = getDbConnection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    apiJsonResponse(['success' => false, 'message' => '无效的订阅ID'], 400);
}

$stmt = $pdo->prepare('SELECT s.*, c.code as currency_code, c.symbol as currency_symbol, cat.name as category_name, cat.color as category_color, pm.name as payment_method_name FROM subscriptions s LEFT JOIN currencies c ON s.currency_id = c.id LEFT JOIN categories cat ON s.category_id = cat.id LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id WHERE s.id = :id AND s.user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$subscription = $stmt->fetch();
if (!$subscription) {
    apiJsonResponse(['success' => false, 'message' => '订阅不存在'], 404);
}

$paymentsStmt = $pdo->prepare('SELECT p.*, c.symbol as currency_symbol FROM payments p LEFT JOIN currencies c ON p.currency_id = c.id WHERE p.subscription_id = :subscription_id ORDER BY p.paid_at DESC LIMIT 20');
$paymentsStmt->execute([':subscription_id' => $id]);
$payments = $paymentsStmt->fetchAll();

apiJsonResponse([
    'subscription' => $subscription,
    'payments' => $payments,
]);

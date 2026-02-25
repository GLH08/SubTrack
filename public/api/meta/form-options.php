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

$categories = $pdo->prepare('SELECT id, name, color FROM categories ORDER BY name');
$categories->execute();
$currencies = $pdo->prepare('SELECT id, code, symbol, name FROM currencies ORDER BY code');
$currencies->execute();
$paymentMethods = $pdo->prepare('SELECT id, name FROM payment_methods WHERE enabled = 1 ORDER BY name');
$paymentMethods->execute();

apiJsonResponse([
    'categories' => $categories->fetchAll(),
    'currencies' => $currencies->fetchAll(),
    'payment_methods' => $paymentMethods->fetchAll(),
]);

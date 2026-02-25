<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';

function notificationListResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    notificationListResponse(['success' => false, 'unread_count' => 0, 'items' => [], 'message' => 'Unauthorized'], 401);
}

try {
    $pdo = getDbConnection();
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT s.id AS subscription_id,
                s.name,
                s.amount,
                s.next_payment_date,
                c.symbol AS currency_symbol
         FROM subscriptions s
         LEFT JOIN currencies c ON c.id = s.currency_id
         WHERE s.user_id = :uid
           AND s.status = 'active'
           AND s.next_payment_date IS NOT NULL
           AND s.next_payment_date >= DATE('now')
           AND s.next_payment_date <= DATE('now', '+3 days')
           AND NOT EXISTS (
               SELECT 1
               FROM notification_reads nr
               WHERE nr.user_id = :uid
                 AND nr.subscription_id = s.id
                 AND nr.due_date = s.next_payment_date
           )
         ORDER BY s.next_payment_date ASC, s.id ASC"
    );
    $stmt->execute([':uid' => $userId]);

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'subscription_id' => (int) ($row['subscription_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'amount' => (float) ($row['amount'] ?? 0),
            'currency_symbol' => (string) ($row['currency_symbol'] ?? ''),
            'next_payment_date' => (string) ($row['next_payment_date'] ?? ''),
        ];
    }

    notificationListResponse([
        'success' => true,
        'unread_count' => count($items),
        'items' => $items,
    ]);
} catch (Throwable $e) {
    notificationListResponse(['success' => false, 'unread_count' => 0, 'items' => []], 500);
}

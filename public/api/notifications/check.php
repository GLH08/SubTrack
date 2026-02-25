<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';

function notificationResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    notificationResponse(['success' => false, 'count' => 0, 'message' => 'Unauthorized'], 401);
}

try {
    $pdo = getDbConnection();
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM subscriptions s
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
           )"
    );
    $stmt->execute([':uid' => $userId]);

    notificationResponse([
        'success' => true,
        'count' => (int) $stmt->fetchColumn(),
    ]);
} catch (Throwable $e) {
    notificationResponse(['success' => false, 'count' => 0], 500);
}

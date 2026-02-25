<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

function notificationMarkReadResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Invalid JSON body'], 400);
}

if (!verifyCsrfToken($input['csrf_token'] ?? null)) {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Invalid CSRF token'], 403);
}

$subscriptionId = (int) ($input['subscription_id'] ?? 0);
$dueDate = trim((string) ($input['due_date'] ?? ''));

if ($subscriptionId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Invalid input'], 400);
}

$parsedDate = DateTime::createFromFormat('Y-m-d', $dueDate);
$dateErrors = DateTime::getLastErrors();
if (
    !$parsedDate
    || $parsedDate->format('Y-m-d') !== $dueDate
    || ($dateErrors !== false && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0))
) {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Invalid due date'], 400);
}

try {
    $pdo = getDbConnection();
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $ownStmt = $pdo->prepare(
        "SELECT id
         FROM subscriptions
         WHERE id = :sid
           AND user_id = :uid
           AND status = 'active'
           AND next_payment_date = :due_date
         LIMIT 1"
    );
    $ownStmt->execute([
        ':sid' => $subscriptionId,
        ':uid' => $userId,
        ':due_date' => $dueDate,
    ]);

    if (!$ownStmt->fetch(PDO::FETCH_ASSOC)) {
        notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Not found'], 404);
    }

    $insertStmt = $pdo->prepare(
        "INSERT OR IGNORE INTO notification_reads (user_id, subscription_id, due_date, read_at)
         VALUES (:uid, :sid, :due_date, datetime('now'))"
    );
    $insertStmt->execute([
        ':uid' => $userId,
        ':sid' => $subscriptionId,
        ':due_date' => $dueDate,
    ]);

    $countStmt = $pdo->prepare(
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
    $countStmt->execute([':uid' => $userId]);

    notificationMarkReadResponse([
        'success' => true,
        'unread_count' => (int) $countStmt->fetchColumn(),
    ]);
} catch (Throwable $e) {
    notificationMarkReadResponse(['success' => false, 'unread_count' => 0, 'message' => 'Server error'], 500);
}

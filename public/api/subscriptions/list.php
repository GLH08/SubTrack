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

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 12)));
$offset = ($page - 1) * $perPage;

$view = $_GET['view'] ?? 'card';
$search = $_GET['search'] ?? '';
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;
$currencyId = isset($_GET['currency_id']) && $_GET['currency_id'] !== '' ? (int) $_GET['currency_id'] : null;
$status = $_GET['status'] ?? '';
$paymentMethodId = isset($_GET['payment_method_id']) && $_GET['payment_method_id'] !== '' ? (int) $_GET['payment_method_id'] : null;
$sortBy = $_GET['sort_by'] ?? 'next_payment_date';
$sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');

$allowedSortFields = ['name', 'amount', 'next_payment_date', 'created_at'];
if (!in_array($sortBy, $allowedSortFields, true)) {
    $sortBy = 'next_payment_date';
}
if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
    $sortOrder = 'ASC';
}

$where = 's.user_id = :user_id';
$params = [':user_id' => $userId];

if ($search !== '') {
    $where .= ' AND s.name LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
if ($categoryId !== null) {
    $where .= ' AND s.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
if ($currencyId !== null) {
    $where .= ' AND s.currency_id = :currency_id';
    $params[':currency_id'] = $currencyId;
}
if ($status !== '') {
    $where .= ' AND s.status = :status';
    $params[':status'] = $status;
}
if ($paymentMethodId !== null) {
    $where .= ' AND s.payment_method_id = :payment_method_id';
    $params[':payment_method_id'] = $paymentMethodId;
}

$countSql = "SELECT COUNT(*) FROM subscriptions s WHERE $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$sql = "
    WITH paged AS (
        SELECT
            s.id, s.name, s.logo_url, s.amount, s.currency_id, s.interval_value,
            s.interval_unit, s.is_lifetime, s.auto_renew, s.next_payment_date,
            s.status, s.category_id, s.payment_method_id, s.website_url, s.created_at
        FROM subscriptions s
        WHERE $where
        ORDER BY s.$sortBy $sortOrder, s.id ASC
        LIMIT :limit OFFSET :offset
    )
    SELECT
        p.id, p.name, p.logo_url, p.amount, p.currency_id, p.interval_value,
        p.interval_unit, p.is_lifetime, p.auto_renew, p.next_payment_date,
        p.status, p.category_id, p.payment_method_id, p.website_url, p.created_at,
        c.code as currency_code, c.symbol as currency_symbol,
        cat.name as category_name, cat.color as category_color,
        pm.name as payment_method_name
    FROM paged p
    LEFT JOIN currencies c ON p.currency_id = c.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
    ORDER BY p.$sortBy $sortOrder, p.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
if ($search !== '') {
    $stmt->bindValue(':search', '%' . $search . '%');
}
if ($categoryId !== null) {
    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
}
if ($currencyId !== null) {
    $stmt->bindValue(':currency_id', $currencyId, PDO::PARAM_INT);
}
if ($status !== '') {
    $stmt->bindValue(':status', $status);
}
if ($paymentMethodId !== null) {
    $stmt->bindValue(':payment_method_id', $paymentMethodId, PDO::PARAM_INT);
}
$stmt->execute();
$items = $stmt->fetchAll();

apiJsonResponse([
    'success' => true,
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => (int) ceil($total / $perPage),
]);

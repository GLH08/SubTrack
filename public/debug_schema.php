<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';

if (getenv('APP_DEBUG') !== '1' || !isLoggedIn()) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();

echo "<h2>Categories Table</h2>";
$stmt = $pdo->query("PRAGMA table_info(categories)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<pre>' . htmlspecialchars(print_r($row, true), ENT_QUOTES, 'UTF-8') . '</pre>';
}

echo "<h2>Payment Methods Table</h2>";
$stmt = $pdo->query("PRAGMA table_info(payment_methods)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<pre>' . htmlspecialchars(print_r($row, true), ENT_QUOTES, 'UTF-8') . '</pre>';
}

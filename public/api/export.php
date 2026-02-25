<?php

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/checksession.php';

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="subtrack_export_' . date('Y-m-d_H-i-s') . '.json"');

try {
    $pdo = getDbConnection();

    $exportData = [
        'version' => '1.0',
        'exported_at' => date('c'),
        'user_id' => $_SESSION['user_id'],
        'categories' => [],
        'currencies' => [],
        'payment_methods' => [],
        'subscriptions' => []
    ];

    // Categories
    $stmt = $pdo->query("SELECT * FROM categories");
    $exportData['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Currencies
    $stmt = $pdo->query("SELECT * FROM currencies");
    $exportData['currencies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment Methods
    $stmt = $pdo->query("SELECT * FROM payment_methods");
    $exportData['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Subscriptions (include relation names/codes for cross-instance import mapping)
    $stmt = $pdo->prepare("SELECT s.*, c.code AS currency_code, cat.name AS category_name, pm.name AS payment_method_name
                           FROM subscriptions s
                           LEFT JOIN currencies c ON s.currency_id = c.id
                           LEFT JOIN categories cat ON s.category_id = cat.id
                           LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id
                           WHERE s.user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $exportData['subscriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

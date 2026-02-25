<?php

require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/checksession.php';
require_once __DIR__ . '/../../includes/csrf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上传失败']);
    exit;
}

$content = file_get_contents($_FILES['import_file']['tmp_name']);
$data = json_decode($content, true);

if (!$data || !is_array($data)) {
    echo json_encode(['success' => false, 'message' => '无效的 JSON 文件']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Import logic: Simple skip duplicates or overwrite? usually complex.
    // For simplicity: Add if name not exists.
    
    $importStats = ['categories' => 0, 'currencies' => 0, 'payment_methods' => 0, 'subscriptions' => 0];

    // Allow user to clear old data behavior would be nice, but risky.
    // Here we just append.

    // 1. Categories
    if (isset($data['categories'])) {
        $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
        $stmtInsert = $pdo->prepare("INSERT INTO categories (name, color) VALUES (:name, :color)");
        foreach ($data['categories'] as $item) {
            $stmtCheck->execute(['name' => $item['name']]);
            if (!$stmtCheck->fetch()) {
                $stmtInsert->execute(['name' => $item['name'], 'color' => $item['color'] ?? '#6366f1']);
                $importStats['categories']++;
            }
        }
    }

    // 2. Currencies
    if (isset($data['currencies'])) {
        $stmtCheck = $pdo->prepare("SELECT id FROM currencies WHERE code = :code");
        $stmtInsert = $pdo->prepare("INSERT INTO currencies (code, name, symbol, rate_to_cny) VALUES (:code, :name, :symbol, :rate)");
        foreach ($data['currencies'] as $item) {
            $stmtCheck->execute(['code' => $item['code']]);
            if (!$stmtCheck->fetch()) {
                $stmtInsert->execute([
                    'code' => $item['code'], 
                    'name' => $item['name'], 
                    'symbol' => $item['symbol'],
                    'rate' => $item['rate_to_cny'] ?? ($item['exchange_rate'] ?? 1) // Compat with old export
                ]);
                $importStats['currencies']++;
            }
        }
    }

    // 3. Payment Methods
    if (isset($data['payment_methods'])) {
        $stmtCheck = $pdo->prepare("SELECT id FROM payment_methods WHERE name = :name");
        $stmtInsert = $pdo->prepare("INSERT INTO payment_methods (name, enabled) VALUES (:name, :enabled)");
        foreach ($data['payment_methods'] as $item) {
            $stmtCheck->execute(['name' => $item['name']]);
            if (!$stmtCheck->fetch()) {
                $stmtInsert->execute(['name' => $item['name'], 'enabled' => $item['enabled'] ?? 1]);
                $importStats['payment_methods']++;
            }
        }
    }

    // 4. Subscriptions
    if (isset($data['subscriptions'])) {
        // Re-map IDs? This is hard. We rely on Names to find new IDs.
        $catMap = [];
        foreach ($pdo->query("SELECT id, name FROM categories") as $row) $catMap[$row['name']] = $row['id'];
        
        $curMap = [];
        foreach ($pdo->query("SELECT id, code FROM currencies") as $row) $curMap[$row['code']] = $row['id'];

        $pmMap = [];
        foreach ($pdo->query("SELECT id, name FROM payment_methods") as $row) $pmMap[$row['name']] = $row['id'];

        $sql = "INSERT INTO subscriptions (user_id, name, amount, currency_id, interval_value, interval_unit, is_lifetime, auto_renew, start_date, next_payment_date, category_id, payment_method_id, note, website_url, status) VALUES (:user_id, :name, :amount, :currency_id, :interval_value, :interval_unit, :is_lifetime, :auto_renew, :start_date, :next_payment_date, :category_id, :payment_method_id, :note, :website_url, :status)";
        $stmt = $pdo->prepare($sql);

        foreach ($data['subscriptions'] as $sub) {
            $currencyId = null;
            if (!empty($sub['currency_code']) && isset($curMap[$sub['currency_code']])) {
                $currencyId = (int) $curMap[$sub['currency_code']];
            } elseif (isset($sub['currency_id']) && is_numeric($sub['currency_id']) && (int) $sub['currency_id'] > 0) {
                $currencyId = (int) $sub['currency_id'];
            }

            if ($currencyId === null || $currencyId <= 0) {
                continue;
            }

            $categoryId = null;
            if (!empty($sub['category_name']) && isset($catMap[$sub['category_name']])) {
                $categoryId = (int) $catMap[$sub['category_name']];
            }

            $paymentMethodId = null;
            if (!empty($sub['payment_method_name']) && isset($pmMap[$sub['payment_method_name']])) {
                $paymentMethodId = (int) $pmMap[$sub['payment_method_name']];
            }

            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'name' => $sub['name'],
                'amount' => $sub['amount'],
                'currency_id' => $currencyId,
                'interval_value' => $sub['interval_value'],
                'interval_unit' => $sub['interval_unit'],
                'is_lifetime' => $sub['is_lifetime'],
                'auto_renew' => $sub['auto_renew'],
                'start_date' => $sub['start_date'],
                'next_payment_date' => $sub['next_payment_date'],
                'category_id' => $categoryId,
                'payment_method_id' => $paymentMethodId,
                'note' => $sub['note'],
                'website_url' => $sub['website_url'],
                'status' => $sub['status']
            ]);
            try {
                 // ...
                 $importStats['subscriptions']++;
            } catch (Exception $e) {}
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '导入成功', 'stats' => $importStats]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Import error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '导入错误: ' . $e->getMessage()]);
}

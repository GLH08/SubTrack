<?php

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/checksession.php';
require_once __DIR__ . '/../../../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$fxProvider = trim($_POST['fx_provider'] ?? 'fixer.io');
$fxApiKey = trim($_POST['fx_api_key'] ?? '');
$updateFxApiKey = $fxApiKey !== '';

try {
    $pdo = getDbConnection();

    // Check if settings exist
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO settings (user_id) VALUES (:user_id)");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
    }

    $keyColumnMap = [
        'apilayer.com' => 'fx_api_key_apilayer',
        'fixer.io' => 'fx_api_key_fixer',
        'exchangerate-api.com' => 'fx_api_key_exchangerate',
    ];

    if (!isset($keyColumnMap[$fxProvider])) {
        echo json_encode(['success' => false, 'message' => '不支持的 API 提供商']);
        exit;
    }

    $keyColumn = $keyColumnMap[$fxProvider];

    $sql = "UPDATE settings SET
            fx_provider = :fx_provider,
            updated_at = CURRENT_TIMESTAMP";

    if ($updateFxApiKey) {
        $sql .= ", {$keyColumn} = :fx_api_key, fx_api_key = :fx_api_key";
    }

    $sql .= " WHERE user_id = :user_id";

    $params = [
        'fx_provider' => $fxProvider,
        'user_id' => $_SESSION['user_id']
    ];

    if ($updateFxApiKey) {
        $params['fx_api_key'] = $fxApiKey;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'API 设置已保存']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

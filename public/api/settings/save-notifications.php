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

$notifyTelegram = isset($_POST['notify_telegram']) && $_POST['notify_telegram'] == '1' ? 1 : 0;
$notifyEmail = isset($_POST['notify_email']) && $_POST['notify_email'] == '1' ? 1 : 0;

$telegramToken = trim($_POST['telegram_token'] ?? '');
$updateTelegramToken = $telegramToken !== '';
$telegramChatId = trim($_POST['telegram_chat_id'] ?? '');
$emailHost = trim($_POST['email_host'] ?? '');
$emailPort = intval($_POST['email_port'] ?? 587);
$emailUsername = trim($_POST['email_username'] ?? '');
$emailPassword = trim($_POST['email_password'] ?? '');
$updateEmailPassword = $emailPassword !== '';
$emailFrom = trim($_POST['email_from'] ?? '');
$emailTo = trim($_POST['email_to'] ?? '');

try {
    $pdo = getDbConnection();

    // Check if settings exist
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        // Create default settings row
        $stmt = $pdo->prepare("INSERT INTO settings (user_id) VALUES (:user_id)");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
    }

    $sql = "UPDATE settings SET
            notify_telegram = :notify_telegram,
            notify_email = :notify_email,
            telegram_chat_id = :telegram_chat_id,
            email_host = :email_host,
            email_port = :email_port,
            email_username = :email_username,
            email_from = :email_from,
            email_to = :email_to,
            updated_at = CURRENT_TIMESTAMP";

    if ($updateTelegramToken) {
        $sql .= ", telegram_token = :telegram_token";
    }

    if ($updateEmailPassword) {
        $sql .= ", email_password = :email_password";
    }

    $sql .= " WHERE user_id = :user_id";

    $params = [
        'notify_telegram' => $notifyTelegram,
        'notify_email' => $notifyEmail,
        'telegram_chat_id' => $telegramChatId,
        'email_host' => $emailHost,
        'email_port' => $emailPort,
        'email_username' => $emailUsername,
        'email_from' => $emailFrom,
        'email_to' => $emailTo,
        'user_id' => $_SESSION['user_id']
    ];

    if ($updateTelegramToken) {
        $params['telegram_token'] = $telegramToken;
    }

    if ($updateEmailPassword) {
        $params['email_password'] = $emailPassword;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => '通知设置已保存']);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}

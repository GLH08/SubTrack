<?php
/**
 * API Endpoint: Send Test Notification
 * Tests notification channels (Telegram, Email)
 */

require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/checksession.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/NotificationService.php';

header('Content-Type: application/json');
ini_set('display_errors', '0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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

$type = $_POST['type'] ?? '';
$testMessage = '这是一条测试消息，用于验证通知配置是否正确。发送时间: ' . date('Y-m-d H:i:s');

switch ($type) {
    case 'telegram':
        $result = testTelegramNotification($testMessage);
        break;
    case 'email':
        $result = testEmailNotification($testMessage);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '未知的通知类型']);
        exit;
}

echo json_encode($result);
exit;

function testTelegramNotification(string $message): array {
    $tokenFromRequest = trim((string) ($_POST['telegram_token'] ?? ''));
    $chatIdFromRequest = trim((string) ($_POST['telegram_chat_id'] ?? ''));

    $token = $tokenFromRequest;
    $chatId = $chatIdFromRequest;

    if ($token === '' || $chatId === '') {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT notify_telegram, telegram_token, telegram_chat_id FROM settings WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token === '') {
            $token = trim((string) ($settings['telegram_token'] ?? ''));
        }
        if ($chatId === '') {
            $chatId = trim((string) ($settings['telegram_chat_id'] ?? ''));
        }
    }

    if ($token === '' || $chatId === '') {
        return ['success' => false, 'message' => 'Telegram 未配置'];
    }

    $lastError = '未知错误';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => "【SubTrack 测试消息】\n\n" . $message,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $lastError = 'CURL 错误: ' . $error;
            error_log('Telegram test CURL error (attempt ' . $attempt . '): ' . $error);
            continue;
        }

        $result = json_decode((string) $response, true);
        if ($httpCode === 200 && isset($result['ok']) && $result['ok']) {
            return ['success' => true, 'message' => 'Telegram 测试消息发送成功'];
        }

        $lastError = $result['description'] ?? ('HTTP ' . $httpCode);
        error_log('Telegram test failed (attempt ' . $attempt . '). HTTP=' . $httpCode . ', response=' . (string) $response);
    }

    return ['success' => false, 'message' => 'Telegram 发送失败: ' . $lastError];
}

function testEmailNotification(string $message): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT notify_email, email_host, email_port, email_username, email_password, email_from, email_to FROM settings WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($settings['email_host']) || empty($settings['email_username']) || empty($settings['email_password'])) {
        return ['success' => false, 'message' => '邮箱 SMTP 未完整配置'];
    }

    $to = trim((string) ($settings['email_to'] ?? $settings['email_username'] ?? ''));
    if ($to === '') {
        return ['success' => false, 'message' => '收件人邮箱未配置'];
    }

    $host = trim((string) ($settings['email_host'] ?? ''));
    $configuredPort = (int) ($settings['email_port'] ?? 587);
    $username = trim((string) ($settings['email_username'] ?? ''));
    $password = (string) ($settings['email_password'] ?? '');
    $fromRaw = trim((string) ($settings['email_from'] ?? ''));
    $fromAddress = filter_var($fromRaw, FILTER_VALIDATE_EMAIL)
        ? $fromRaw
        : (filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : $to);

    if ($fromAddress === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => '发件人邮箱无效，请在 SMTP 设置中填写邮箱地址'];
    }

    $subject = '【SubTrack】测试邮件';
    $body = "【SubTrack 测试消息】\n\n" . $message;

    $portsToTry = [$configuredPort];
    if ($configuredPort !== 587) {
        $portsToTry[] = 587;
    }
    if ($configuredPort !== 465) {
        $portsToTry[] = 465;
    }

    $lastError = '未知错误';
    foreach ($portsToTry as $port) {
        $ok = sendSmtpTestMail($host, $port, $username, $password, $fromAddress, $to, $subject, $body, $smtpError);
        if ($ok) {
            if ($port !== $configuredPort) {
                error_log("SMTP test fallback success for user {$_SESSION['user_id']}: configured port {$configuredPort}, success port {$port}");
                return ['success' => true, 'message' => "SMTP 测试成功（当前网络下已自动使用端口 {$port}）"];
            }

            return ['success' => true, 'message' => 'SMTP 测试邮件发送成功'];
        }

        $lastError = $smtpError ?: $lastError;
    }

    return ['success' => false, 'message' => 'SMTP 发送失败: ' . $lastError];
}

function sendSmtpTestMail(string $host, int $port, string $username, string $password, string $from, string $to, string $subject, string $body, ?string &$errorMessage = null): bool
{
    $errorMessage = null;

    $targetHost = $port === 465 ? 'ssl://' . $host : $host;
    $smtp = @fsockopen($targetHost, $port, $errno, $errstr, 25);
    if (!$smtp) {
        $errorMessage = "连接失败 {$host}:{$port} {$errstr} ({$errno})";
        return false;
    }

    stream_set_timeout($smtp, 15);

    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [220])) {
        $errorMessage = '服务器欢迎响应异常: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "EHLO subtrack.local\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [250])) {
        $errorMessage = 'EHLO 失败: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    if ($port === 587) {
        fwrite($smtp, "STARTTLS\r\n");
        $response = smtpReadResponse($smtp);
        if (!smtpHasCode($response, [220])) {
            $errorMessage = 'STARTTLS 失败: ' . implode(' | ', $response);
            fclose($smtp);
            return false;
        }

        if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $errorMessage = 'TLS 握手失败';
            fclose($smtp);
            return false;
        }

        fwrite($smtp, "EHLO subtrack.local\r\n");
        $response = smtpReadResponse($smtp);
        if (!smtpHasCode($response, [250])) {
            $errorMessage = 'TLS 后 EHLO 失败: ' . implode(' | ', $response);
            fclose($smtp);
            return false;
        }
    }

    fwrite($smtp, "AUTH LOGIN\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [334])) {
        $errorMessage = 'AUTH LOGIN 失败: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, base64_encode($username) . "\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [334])) {
        $errorMessage = '用户名被拒绝: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, base64_encode($password) . "\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [235])) {
        $errorMessage = '密码/认证失败: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "MAIL FROM:<{$from}>\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [250])) {
        $errorMessage = '发件人被拒绝: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "RCPT TO:<{$to}>\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [250, 251])) {
        $errorMessage = '收件人被拒绝: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "DATA\r\n");
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [354])) {
        $errorMessage = 'DATA 指令失败: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    $headers = [
        'From: SubTrack <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
    fwrite($smtp, $payload);
    $response = smtpReadResponse($smtp);
    if (!smtpHasCode($response, [250])) {
        $errorMessage = '邮件提交失败: ' . implode(' | ', $response);
        fclose($smtp);
        return false;
    }

    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);
    return true;
}

function smtpReadResponse($smtp): array
{
    $lines = [];
    while (($line = fgets($smtp, 512)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $lines[] = $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $lines;
}

function smtpHasCode(array $lines, array $acceptedCodes): bool
{
    if (empty($lines)) {
        return false;
    }

    $last = $lines[count($lines) - 1];
    $code = (int) substr($last, 0, 3);
    return in_array($code, $acceptedCodes, true);
}

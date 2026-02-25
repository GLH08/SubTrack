<?php
/**
 * Notification Service
 * Handles sending notifications via Telegram and Email
 */

declare(strict_types=1);

require_once __DIR__ . '/connect.php';

class NotificationService
{
    private PDO $pdo;
    private int $userId;
    private array $settings;
    private array $notifications;

    public static function sendPasswordResetNotification(int $userId, string $username, string $email, string $resetLink, string $expiresAt): bool
    {
        $service = new self($userId);

        $emailSubject = '密码重置请求';
        $emailBody = "您好 {$username}，\n\n";
        $emailBody .= "我们收到了您在 SubTrack 的密码重置请求。\n";
        $emailBody .= "请在 {$expiresAt} 前访问以下链接完成重置：\n\n";
        $emailBody .= "{$resetLink}\n\n";
        $emailBody .= "如果这不是您的操作，请忽略此邮件。\n";

        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $telegramMessage = "<b>SubTrack 密码重置</b>\n\n";
        $telegramMessage .= "用户：{$safeUsername}\n";
        $telegramMessage .= "邮箱：{$safeEmail}\n";
        $telegramMessage .= "有效期至：{$expiresAt}\n\n";
        $telegramMessage .= "重置链接：\n{$safeLink}";

        $sentByEmail = $service->sendEmailToAddress($email, $emailSubject, $emailBody);
        if ($sentByEmail) {
            return true;
        }

        return $service->sendTelegram($telegramMessage);
    }

    public function __construct(int $userId)
    {
        $this->pdo = getDbConnection();
        $this->userId = $userId;

        $stmt = $this->pdo->prepare("SELECT * FROM settings WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $this->userId]);
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->notifications = [
            'telegram' => !empty($this->settings['notify_telegram']),
            'email' => !empty($this->settings['notify_email']),
        ];
    }

    /**
     * Send notification for subscription due
     */
    public function sendSubscriptionDueNotification(array $subscription, int $daysBefore): bool
    {
        $message = $this->formatSubscriptionDueMessage($subscription, $daysBefore);
        $sent = false;

        if (!empty($this->notifications['telegram'])) {
            $sent = $this->sendTelegram($message) || $sent;
        }

        if (!empty($this->notifications['email'])) {
            $sent = $this->sendEmail(
                '订阅续费提醒 - ' . $subscription['name'],
                $message
            ) || $sent;
        }

        return $sent;
    }

    /**
     * Format subscription due message
     */
    private function formatSubscriptionDueMessage(array $subscription, int $daysBefore): string
    {
        $currencySymbol = $subscription['currency_symbol'] ?? '$';
        $amount = number_format($subscription['amount'], 2);
        $nextDate = date('Y年m月d日', strtotime($subscription['next_payment_date']));

        $message = "<b>⏰ 订阅续费提醒</b>\n\n";
        $message .= "<b>服务名称:</b> " . htmlspecialchars($subscription['name']) . "\n";
        $message .= "<b>续费金额:</b> {$currencySymbol}{$amount}\n";
        $message .= "<b>续费日期:</b> {$nextDate}\n";
        $message .= "<b>提前提醒:</b> {$daysBefore} 天\n\n";

        if (!empty($subscription['category_name'])) {
            $message .= "<b>分类:</b> " . htmlspecialchars($subscription['category_name']) . "\n";
        }

        if (!empty($subscription['payment_method_name'])) {
            $message .= "<b>支付方式:</b> " . htmlspecialchars($subscription['payment_method_name']) . "\n";
        }

        $message .= "\n---\n";
        $message .= "<i>来自 SubTrack 订阅管理</i>";

        return $message;
    }

    /**
     * Send notification via Telegram
     */
    public function sendTelegram(string $message): bool
    {
        $token = $this->settings['telegram_token'] ?? '';
        $chatId = $this->settings['telegram_chat_id'] ?? '';

        if (empty($token) || empty($chatId)) {
            error_log("Telegram not configured for user {$this->userId}");
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Telegram CURL error for user {$this->userId}: {$error}");
            return false;
        }

        $result = json_decode($response, true);
        if ($httpCode === 200 && isset($result['ok']) && $result['ok']) {
            return true;
        }

        error_log("Telegram send failed for user {$this->userId}: {$response}");
        return false;
    }

    /**
     * Send notification via Email
     */
    public function sendEmail(string $subject, string $body): bool
    {
        $to = trim((string) ($this->settings['email_to'] ?? $this->settings['email_username'] ?? ''));
        if ($to === '') {
            error_log("Email not configured for user {$this->userId}: missing recipient");
            return false;
        }

        return $this->sendEmailToAddress($to, $subject, $body);
    }

    public function sendEmailToAddress(string $to, string $subject, string $body): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Email recipient invalid for user {$this->userId}: {$to}");
            return false;
        }

        $host = trim((string) ($this->settings['email_host'] ?? ''));
        $port = (int) ($this->settings['email_port'] ?? 587);
        $username = trim((string) ($this->settings['email_username'] ?? ''));
        $password = (string) ($this->settings['email_password'] ?? '');
        $fromRaw = trim((string) ($this->settings['email_from'] ?? ''));

        if ($host === '') {
            return $this->sendSimpleEmail($to, $subject, $body);
        }

        if ($username === '' || $password === '') {
            error_log("SMTP credentials missing for user {$this->userId}");
            return false;
        }

        $fromAddress = $this->resolveFromAddress($fromRaw, $username, $to);
        if ($fromAddress === '') {
            error_log("SMTP sender invalid for user {$this->userId}: email_from='{$fromRaw}', email_username='{$username}'");
            return false;
        }

        return $this->sendSmtpEmail($to, $subject, $body, $host, $port, $username, $password, $fromAddress);
    }

    /**
     * Send email using simple mail() function
     */
    private function sendSimpleEmail(string $to, string $subject, string $body): bool
    {
        $headers = [
            'From: SubTrack <no-reply@subtrack.local>',
            'Reply-To: no-reply@subtrack.local',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: SubTrack/1.0',
        ];

        $subject = '【SubTrack】' . $subject;

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Send email using SMTP
     */
    private function sendSmtpEmail(string $to, string $subject, string $body, string $host, int $port, string $username, string $password, string $from): bool
    {
        // Convert body to plain text for HTML
        $plainBody = strip_tags($body);
        $plainBody = str_replace(['<b>', '</b>', '<i>', '</i>', '<br>', '<br/>'], ['**', '**', '*', '*', "\n", "\n"], $plainBody);

        $headers = [
            'From: SubTrack <' . $from . '>',
            'Reply-To: ' . $from,
            'Subject: 【SubTrack】' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $targetHost = $port === 465 ? 'ssl://' . $host : $host;

        // SMTP connection
        $smtp = @fsockopen($targetHost, $port, $errno, $errstr, 10);
        if (!$smtp) {
            error_log("SMTP connection failed for user {$this->userId}: {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($smtp, 15);

        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [220])) {
            error_log("SMTP greeting failed for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, "EHLO subtrack.local\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [250])) {
            error_log("SMTP EHLO failed for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        // STARTTLS for 587
        if ($port === 587) {
            fwrite($smtp, "STARTTLS\r\n");
            $response = $this->readSmtpResponse($smtp);
            if (!$this->isPositiveSmtpResponse($response, [220])) {
                error_log("SMTP STARTTLS failed for user {$this->userId}: " . implode(' | ', $response));
                fclose($smtp);
                return false;
            }

            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP TLS enable failed for user {$this->userId}");
                fclose($smtp);
                return false;
            }

            fwrite($smtp, "EHLO subtrack.local\r\n");
            $response = $this->readSmtpResponse($smtp);
            if (!$this->isPositiveSmtpResponse($response, [250])) {
                error_log("SMTP EHLO after STARTTLS failed for user {$this->userId}: " . implode(' | ', $response));
                fclose($smtp);
                return false;
            }
        }

        fwrite($smtp, "AUTH LOGIN\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [334])) {
            error_log("SMTP AUTH LOGIN start failed for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, base64_encode($username) . "\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [334])) {
            error_log("SMTP username rejected for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, base64_encode($password) . "\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [235])) {
            error_log("SMTP password/auth rejected for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, "MAIL FROM:<{$from}>\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [250])) {
            error_log("SMTP MAIL FROM rejected for user {$this->userId}: sender={$from}, response=" . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, "RCPT TO:<{$to}>\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [250, 251])) {
            error_log("SMTP RCPT TO rejected for user {$this->userId}: recipient={$to}, response=" . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, "DATA\r\n");
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [354])) {
            error_log("SMTP DATA command failed for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        $message = implode("\r\n", $headers) . "\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "\r\n";
        $message .= $plainBody . "\r\n";
        $message .= ".\r\n";

        fwrite($smtp, $message);
        $response = $this->readSmtpResponse($smtp);
        if (!$this->isPositiveSmtpResponse($response, [250])) {
            error_log("SMTP message send failed for user {$this->userId}: " . implode(' | ', $response));
            fclose($smtp);
            return false;
        }

        fwrite($smtp, "QUIT\r\n");
        fclose($smtp);

        return true;
    }

    /**
     * Read SMTP response
     */
    private function readSmtpResponse($smtp): array
    {
        $response = [];
        while ($line = fgets($smtp, 512)) {
            $response[] = trim($line);
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    private function isPositiveSmtpResponse(array $responseLines, array $acceptedCodes): bool
    {
        if (empty($responseLines)) {
            return false;
        }

        $lastLine = $responseLines[count($responseLines) - 1];
        $code = (int) substr($lastLine, 0, 3);

        return in_array($code, $acceptedCodes, true);
    }

    private function resolveFromAddress(string $fromRaw, string $username, string $fallbackTo): string
    {
        foreach ([$fromRaw, $username, $fallbackTo] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return '';
    }
}

/**
 * Send due reminders for all subscriptions
 */
function sendDueReminders(): int
{
    $pdo = getDbConnection();
    $now = new DateTime('now');
    $today = $now->format('Y-m-d');
    $currentTime = $now->format('H:i');
    $sentCount = 0;

    // Get all active subscriptions with notifications enabled
    $stmt = $pdo->query("
        SELECT
            s.id, s.name, s.amount, s.next_payment_date, s.remind_days_before, s.remind_time, s.remind_sent_at,
            c.symbol as currency_symbol, cat.name as category_name,
            pm.name as payment_method_name,
            u.id as user_id
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN currencies c ON s.currency_id = c.id
        LEFT JOIN categories cat ON s.category_id = cat.id
        LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id
        WHERE s.status = 'active'
            AND s.auto_renew = 1
            AND s.is_lifetime = 0
            AND s.next_payment_date IS NOT NULL
            AND s.remind_days_before > 0
        ORDER BY s.next_payment_date ASC
    ");

    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subscriptions as $sub) {
        $dueDate = new DateTime($sub['next_payment_date']);
        $reminderDate = clone $dueDate;
        $reminderDate->sub(new DateInterval('P' . $sub['remind_days_before'] . 'D'));
        $reminderDateStr = $reminderDate->format('Y-m-d');

        $remindTime = trim((string) ($sub['remind_time'] ?? '09:00'));
        if (!preg_match('/^\d{2}:\d{2}$/', $remindTime)) {
            $remindTime = '09:00';
        }

        $alreadySentToday = false;
        if (!empty($sub['remind_sent_at'])) {
            $alreadySentToday = str_starts_with((string) $sub['remind_sent_at'], $today);
        }

        // Trigger only when date matches, configured time reached, and not sent today
        if ($reminderDateStr === $today && $currentTime >= $remindTime && !$alreadySentToday) {
            $service = new NotificationService((int) $sub['user_id']);
            if ($service->sendSubscriptionDueNotification($sub, (int) $sub['remind_days_before'])) {
                $sentCount++;

                $updateStmt = $pdo->prepare("UPDATE subscriptions SET remind_sent_at = datetime('now') WHERE id = :id");
                $updateStmt->execute([':id' => $sub['id']]);
            }
        }
    }

    return $sentCount;
}

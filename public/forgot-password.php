<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/NotificationService.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $selector = bin2hex(random_bytes(8));
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("INSERT OR REPLACE INTO password_resets (email, selector, token_hash, expires_at) VALUES (:email, :selector, :token_hash, :expires)");
            $stmt->execute([
                ':email' => $email,
                ':selector' => $selector,
                ':token_hash' => $tokenHash,
                ':expires' => $expires,
            ]);

            $baseUrl = getenv('APP_BASE_URL');
            if ($baseUrl === false || trim($baseUrl) === '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseUrl = $scheme . '://' . $host;
            }
            $baseUrl = rtrim((string) $baseUrl, '/');
            $resetLink = $baseUrl . "/reset-password.php?selector={$selector}&token={$token}&email=" . urlencode($email);

            $sent = NotificationService::sendPasswordResetNotification(
                (int) $user['id'],
                (string) ($user['username'] ?? '用户'),
                $email,
                $resetLink,
                $expires
            );

            if ($sent) {
                $success = '密码重置链接已发送，请检查邮箱或 Telegram 通知';
            } else {
                $success = '如果该邮箱已注册，您将收到密码重置链接';
            }
        } else {
            // Don't reveal if email exists
            $success = '如果该邮箱已注册，您将收到密码重置链接';
        }
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>忘记密码 - SubTrack</title>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
        <link href="assets/css/app.tailwind.css" rel="stylesheet"/>

</head>
<body class="bg-background-light font-sans text-gray-900 antialiased min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 text-primary">
                <span class="material-symbols-outlined text-4xl">donut_small</span>
                <span class="text-2xl font-bold tracking-tight">SubTrack</span>
            </div>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">忘记密码</h1>
            <p class="text-gray-500 text-center mb-8">输入您的注册邮箱，我们将发送密码重置链接</p>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-100 rounded-xl text-green-600 text-sm">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form method="POST">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">邮箱地址</label>
                        <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="your@email.com"/>
                    </div>
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-xl font-medium hover:bg-gray-800 transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">send</span>
                        发送重置链接
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-6 text-center">
                <a href="login.php" class="text-sm text-gray-500 hover:text-primary flex items-center justify-center gap-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    返回登录
                </a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/csrf.php';

$selector = $_GET['selector'] ?? '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (
    empty($selector)
    || empty($token)
    || empty($email)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !preg_match('/^[a-f0-9]{16}$/i', $selector)
    || !preg_match('/^[a-f0-9]{64}$/i', $token)
) {
    $error = '无效的重置链接';
} else {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT token_hash FROM password_resets WHERE email = :email AND selector = :selector AND expires_at > datetime('now')");
    $stmt->execute([':email' => $email, ':selector' => $selector]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    $tokenHash = hash('sha256', $token);
    if (!$reset || !hash_equals((string) ($reset['token_hash'] ?? ''), $tokenHash)) {
        $error = '重置链接已过期或无效';
    }
}

$error = $error ?? '';
$success = '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>重置密码 - SubTrack</title>
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
            <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">设置新密码</h1>
            <p class="text-gray-500 text-center mb-8">请输入您的新密码</p>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
                <form id="reset-form">
                    <input type="hidden" name="selector" value="<?php echo htmlspecialchars($selector); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">新密码</label>
                        <input type="password" name="new_password" id="new_password" required minlength="8" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="至少8位字符"/>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">确认新密码</label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="8" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="再次输入新密码"/>
                    </div>

                    <div id="message" class="mb-6 p-4 rounded-xl text-sm hidden"></div>

                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-xl font-medium hover:bg-gray-800 transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">lock_reset</span>
                        重置密码
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

    <script>
    document.getElementById('reset-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const message = document.getElementById('message');
        const password = formData.get('new_password');
        const confirm = formData.get('confirm_password');

        if (password !== confirm) {
            message.className = 'mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm';
            message.textContent = '两次输入的密码不一致';
            message.classList.remove('hidden');
            return;
        }

        if (password.length < 8) {
            message.className = 'mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm';
            message.textContent = '密码长度至少8位';
            message.classList.remove('hidden');
            return;
        }

        try {
            const res = await fetch('/api/auth/reset-password.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                message.className = 'mb-6 p-4 bg-green-50 border border-green-100 rounded-xl text-green-600 text-sm';
                message.textContent = data.message;
                message.classList.remove('hidden');

                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            } else {
                message.className = 'mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm';
                message.textContent = data.message;
                message.classList.remove('hidden');
            }
        } catch (e) {
            message.className = 'mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm';
            message.textContent = '重置失败，请稍后重试';
            message.classList.remove('hidden');
        }
    });
    </script>
</body>
</html>

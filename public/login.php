<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$csrf = csrfToken();
$error = isset($_GET['error']) && $_GET['error'] === '1';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>系统登录 - SubTrack</title>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
    <link href="assets/css/app.tailwind.css" rel="stylesheet"/>

</head>
<body class="bg-background-light font-sans text-gray-900 antialiased h-screen flex flex-col items-center justify-center relative overflow-hidden">
    <div class="absolute inset-0 z-0 bg-gradient-to-br from-slate-100 via-white to-gray-200 opacity-80 pointer-events-none"></div>
    <main class="w-full max-w-[420px] px-6 z-10 relative">
        <div class="bg-card-light rounded-3xl shadow-card p-10 border border-white/50 backdrop-blur-sm">
            <div class="flex flex-col items-center mb-10">
                <div class="h-14 w-14 bg-primary text-white rounded-2xl flex items-center justify-center shadow-lg mb-5">
                    <span class="text-2xl font-bold">S</span>
                </div>
                <span class="text-2xl font-bold tracking-tight text-primary">SubTrack</span>
            </div>
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">欢迎回来</h1>
                <p class="text-sm text-text-secondary-light leading-relaxed">请输入账号密码以访问订阅管理系统</p>
            </div>
            <?php if ($error): ?>
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-2">账号或密码错误，请重试。</div>
            <?php endif; ?>
            <form action="/api/auth/login.php" class="space-y-5" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"/>
                <div>
                    <label for="username" class="sr-only">用户名</label>
                    <input id="username" name="username" required type="text" placeholder="请输入用户名" class="block w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm font-medium"/>
                </div>
                <div>
                    <label for="password" class="sr-only">密码</label>
                    <input id="password" name="password" required type="password" placeholder="请输入密码" class="block w-full px-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm font-medium"/>
                </div>
                <button class="w-full py-3.5 rounded-xl text-sm font-semibold text-white bg-primary hover:bg-primary-hover transition-all" type="submit">登录</button>
            </form>
            <div class="mt-4 text-center">
                <a href="forgot-password.php" class="text-sm text-text-secondary-light hover:text-primary transition-colors">忘记密码？</a>
            </div>
        </div>
    </main>
</body>
</html>

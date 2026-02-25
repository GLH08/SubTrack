<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];

// Get current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
}

// Get login history (last 10 logins)
$stmt = $pdo->prepare("SELECT * FROM login_tokens WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
$stmt->execute([':user_id' => $userId]);
$loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$subscriptionCount = $stmt->fetchColumn();

$currentTime = date('H:i');
$currentDate = date('Y年m月d日');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>个人资料 - SubTrack</title>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
        <link href="assets/css/app.tailwind.css" rel="stylesheet"/>
</head>
<body class="bg-background-light font-sans text-gray-900 antialiased overflow-hidden h-screen flex transition-colors duration-300">
    <!-- Sidebar -->
    <aside class="w-56 bg-card-light border-r border-gray-200 flex flex-col justify-between transition-colors duration-300 hidden md:flex z-20 shrink-0">
        <div>
            <div class="h-20 flex items-center px-6 border-b border-gray-100">
                <div class="flex items-center gap-2 text-primary">
                    <span class="material-symbols-outlined text-3xl">donut_small</span>
                    <span class="text-xl font-bold tracking-tight">SubTrack</span>
                </div>
            </div>
            <nav class="p-3 space-y-1 mt-4">
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="font-medium">仪表盘</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="subscriptions.php">
                    <span class="material-symbols-outlined">credit_card</span>
                    <span class="font-medium">订阅列表</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="calendar.php">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span class="font-medium">续费日历</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="stats.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="font-medium">统计分析</span>
                </a>
            </nav>
        </div>
        <div class="p-4 border-t border-gray-200">
            <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="settings.php">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-medium">系统设置</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="profile.php">
                <span class="material-symbols-outlined">person</span>
                <span class="font-medium">个人资料</span>
            </a>
            <form method="POST" action="/api/auth/logout.php" class="mt-1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"/>
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition-all">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="font-medium">退出登录</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-b from-gray-200/50 to-transparent pointer-events-none z-0"></div>

        <!-- Header -->
        <header class="flex items-center justify-between px-8 py-6 z-10 gap-8 shrink-0">
            <div class="flex items-center gap-8 flex-1">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">个人资料</h1>
                    <p class="text-text-secondary-light mt-1 text-sm">管理您的账户信息</p>
                </div>
            </div>
            <div class="flex items-center gap-4 shrink-0">
                <div class="flex flex-col items-end justify-center px-4 py-1.5 bg-white/50 backdrop-blur-sm rounded-xl border border-white/20">
                    <span class="text-sm font-bold text-gray-900 tracking-wide"><?php echo $currentTime; ?></span>
                    <span class="text-xs text-text-secondary-light font-medium"><?php echo $currentDate; ?></span>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto z-10 px-8 pb-8">
            <div class="max-w-4xl mx-auto space-y-6">
                <!-- Profile Card -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft border border-gray-100">
                    <div class="flex items-center gap-6 mb-8">
                        <div class="w-20 h-20 rounded-full bg-primary text-white flex items-center justify-center text-3xl font-bold">
                            <?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['username'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p class="text-text-secondary-light"><?php echo htmlspecialchars($user['email'] ?? '未设置邮箱'); ?></p>
                            <div class="flex items-center gap-4 mt-2">
                                <span class="text-xs text-gray-500"><?php echo $subscriptionCount; ?> 个订阅</span>
                                <span class="text-xs text-gray-500"><?php echo $user['created_at'] ? '创建于 ' . date('Y-m-d', strtotime($user['created_at'])) : ''; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form -->
                    <form id="profile-form" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"/>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">用户名</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" required/>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">邮箱地址</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="your@email.com"/>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-hover transition-colors shadow-lg">
                                保存更改
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Security -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">security</span>
                        账户安全
                    </h3>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-4">
                                <span class="material-symbols-outlined text-gray-400">lock</span>
                                <div>
                                    <p class="font-medium text-gray-900">密码</p>
                                    <p class="text-sm text-gray-500">••••••••</p>
                                </div>
                            </div>
                            <a href="settings.php" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                                修改密码
                            </a>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-4">
                                <span class="material-symbols-outlined text-gray-400">history</span>
                                <div>
                                    <p class="font-medium text-gray-900">登录历史</p>
                                    <p class="text-sm text-gray-500">最近 10 次登录记录</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Login History -->
                    <div class="mt-6 space-y-3">
                        <?php foreach ($loginHistory as $index => $login): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg <?php echo $index === 0 ? 'bg-primary/5 border border-primary/10' : 'bg-gray-50'; ?>">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-lg <?php echo $index === 0 ? 'text-primary' : 'text-gray-400'; ?>">
                                        <?php echo $login['user_agent'] && strpos($login['user_agent'], 'Mobile') !== false ? 'smartphone' : 'computer'; ?>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium <?php echo $index === 0 ? 'text-primary' : 'text-gray-900'; ?>">
                                            <?php echo $index === 0 ? '当前会话' : '历史登录'; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($login['user_agent'] ?? '未知设备'); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-900"><?php echo date('Y-m-d', strtotime($login['created_at'])); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($login['created_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($loginHistory)): ?>
                            <div class="text-center py-8 text-gray-400">
                                <span class="material-symbols-outlined text-4xl mb-2">history</span>
                                <p class="text-sm">暂无登录记录</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft border border-red-200">
                    <h3 class="text-lg font-bold text-red-600 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined">dangerous</span>
                        危险操作
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">以下操作具有不可逆性，请谨慎操作。</p>
                    <div class="p-4 bg-red-50 rounded-xl border border-red-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="font-medium text-gray-900">删除账户</p>
                                <p class="text-sm text-gray-500">永久删除您的账户及所有数据</p>
                            </div>
                            <button onclick="deleteAccount()" class="px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition-colors">
                                删除账户
                            </button>
                        </div>
                        <div class="space-y-2">
                            <input type="password" id="delete-password" placeholder="输入密码以确认删除操作"
                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                            <input type="text" id="delete-confirm-text" placeholder="输入 DELETE 以确认"
                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 uppercase" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = '<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>';

        document.getElementById('profile-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const res = await fetch('/api/users/update-profile.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert('个人资料已更新');
                    location.reload();
                } else {
                    alert(data.message || '更新失败');
                }
            } catch (e) {
                alert('更新失败，请稍后重试');
            }
        });

        async function deleteAccount() {
            const warning = '危险操作：删除后不可恢复，所有订阅与支付记录将永久删除。';
            if (!confirm(warning + '\n\n请确认继续。')) return;

            const secondConfirm = prompt('请输入 DELETE 以继续删除账户：', '');
            if (secondConfirm !== 'DELETE') {
                alert('删除已取消：必须准确输入 DELETE');
                return;
            }

            const password = document.getElementById('delete-password').value;
            const confirmTextInput = document.getElementById('delete-confirm-text').value.trim();
            if (!password) {
                alert('请输入密码以确认删除操作');
                return;
            }

            if (confirmTextInput !== 'DELETE') {
                alert('请输入 DELETE 以确认删除操作');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('password', password);
                formData.append('confirm_text', confirmTextInput);

                const res = await fetch('/api/users/delete-account.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert('账户已删除');
                    window.location.href = 'login.php';
                } else {
                    alert(data.message || '删除失败');
                }
            } catch (e) {
                alert('删除失败，请稍后重试');
            }
        }
    </script>
</body>
</html>

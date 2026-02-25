<?php
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

$pdo = getDbConnection();

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get currencies
$stmt = $pdo->query("SELECT * FROM currencies ORDER BY code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment methods
$stmt = $pdo->query("SELECT * FROM payment_methods ORDER BY name");
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user settings
$stmt = $pdo->prepare("SELECT * FROM settings WHERE user_id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    $settings = [];
}

$hasTelegramToken = !empty($settings['telegram_token']);
$hasEmailPassword = !empty($settings['email_password']);
$providerApiKeyConfigured = [
    'exchangerate-api.com' => !empty($settings['fx_api_key_exchangerate']),
    'apilayer.com' => !empty($settings['fx_api_key_apilayer']),
    'fixer.io' => !empty($settings['fx_api_key_fixer']),
];

// Get notification settings
$notifications = [
    'telegram' => !empty($settings['notify_telegram']),
    'email' => !empty($settings['notify_email']),
];

// Get exchange rate last update time
$exchangeRateLastUpdate = date('Y-m-d H:i:s');
try {
    $stmt = $pdo->prepare("SELECT value FROM global_settings WHERE key_name = 'exchange_rate_last_update'");
    $stmt->execute();
    $exchangeRateLastUpdate = $stmt->fetchColumn() ?: $exchangeRateLastUpdate;
} catch (PDOException $e) {
    // Keep default timestamp when legacy database has no global_settings table
}

// Get current time for header
$currentTime = date('H:i');
$currentDate = date('Y年m月d日');

// Category colors
$categoryColors = [
    'indigo' => 'bg-indigo-50 text-indigo-700',
    'emerald' => 'bg-emerald-50 text-emerald-700',
    'amber' => 'bg-amber-50 text-amber-700',
    'rose' => 'bg-rose-50 text-rose-700',
    'sky' => 'bg-sky-50 text-sky-700',
    'blue' => 'bg-blue-50 text-blue-700',
    'purple' => 'bg-purple-50 text-purple-700',
    'green' => 'bg-green-50 text-green-700',
    'red' => 'bg-red-50 text-red-700',
    'orange' => 'bg-orange-50 text-orange-700',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>系统设置 - SubTrack</title>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
        <link href="assets/css/app.tailwind.css" rel="stylesheet"/>
    <script src="assets/js/notification-popover.js"></script>
    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .toggle-checkbox:checked {
            right: 0;
            border-color: #111827;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #111827;
        }
    </style>
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
            <a class="flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="settings.php">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-medium">系统设置</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="profile.php">
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
        <!-- Background gradient -->
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-b from-gray-200/50 to-transparent pointer-events-none z-0"></div>

        <!-- Header -->
        <header class="flex items-center justify-between px-8 py-6 z-10 shrink-0">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">系统设置</h1>
                <p class="text-text-secondary-light mt-1">管理应用偏好、支付方式及系统配置。</p>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 rounded-full bg-white shadow-sm text-gray-500 hover:text-primary transition-colors relative" id="notification-btn">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <div class="flex flex-col items-end justify-center px-4 py-1.5 bg-white/50 backdrop-blur-sm rounded-xl border border-white/20">
                    <span class="text-sm font-bold text-gray-900 tracking-wide"><?php echo $currentTime; ?></span>
                    <span class="text-xs text-text-secondary-light font-medium"><?php echo $currentDate; ?></span>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto px-8 pb-8 z-10 space-y-6">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="xl:col-span-2 space-y-6">
                    <!-- Categories Section -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">label</span>
                                订阅分类设置
                            </h3>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach ($categories as $index => $cat): ?>
                                <?php $colorClass = $categoryColors[array_keys($categoryColors)[$index % count($categoryColors)]] ?? 'bg-gray-50 text-gray-700'; ?>
                                <div class="flex items-center gap-2 px-3 py-1.5 <?php echo $colorClass; ?> rounded-lg text-sm font-medium group cursor-pointer border border-transparent hover:border-gray-200 transition-all">
                                    <?php if (!empty($cat['icon'])): ?>
                                        <span class="material-symbols-outlined text-sm"><?php echo htmlspecialchars($cat['icon']); ?></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <button class="hover:opacity-70" onclick="deleteCategory(<?php echo $cat['id']; ?>)">
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            <button onclick="showAddCategoryModal()" class="flex items-center gap-1 px-3 py-1.5 border border-dashed border-gray-300 text-text-secondary-light rounded-lg text-sm font-medium hover:border-primary hover:text-primary transition-all">
                                <span class="material-symbols-outlined text-[18px]">add</span> 添加分类
                            </button>
                        </div>
                    </section>

                    <!-- Currency Section -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">currency_exchange</span>
                                货币设置
                            </h3>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-gray-100">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-text-secondary-light font-medium">
                                    <tr>
                                        <th class="px-6 py-3">图标</th>
                                        <th class="px-6 py-3">名称</th>
                                        <th class="px-6 py-3">缩写</th>
                                        <th class="px-6 py-3">汇率</th>
                                        <th class="px-6 py-3 text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($currencies as $curr): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 font-bold text-lg"><?php echo htmlspecialchars($curr['symbol']); ?></td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($curr['name']); ?></td>
                                        <td class="px-6 py-4"><span class="bg-gray-100 px-2 py-1 rounded text-xs font-mono"><?php echo htmlspecialchars($curr['code']); ?></span></td>
                                        <td class="px-6 py-4 text-text-secondary-light"><?php echo number_format($curr['rate_to_cny'] ?? 1, 4); ?></td>
                                        <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                                            <button class="text-gray-400 hover:text-primary" onclick="editCurrency(<?php echo $curr['id']; ?>)">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </button>
                                            <?php if ($curr['code'] !== 'USD'): ?>
                                            <button class="text-gray-400 hover:text-red-500" onclick="deleteCurrency(<?php echo $curr['id']; ?>)">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <div class="flex flex-col gap-1">
                                <div class="text-xs text-text-secondary-light">
                                    <span id="exchange-rate-info">汇率最后更新: <?php echo date('Y-m-d H:i', strtotime($exchangeRateLastUpdate)); ?></span>
                                </div>
                                <div class="text-xs text-text-secondary-light">
                                    查找货币代码请访问 <a class="text-primary hover:underline" href="https://fixer.io" target="_blank">fixer.io</a>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="updateExchangeRates()" id="update-rates-btn" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-1.5 shadow-lg shadow-green-100">
                                    <span class="material-symbols-outlined text-sm">sync</span> 更新汇率
                                </button>
                                <button onclick="showAddCurrencyModal()" class="bg-primary hover:bg-primary-hover text-white px-3 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-1.5 shadow-lg shadow-gray-200">
                                    <span class="material-symbols-outlined text-sm">add</span> 添加货币
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Payment Methods Section -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">credit_card</span>
                                支付方式管理
                            </h3>
                            <button onclick="showAddPaymentMethodModal()" class="text-sm font-medium text-primary hover:bg-gray-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">add</span> 添加支付方式
                            </button>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($paymentMethods as $pm): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-100 rounded-2xl hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl <?php echo $pm['bg_color'] ?? 'bg-gray-100'; ?> flex items-center justify-center <?php echo $pm['text_color'] ?? 'text-gray-600'; ?>">
                                        <span class="material-symbols-outlined text-2xl"><?php echo $pm['icon'] ?? 'credit_card'; ?></span>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($pm['name']); ?></h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer" <?php echo !empty($pm['enabled']) ? 'checked' : ''; ?> onchange="togglePaymentMethod(<?php echo $pm['id']; ?>, this)">
                                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
                                            </label>
                                            <span class="text-xs font-medium <?php echo !empty($pm['enabled']) ? 'text-green-600' : 'text-gray-500'; ?>">
                                                <?php echo !empty($pm['enabled']) ? '已启用' : '已停用'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button class="p-2 text-text-secondary-light hover:text-primary hover:bg-white rounded-lg transition-all" onclick="editPaymentMethod(<?php echo $pm['id']; ?>)">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <?php if (!in_array($pm['name'], ['支付宝', '微信支付', '信用卡', 'PayPal'], true)): ?>
                                    <button class="p-2 text-text-secondary-light hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" onclick="deletePaymentMethod(<?php echo $pm['id']; ?>)">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Account Management -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">manage_accounts</span>
                            账户管理
                        </h3>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="h-14 w-14 rounded-full bg-primary text-white flex items-center justify-center text-xl font-bold border-4 border-gray-50">
                                <?php echo $user ? mb_strtoupper(mb_substr($user['username'], 0, 1)) : 'A'; ?>
                            </div>
                            <div>
                                <p class="text-xs text-text-secondary-light">当前用户</p>
                                <p class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($user['username'] ?? 'admin'); ?></p>
                            </div>
                        </div>
                        <button onclick="showChangePasswordModal()" class="w-full border border-gray-200 text-gray-900 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">lock_reset</span> 修改密码
                        </button>
                    </section>

                    <!-- Notification Settings -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">notifications_active</span>
                            通知设置
                        </h3>

                        <!-- Telegram Configuration -->
                        <div class="mb-6 pb-6 border-b border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-blue-500">send</span>
                                    <span class="font-medium text-gray-900 text-sm">Telegram</span>
                                </div>
                                <div class="relative inline-block w-10 mr-2 align-middle select-none transition duration-200 ease-in">
                                    <input class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" id="toggle_telegram" name="toggle" type="checkbox" <?php echo !empty($notifications['telegram']) ? 'checked' : ''; ?>/>
                                    <label class="toggle-label block overflow-hidden h-5 rounded-full <?php echo !empty($notifications['telegram']) ? 'bg-primary' : 'bg-gray-300'; ?> cursor-pointer" for="toggle_telegram"></label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Bot Token</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="telegram_token" placeholder="<?php echo $hasTelegramToken ? '已配置，留空则保持不变' : '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'; ?>" type="text" value="" autocomplete="off"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Chat ID</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="telegram_chat_id" placeholder="-100123456789" type="text" value="<?php echo htmlspecialchars($settings['telegram_chat_id'] ?? ''); ?>"/>
                                </div>
                            </div>
                            <button onclick="testNotification('telegram')" class="mt-3 text-xs text-primary underline hover:opacity-80">测试通知</button>
                        </div>

                        <!-- Email Configuration -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-orange-500">mail</span>
                                    <span class="font-medium text-gray-900 text-sm">邮箱 SMTP</span>
                                </div>
                                <div class="relative inline-block w-10 mr-2 align-middle select-none transition duration-200 ease-in">
                                    <input class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" id="toggle_email" name="toggle" type="checkbox" <?php echo !empty($notifications['email']) ? 'checked' : ''; ?>/>
                                    <label class="toggle-label block overflow-hidden h-5 rounded-full <?php echo !empty($notifications['email']) ? 'bg-primary' : 'bg-gray-300'; ?> cursor-pointer" for="toggle_email"></label>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">SMTP 服务器</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="email_host" placeholder="smtp.gmail.com" type="text" value="<?php echo htmlspecialchars($settings['email_host'] ?? ''); ?>"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">端口</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="email_port" placeholder="587" type="number" value="<?php echo htmlspecialchars($settings['email_port'] ?? 587); ?>"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">邮箱账号</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="email_username" placeholder="your@email.com" type="email" value="<?php echo htmlspecialchars($settings['email_username'] ?? ''); ?>"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">密码/App Key</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="email_password" placeholder="<?php echo $hasEmailPassword ? '已配置，留空则保持不变' : '邮箱密码或App Key'; ?>" type="password" value="" autocomplete="new-password"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">发件人名称</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="email_from" placeholder="SubTrack" type="text" value="<?php echo htmlspecialchars($settings['email_from'] ?? ''); ?>"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">接收邮箱</label>
                                    <input class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg p-2.5" name="email_to" placeholder="your@email.com" type="email" value="<?php echo htmlspecialchars($settings['email_to'] ?? ''); ?>"/>
                                </div>
                            </div>
                            <button onclick="testNotification('email')" class="mt-3 text-xs text-primary underline hover:opacity-80">测试通知</button>
                        </div>

                        <button onclick="saveNotificationSettings()" class="w-full bg-primary hover:bg-primary-hover text-white py-2.5 rounded-xl text-sm font-medium transition-colors flex items-center justify-center gap-2 mt-4">
                            <span class="material-symbols-outlined text-sm">save</span> 保存通知设置
                        </button>
                    </section>

                    <!-- API Settings -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">api</span>
                            API 设置
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary-light mb-1.5">服务提供商</label>
                                <select id="fx_provider" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-primary focus:border-primary block p-2.5">
                                    <option value="exchangerate-api.com" <?php echo ($settings['fx_provider'] ?? '') === 'exchangerate-api.com' ? 'selected' : ''; ?>>exchangerate-api.com (推荐，免费支持 CNY 基准)</option>
                                    <option value="apilayer.com" <?php echo ($settings['fx_provider'] ?? '') === 'apilayer.com' ? 'selected' : ''; ?>>apilayer.com (仅支持 EUR 基准)</option>
                                    <option value="fixer.io" <?php echo ($settings['fx_provider'] ?? '') === 'fixer.io' ? 'selected' : ''; ?>>fixer.io (仅支持 EUR 基准)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary-light mb-1.5">API Key</label>
                                <div class="relative">
                                    <input id="fx_api_key" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-primary focus:border-primary block p-2.5 pr-10" placeholder="当前渠道需要时再输入" type="password" value="" autocomplete="off"/>
                                    <button type="button" id="toggle-fx-api-key" onclick="toggleApiKeyVisibility()" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700" title="显示/隐藏 API Key">
                                        <span id="toggle-fx-api-key-icon" class="material-symbols-outlined text-[18px]">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-3 bg-blue-50 rounded-xl border border-blue-100">
                                <div class="flex gap-2">
                                    <span class="material-symbols-outlined text-blue-600 text-sm mt-0.5">info</span>
                                    <p class="text-xs text-blue-800 leading-relaxed">
                                        <strong>推荐 exchangerate-api.com：</strong>免费，每月 1500 次，支持 CNY 基准，无需注册。
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-2">
                                <div class="flex flex-wrap gap-3 text-xs">
                                    <a class="flex items-center gap-1 text-text-secondary-light hover:text-primary transition-colors" href="https://www.exchangerate-api.com" target="_blank">
                                        <span class="material-symbols-outlined text-xs">open_in_new</span> exchangerate-api
                                    </a>
                                    <a class="flex items-center gap-1 text-text-secondary-light hover:text-primary transition-colors" href="https://fixer.io" target="_blank">
                                        <span class="material-symbols-outlined text-xs">open_in_new</span> fixer.io
                                    </a>
                                    <a class="flex items-center gap-1 text-text-secondary-light hover:text-primary transition-colors" href="https://apilayer.com" target="_blank">
                                        <span class="material-symbols-outlined text-xs">open_in_new</span> apilayer
                                    </a>
                                </div>
                                <button onclick="saveApiSettings()" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-2 whitespace-nowrap">
                                    <span class="material-symbols-outlined text-sm">save</span> 保存配置
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Data Management -->
                    <section class="bg-card-light p-6 rounded-3xl shadow-soft">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">database</span>
                            数据管理
                        </h3>
                        <div class="flex gap-3">
                            <button onclick="importData()" class="flex-1 flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all group">
                                <span class="material-symbols-outlined text-gray-500 group-hover:text-primary mb-1">upload_file</span>
                                <span class="text-xs font-medium text-gray-700">导入数据</span>
                            </button>
                            <button onclick="exportData()" class="flex-1 flex flex-col items-center justify-center p-3 bg-primary hover:bg-primary-hover text-white rounded-xl shadow-lg transition-all">
                                <span class="material-symbols-outlined mb-1">download</span>
                                <span class="text-xs font-medium">导出数据</span>
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals Container -->
    <div id="modals">
        <!-- Add Category Modal -->
        <div id="modal-add-category" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">添加分类</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleAddCategory(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">分类名称</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="例如: 流媒体"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">图标</label>
                            <select name="icon" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="category">默认图标</option>
                                <option value="movie">电影</option>
                                <option value="music_note">音乐</option>
                                <option value="cloud">云服务</option>
                                <option value="code">开发工具</option>
                                <option value="gamepad">游戏</option>
                                <option value="fitness">健身</option>
                                <option value="school">教育</option>
                                <option value="news">新闻</option>
                                <option value="shopping_cart">购物</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">添加</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Currency Modal -->
        <div id="modal-add-currency" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">添加货币</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleAddCurrency(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">货币代码</label>
                                <input type="text" name="code" required maxlength="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary uppercase" placeholder="USD"/>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">符号</label>
                                <input type="text" name="symbol" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="$"/>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">货币名称</label>
                            <input type="text" name="name" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="美元"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">汇率 (相对于 CNY)</label>
                            <input type="number" name="rate_to_cny" step="0.0001" value="1" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">添加</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Payment Method Modal -->
        <div id="modal-add-payment" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">添加支付方式</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleAddPaymentMethod(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">支付方式名称</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="例如: 支付宝"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">图标</label>
                            <select name="icon" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="credit_card">信用卡</option>
                                <option value="account_balance_wallet">钱包</option>
                                <option value="account_balance">银行转账</option>
                                <option value="smartphone">手机支付</option>
                                <option value="code">加密货币</option>
                                <option value="contactless">NFC支付</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">添加</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Currency Modal -->
        <div id="modal-edit-currency" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">编辑货币</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleEditCurrency(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" id="edit-currency-id">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">货币代码</label>
                                <input type="text" name="code" id="edit-currency-code" required maxlength="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary uppercase" placeholder="USD"/>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">符号</label>
                                <input type="text" name="symbol" id="edit-currency-symbol" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="$"/>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">货币名称</label>
                            <input type="text" name="name" id="edit-currency-name" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="美元"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">汇率 (相对于 CNY)</label>
                            <input type="number" name="rate_to_cny" id="edit-currency-rate" step="0.0001" value="1" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">保存</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Payment Method Modal -->
        <div id="modal-edit-payment" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">编辑支付方式</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleEditPaymentMethod(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" id="edit-payment-id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">支付方式名称</label>
                            <input type="text" name="name" id="edit-payment-name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="例如: 支付宝"/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">保存</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div id="modal-change-password" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">修改密码</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleChangePassword(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">当前密码</label>
                            <input type="password" name="current_password" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">新密码</label>
                            <input type="password" name="new_password" required minlength="8" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">确认新密码</label>
                            <input type="password" name="confirm_password" required minlength="8" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                        </div>
                    </div>
                    <div id="password-message" class="mt-3 text-sm"></div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">修改</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Modal -->
        <div id="modal-import" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeModals()"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">导入数据</h3>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form onsubmit="handleImport(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="space-y-4">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center">
                            <span class="material-symbols-outlined text-4xl text-gray-400 mb-2">upload_file</span>
                            <p class="text-sm text-gray-600 mb-2">点击或拖拽上传 JSON 文件</p>
                            <input type="file" name="import_file" accept=".json" class="hidden" id="import-file-input" onchange="handleFileSelect(event)"/>
                            <button type="button" onclick="document.getElementById('import-file-input').click()" class="text-sm text-primary hover:underline">选择文件</button>
                            <p id="selected-file-name" class="text-sm text-gray-500 mt-2"></p>
                        </div>
                    </div>
                    <div id="import-message" class="mt-3 text-sm"></div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeModals()" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl">取消</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover">导入</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddCategoryModal() {
            document.getElementById('modal-add-category').classList.remove('hidden');
        }

        function showAddCurrencyModal() {
            document.getElementById('modal-add-currency').classList.remove('hidden');
        }

        function showAddPaymentMethodModal() {
            document.getElementById('modal-add-payment').classList.remove('hidden');
        }

        function showChangePasswordModal() {
            document.getElementById('modal-change-password').classList.remove('hidden');
            document.getElementById('password-message').innerHTML = '';
        }

        function showImportModal() {
            document.getElementById('modal-import').classList.remove('hidden');
        }

        function closeModals() {
            document.querySelectorAll('#modals > div[id^="modal-"]').forEach(modal => {
                modal.classList.add('hidden');
            });
            document.getElementById('selected-file-name').textContent = '';
            document.getElementById('import-message').innerHTML = '';
        }

        // Toggle switch handlers
        document.querySelectorAll('.toggle-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.nextElementSibling;
                if (this.checked) {
                    label.classList.remove('bg-gray-300');
                    label.classList.add('bg-primary');
                } else {
                    label.classList.remove('bg-primary');
                    label.classList.add('bg-gray-300');
                }

                // Save notification preference
                const type = this.id.replace('toggle_', '');
                saveNotificationSetting(type, this.checked);
            });
        });

        function saveNotificationSetting(type, enabled) {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            fetch('api/settings/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({type: type, enabled: enabled, csrf_token: csrfToken})
            });
        }

        // Category handlers
        async function handleAddCategory(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const res = await fetch('api/categories/add.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    closeModals();
                    location.reload();
                } else {
                    alert(data.message || '添加失败');
                }
            } catch (e) {
                alert('添加失败');
            }
        }

        function deleteCategory(id) {
            if (confirm('确定要删除此分类吗？相关的订阅将变为未分类状态。')) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fetch('api/categories/delete.php', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '删除失败');
                    }
                });
            }
        }

        // Currency handlers
        async function handleAddCurrency(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const res = await fetch('api/currencies/add.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    closeModals();
                    location.reload();
                } else {
                    alert(data.message || '添加失败');
                }
            } catch (e) {
                alert('添加失败');
            }
        }

        function deleteCurrency(id) {
            if (confirm('确定要删除此货币吗？')) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fetch('api/currencies/delete.php', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '删除失败');
                    }
                });
            }
        }

        function editCurrency(id) {
            // Get currency data
            const currencies = <?php echo json_encode($currencies); ?>;
            const currency = currencies.find(c => c.id == id);
            if (!currency) {
                alert('货币不存在');
                return;
            }

            // Fill form
            document.getElementById('edit-currency-id').value = currency.id;
            document.getElementById('edit-currency-code').value = currency.code;
            document.getElementById('edit-currency-symbol').value = currency.symbol;
            document.getElementById('edit-currency-name').value = currency.name;
            document.getElementById('edit-currency-rate').value = currency.rate_to_cny;

            // Show modal
            document.getElementById('modal-edit-currency').classList.remove('hidden');
        }

        async function handleEditCurrency(event) {
            event.preventDefault();
            const formData = new FormData(event.target);

            try {
                const response = await fetch('api/currencies/update.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || '更新失败');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('更新失败');
            }
        }

        // Notification settings are handled by the function at the bottom of the file

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.initNotificationPopover === 'function') {
                window.initNotificationPopover();
            }
        });

        // Payment method handlers
        async function handleAddPaymentMethod(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            try {
                const res = await fetch('api/payment-methods/add.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    closeModals();
                    location.reload();
                } else {
                    alert(data.message || '添加失败');
                }
            } catch (e) {
                alert('添加失败');
            }
        }

        function deletePaymentMethod(id) {
            if (confirm('确定要删除此支付方式吗？')) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                fetch('api/payment-methods/delete.php', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '删除失败');
                    }
                });
            }
        }

        function editPaymentMethod(id) {
            // Get payment method data
            const paymentMethods = <?php echo json_encode($paymentMethods); ?>;
            const pm = paymentMethods.find(p => p.id == id);
            if (!pm) {
                alert('支付方式不存在');
                return;
            }

            // Fill form
            document.getElementById('edit-payment-id').value = pm.id;
            document.getElementById('edit-payment-name').value = pm.name;

            // Show modal
            document.getElementById('modal-edit-payment').classList.remove('hidden');
        }

        async function handleEditPaymentMethod(event) {
            event.preventDefault();
            const formData = new FormData(event.target);

            try {
                const response = await fetch('api/payment-methods/update.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || '更新失败');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('更新失败');
            }
        }

        async function togglePaymentMethod(id, checkbox) {
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('id', id);

            try {
                const response = await fetch('api/payment-methods/toggle.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    // Update the status text
                    const statusText = checkbox.parentElement.nextElementSibling;
                    if (data.enabled) {
                        statusText.textContent = '已启用';
                        statusText.classList.remove('text-gray-500');
                        statusText.classList.add('text-green-600');
                    } else {
                        statusText.textContent = '已停用';
                        statusText.classList.remove('text-green-600');
                        statusText.classList.add('text-gray-500');
                    }
                } else {
                    alert(data.message || '操作失败');
                    checkbox.checked = !checkbox.checked; // Revert checkbox
                }
            } catch (error) {
                console.error('Error:', error);
                alert('操作失败');
                checkbox.checked = !checkbox.checked; // Revert checkbox
            }
        }

        // Password handlers
        async function handleChangePassword(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');

            if (newPassword !== confirmPassword) {
                document.getElementById('password-message').innerHTML = '<p class="text-red-500">两次输入的密码不一致</p>';
                return;
            }

            if (newPassword.length < 8) {
                document.getElementById('password-message').innerHTML = '<p class="text-red-500">新密码长度至少8位</p>';
                return;
            }

            try {
                const res = await fetch('api/auth/change-password.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('password-message').innerHTML = '<p class="text-green-500">密码修改成功</p>';
                    setTimeout(() => {
                        closeModals();
                        event.target.reset();
                    }, 1000);
                } else {
                    document.getElementById('password-message').innerHTML = '<p class="text-red-500">' + (data.message || '修改失败') + '</p>';
                }
            } catch (e) {
                document.getElementById('password-message').innerHTML = '<p class="text-red-500">修改失败</p>';
            }
        }

        // Import/Export handlers
        function exportData() {
            window.location.href = 'api/export.php';
        }

        function importData() {
            showImportModal();
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById('selected-file-name').textContent = file.name;
            }
        }

        async function handleImport(event) {
            event.preventDefault();
            const fileInput = document.getElementById('import-file-input');
            if (!fileInput.files[0]) {
                document.getElementById('import-message').innerHTML = '<p class="text-red-500">请选择文件</p>';
                return;
            }

            const formData = new FormData();
            formData.append('import_file', fileInput.files[0]);
            formData.append('csrf_token', document.querySelector('#modal-import input[name="csrf_token"]').value);

            try {
                const res = await fetch('api/import.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('import-message').innerHTML = '<p class="text-green-500">' + (data.message || '导入成功') + '</p>';
                    setTimeout(() => {
                        closeModals();
                        location.reload();
                    }, 1500);
                } else {
                    document.getElementById('import-message').innerHTML = '<p class="text-red-500">' + (data.message || '导入失败') + '</p>';
                }
            } catch (e) {
                document.getElementById('import-message').innerHTML = '<p class="text-red-500">导入失败</p>';
            }
        }

        // Exchange rate update handler
        async function updateExchangeRates() {
            const btn = document.getElementById('update-rates-btn');
            const originalContent = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span> 更新中...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                const res = await fetch('api/currencies/update-rates.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('exchange-rate-info').innerHTML =
                        '<span class="text-green-600">汇率已更新: ' + data.data.updated_count + ' 个货币</span>';
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    alert(data.message || '汇率更新失败');
                    btn.innerHTML = originalContent;
                }
            } catch (e) {
                alert('更新失败，请稍后重试');
                btn.innerHTML = originalContent;
            } finally {
                btn.disabled = false;
            }
        }

        // Test notification handler
        async function testNotification(type) {
            const labels = {
                'telegram': 'Telegram',
                'email': '邮箱'
            };

            if (!confirm('确定要发送' + labels[type] + '测试消息吗？')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('type', type);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                formData.append('telegram_token', document.querySelector('input[name="telegram_token"]')?.value || '');
                formData.append('telegram_chat_id', document.querySelector('input[name="telegram_chat_id"]')?.value || '');

                const res = await fetch('api/test-notification.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert(labels[type] + '测试成功: ' + data.message);
                } else {
                    alert(labels[type] + '测试失败: ' + data.message);
                }
            } catch (e) {
                alert('测试失败，请稍后重试');
            }
        }

        // Save notification settings
        async function saveNotificationSettings() {
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('notify_telegram', document.getElementById('toggle_telegram').checked ? '1' : '0');
            formData.append('notify_email', document.getElementById('toggle_email').checked ? '1' : '0');
            formData.append('telegram_token', document.querySelector('input[name="telegram_token"]').value || '');
            formData.append('telegram_chat_id', document.querySelector('input[name="telegram_chat_id"]').value || '');
            formData.append('email_host', document.querySelector('input[name="email_host"]').value || '');
            formData.append('email_port', document.querySelector('input[name="email_port"]').value || '587');
            formData.append('email_username', document.querySelector('input[name="email_username"]').value || '');
            formData.append('email_password', document.querySelector('input[name="email_password"]').value || '');
            formData.append('email_from', document.querySelector('input[name="email_from"]').value || '');
            formData.append('email_to', document.querySelector('input[name="email_to"]').value || '');

            try {
                const res = await fetch('api/settings/save-notifications.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert('通知设置已保存');
                } else {
                    alert(data.message || '保存失败');
                }
            } catch (e) {
                alert('保存失败，请稍后重试');
            }
        }

        // Save API settings
        const providerApiKeyConfigured = <?php echo json_encode($providerApiKeyConfigured, JSON_UNESCAPED_UNICODE); ?>;

        function setApiKeyFieldByProvider() {
            const provider = document.getElementById('fx_provider').value;
            const input = document.getElementById('fx_api_key');
            const configured = !!providerApiKeyConfigured[provider];

            input.value = '';

            if (provider === 'exchangerate-api.com') {
                input.placeholder = configured
                    ? '已配置，可留空保持不变'
                    : '该渠道默认可不填 API Key';
            } else {
                input.placeholder = configured
                    ? '已配置，留空则保持不变'
                    : '请输入该渠道 API Key';
            }
        }

        function toggleApiKeyVisibility() {
            const input = document.getElementById('fx_api_key');
            const icon = document.getElementById('toggle-fx-api-key-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const providerSelect = document.getElementById('fx_provider');
            if (providerSelect) {
                providerSelect.addEventListener('change', setApiKeyFieldByProvider);
                setApiKeyFieldByProvider();
            }
        });

        async function saveApiSettings() {
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('fx_provider', document.getElementById('fx_provider').value);
            formData.append('fx_api_key', document.getElementById('fx_api_key').value);

            try {
                const res = await fetch('api/settings/save-api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    const provider = document.getElementById('fx_provider').value;
                    if (document.getElementById('fx_api_key').value.trim() !== '') {
                        providerApiKeyConfigured[provider] = true;
                    }
                    setApiKeyFieldByProvider();
                    alert('API 设置已保存');
                } else {
                    alert(data.message || '保存失败');
                }
            } catch (e) {
                alert('保存失败，请稍后重试');
            }
        }
    </script>
</body>
</html>

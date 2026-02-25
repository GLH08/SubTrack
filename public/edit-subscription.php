<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

$pdo = getDbConnection();

// Get subscription ID
$subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch subscription data
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id");
$stmt->execute([':id' => $subscriptionId, ':user_id' => $_SESSION['user_id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header('Location: subscriptions.php');
    exit;
}

// Get form options
$stmt = $pdo->query("SELECT * FROM currencies ORDER BY code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM payment_methods ORDER BY name");
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current time for header
$currentTime = date('H:i');
$currentDate = date('Y年m月d日');

// Parse billing cycle
$intervalValue = $subscription['interval_value'] ?? 1;
$intervalUnit = $subscription['interval_unit'] ?? 'month';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>编辑订阅 - <?php echo htmlspecialchars($subscription['name']); ?> - SubTrack</title>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
        <link href="assets/css/app.tailwind.css" rel="stylesheet"/>
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
        .input-transition {
            transition: all 0.2s ease-in-out;
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
            <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="settings.php">
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
        <header class="flex items-center justify-between px-8 py-6 z-10 gap-8 shrink-0">
            <div class="flex items-center gap-8 flex-1">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">编辑订阅</h1>
                    <p class="text-text-secondary-light mt-1 text-sm">修改订阅服务信息</p>
                </div>
            </div>
            <div class="flex items-center gap-4 shrink-0">
                <button class="p-2 rounded-full bg-white shadow-sm text-gray-500 hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <div class="flex flex-col items-end justify-center px-4 py-1.5 bg-white/50 backdrop-blur-sm rounded-xl border border-white/20">
                    <span class="text-sm font-bold text-gray-900 tracking-wide"><?php echo $currentTime; ?></span>
                    <span class="text-xs text-text-secondary-light font-medium"><?php echo $currentDate; ?></span>
                </div>
            </div>
        </header>

        <!-- Form Content -->
        <div class="flex-1 overflow-y-auto z-10 px-8 pb-8">
            <form id="edit-form" class="max-w-4xl mx-auto space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="id" value="<?php echo $subscription['id']; ?>">

                <!-- Service Info Card -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="shrink-0 group relative self-center">
                            <div class="w-16 h-16 rounded-full bg-gray-50 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 hover:border-brand-blue hover:text-brand-blue transition-all cursor-pointer overflow-hidden" onclick="document.getElementById('logo-upload').click()">
                                <img id="logo-preview" class="w-full h-full object-cover <?php echo empty($subscription['logo_url']) ? 'hidden' : ''; ?>" src="<?php echo htmlspecialchars($subscription['logo_url'] ?? ''); ?>" alt="Logo preview">
                                <span id="logo-placeholder" class="material-symbols-outlined text-3xl <?php echo !empty($subscription['logo_url']) ? 'hidden' : ''; ?>">add_photo_alternate</span>
                            </div>
                            <input type="file" id="logo-upload" name="logo_file" accept="image/*" class="hidden" onchange="handleLogoUpload(this)"/>
                            <input type="hidden" name="logo_url" id="logo-url" value="<?php echo htmlspecialchars($subscription['logo_url'] ?? ''); ?>">
                        </div>
                        <div class="flex-1 space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider pl-1 block mb-1.5">服务名称</label>
                                <div class="relative group">
                                    <input class="input-transition block w-full rounded-2xl border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:bg-white focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 py-2.5 pl-10 sm:text-sm font-medium" name="name" placeholder="例如: Netflix" type="text" value="<?php echo htmlspecialchars($subscription['name'] ?? ''); ?>" required/>
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg group-focus-within:text-brand-blue transition-colors">search</span>
                                </div>
                            </div>
                            <div class="w-full sm:w-[40%]">
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider pl-1 block mb-1.5">图标链接（备选）</label>
                                <div class="relative group">
                                    <input class="input-transition block w-full rounded-2xl border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:bg-white focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 py-2.5 pl-10 sm:text-sm" name="logo_url_remote" placeholder="https://..." type="url" value="<?php echo htmlspecialchars($subscription['logo_url'] ?? ''); ?>"/>
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg group-focus-within:text-brand-blue transition-colors">link</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billing Info Card -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">credit_card</span>
                        付费信息
                    </h3>

                    <!-- Billing Cycle -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">付款周期</label>
                        <div class="flex items-center gap-4 w-full">
                            <div class="relative flex-1 min-w-0">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <span class="text-gray-500 text-sm font-medium">每</span>
                                </div>
                                <input class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 py-2.5 pl-10 pr-4 focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm font-semibold text-center" name="interval_value" value="<?php echo htmlspecialchars($intervalValue); ?>" min="1" max="366" type="number"/>
                            </div>
                            <div class="relative flex-1 min-w-0">
                                <select class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 py-2.5 pl-4 pr-10 focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm font-medium appearance-none cursor-pointer" name="interval_unit">
                                    <option value="month" <?php echo $intervalUnit === 'month' ? 'selected' : ''; ?>>月</option>
                                    <option value="year" <?php echo $intervalUnit === 'year' ? 'selected' : ''; ?>>年</option>
                                    <option value="week" <?php echo $intervalUnit === 'week' ? 'selected' : ''; ?>>周</option>
                                    <option value="day" <?php echo $intervalUnit === 'day' ? 'selected' : ''; ?>>日</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-lg pointer-events-none">expand_more</span>
                            </div>
                            <div class="flex items-center h-[42px] px-3 rounded-xl shrink-0">
                                <input class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary bg-gray-50 cursor-pointer" id="is_lifetime" name="is_lifetime" type="checkbox" <?php echo !empty($subscription['is_lifetime']) ? 'checked' : ''; ?>/>
                                <label class="ml-2 text-sm text-gray-600 font-medium cursor-pointer select-none" for="is_lifetime">一次性买断</label>
                            </div>
                        </div>
                    </div>

                    <!-- Amount, Payment Method, Category -->
                    <div class="w-full flex items-start gap-4 flex-wrap">
                        <div class="flex-1 min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">金额</label>
                            <div class="relative rounded-2xl shadow-subtle bg-white group focus-within:ring-[3px] focus-within:ring-brand-blue/15 transition-all">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-gray-500 font-bold text-sm">¥</span>
                                </div>
                                <input class="input-transition block w-full rounded-2xl border-gray-200 bg-transparent pl-8 pr-28 py-2.5 text-gray-900 placeholder-gray-300 focus:border-brand-blue focus:ring-0 sm:text-sm font-bold tracking-tight shadow-none z-10 relative" id="amount" name="amount" placeholder="0.00" type="text" value="<?php echo number_format($subscription['amount'] ?? 0, 2, '.', ''); ?>"/>
                                <div class="absolute inset-y-0 right-0 flex items-center z-20">
                                    <div class="relative h-full">
                                        <select class="h-full rounded-r-2xl border-0 bg-transparent py-0 pl-2 pr-8 text-gray-500 font-medium focus:ring-0 sm:text-xs text-right cursor-pointer appearance-none hover:text-gray-700 transition-colors" id="currency" name="currency_id">
                                            <?php foreach ($currencies as $curr): ?>
                                                <option value="<?php echo $curr['id']; ?>" <?php echo $curr['id'] == ($subscription['currency_id'] ?? 0) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($curr['code']); ?> (<?php echo htmlspecialchars($curr['symbol']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">expand_more</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">支付方式</label>
                            <div class="relative">
                                <select class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 py-2.5 pl-10 pr-8 focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm font-medium appearance-none cursor-pointer truncate" name="payment_method_id">
                                    <option value="">选择支付方式</option>
                                    <?php foreach ($paymentMethods as $pm): ?>
                                        <option value="<?php echo $pm['id']; ?>" <?php echo $pm['id'] == ($subscription['payment_method_id'] ?? 0) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pm['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none">account_balance_wallet</span>
                                <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 text-lg pointer-events-none">expand_more</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">分类</label>
                            <div class="relative">
                                <select class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 py-2.5 pl-10 pr-8 focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm font-medium appearance-none cursor-pointer truncate" name="category_id">
                                    <option value="">选择分类</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == ($subscription['category_id'] ?? 0) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none">category</span>
                                <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 text-lg pointer-events-none">expand_more</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billing Cycle Card -->
                <div class="bg-gray-50 rounded-3xl p-6 border border-gray-200 space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="p-2.5 bg-white rounded-2xl shadow-sm border border-gray-200 text-brand-blue">
                                <span class="material-symbols-outlined text-xl">autorenew</span>
                            </div>
                            <div>
                                <span class="block text-sm font-bold text-gray-900">自动续费</span>
                                <span class="block text-xs text-gray-500 font-medium mt-0.5">开启后自动计算下个账单日</span>
                            </div>
                        </div>
                        <button id="auto_renewal_toggle" type="button" class="<?php echo !empty($subscription['auto_renew']) ? 'bg-brand-blue' : 'bg-gray-200'; ?> relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2" role="switch" aria-checked="<?php echo !empty($subscription['auto_renew']) ? 'true' : 'false'; ?>">
                            <span id="auto_renewal_knob" aria-hidden="true" class="<?php echo !empty($subscription['auto_renew']) ? 'translate-x-5' : 'translate-x-0'; ?> pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                        <input type="hidden" name="auto_renew" id="auto_renewal_input" value="<?php echo !empty($subscription['auto_renew']) ? '1' : '0'; ?>"/>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 pl-1">开始日期</label>
                            <div class="relative">
                                <input class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 shadow-subtle focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm py-2.5 px-3" name="start_date" type="date" value="<?php echo htmlspecialchars($subscription['start_date'] ?? ''); ?>"/>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 pl-1">下次支付日期</label>
                            <div class="relative group">
                                <input class="block w-full rounded-2xl border-gray-200 bg-gray-100 text-gray-500 cursor-not-allowed shadow-none sm:text-sm py-2.5 px-3" readonly type="date" disabled/>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">lock</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">toggle_on</span>
                        订阅状态
                    </h3>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="active" class="w-4 h-4 text-primary focus:ring-primary border-gray-300" <?php echo ($subscription['status'] ?? '') === 'active' ? 'checked' : ''; ?>/>
                            <span class="text-sm font-medium text-gray-700">活跃</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="paused" class="w-4 h-4 text-primary focus:ring-primary border-gray-300" <?php echo ($subscription['status'] ?? '') === 'paused' ? 'checked' : ''; ?>/>
                            <span class="text-sm font-medium text-gray-700">已暂停</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="cancelled" class="w-4 h-4 text-primary focus:ring-primary border-gray-300" <?php echo ($subscription['status'] ?? '') === 'cancelled' ? 'checked' : ''; ?>/>
                            <span class="text-sm font-medium text-gray-700">已取消</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="expired" class="w-4 h-4 text-primary focus:ring-primary border-gray-300" <?php echo ($subscription['status'] ?? '') === 'expired' ? 'checked' : ''; ?>/>
                            <span class="text-sm font-medium text-gray-700">已过期</span>
                        </label>
                    </div>
                </div>

                <!-- Additional Info Card -->
                <div class="bg-card-light rounded-3xl p-8 shadow-soft space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">通知提醒</label>
                            <div class="flex gap-2">
                                <div class="relative flex-[2]">
                                    <select class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 shadow-subtle focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm py-2.5 pl-3 pr-10 appearance-none cursor-pointer" name="remind_days_before">
                                        <option value="0" <?php echo ($subscription['remind_days_before'] ?? 1) == 0 ? 'selected' : ''; ?>>不提醒</option>
                                        <option value="1" <?php echo ($subscription['remind_days_before'] ?? 1) == 1 ? 'selected' : ''; ?>>提前 1 天</option>
                                        <option value="2" <?php echo ($subscription['remind_days_before'] ?? 1) == 2 ? 'selected' : ''; ?>>提前 2 天</option>
                                        <option value="3" <?php echo ($subscription['remind_days_before'] ?? 1) == 3 ? 'selected' : ''; ?>>提前 3 天</option>
                                        <option value="7" <?php echo ($subscription['remind_days_before'] ?? 1) == 7 ? 'selected' : ''; ?>>提前 1 周</option>
                                        <option value="14" <?php echo ($subscription['remind_days_before'] ?? 1) == 14 ? 'selected' : ''; ?>>提前 2 周</option>
                                    </select>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-lg pointer-events-none">expand_more</span>
                                </div>
                                <div class="relative flex-1">
                                    <input class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 shadow-subtle focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm py-2.5 px-1 text-center cursor-pointer" name="remind_time" type="time" value="<?php echo htmlspecialchars($subscription['remind_time'] ?? '09:00'); ?>"/>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">官网链接</label>
                            <div class="relative group">
                                <input class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 placeholder-gray-400 shadow-subtle focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm py-2.5 pl-10" name="website_url" placeholder="https://" type="url" value="<?php echo htmlspecialchars($subscription['website_url'] ?? ''); ?>"/>
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg group-focus-within:text-brand-blue transition-colors">public</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">备注</label>
                        <textarea class="input-transition block w-full rounded-2xl border-gray-200 bg-white text-gray-900 placeholder-gray-400 shadow-subtle focus:border-brand-blue focus:ring-[3px] focus:ring-brand-blue/15 sm:text-sm py-3 px-4 resize-none" name="note" placeholder="添加账号信息、备忘录等..." rows="3"><?php echo htmlspecialchars($subscription['note'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-between">
                    <button type="button" onclick="if(confirm('确定要删除此订阅吗？')) { window.location.href = 'api/subscriptions/delete.php?id=<?php echo $subscription['id']; ?>'; }" class="px-6 py-2.5 rounded-2xl text-sm font-semibold text-red-600 hover:bg-red-50 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">delete</span> 删除订阅
                    </button>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="window.history.back()" class="px-6 py-2.5 rounded-2xl text-sm font-semibold text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-all">取消</button>
                        <button type="submit" class="px-6 py-2.5 rounded-2xl text-sm font-semibold bg-primary text-white hover:bg-gray-800 shadow-lg shadow-gray-200 hover:shadow-xl hover:-translate-y-0.5 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">check</span> 保存更改
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Auto renewal toggle
        const autoRenewalToggle = document.getElementById('auto_renewal_toggle');
        const autoRenewalKnob = document.getElementById('auto_renewal_knob');
        const autoRenewalInput = document.getElementById('auto_renewal_input');

        autoRenewalToggle.addEventListener('click', function() {
            const isOn = autoRenewalToggle.classList.contains('bg-brand-blue');
            autoRenewalToggle.setAttribute('aria-checked', !isOn);
            autoRenewalInput.value = isOn ? '0' : '1';

            if (isOn) {
                autoRenewalToggle.classList.remove('bg-brand-blue');
                autoRenewalToggle.classList.add('bg-gray-200');
                autoRenewalKnob.classList.remove('translate-x-5');
                autoRenewalKnob.classList.add('translate-x-0');
            } else {
                autoRenewalToggle.classList.remove('bg-gray-200');
                autoRenewalToggle.classList.add('bg-brand-blue');
                autoRenewalKnob.classList.remove('translate-x-0');
                autoRenewalKnob.classList.add('translate-x-5');
            }
        });

        // Calculate next payment date based on billing cycle
        function calculateNextPaymentDate() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const cycleValue = parseInt(document.querySelector('input[name="interval_value"]').value) || 1;
            const cycleType = document.querySelector('select[name="interval_unit"]').value;
            const oneTime = document.getElementById('is_lifetime').checked;

            if (!startDate || oneTime) {
                return;
            }

            const date = new Date(startDate);
            let nextDate = new Date(startDate);

            switch(cycleType) {
                case 'day':
                    nextDate.setDate(nextDate.getDate() + cycleValue);
                    break;
                case 'week':
                    nextDate.setDate(nextDate.getDate() + (cycleValue * 7));
                    break;
                case 'month':
                    nextDate.setMonth(nextDate.getMonth() + cycleValue);
                    break;
                case 'year':
                    nextDate.setFullYear(nextDate.getFullYear() + cycleValue);
                    break;
            }

            const nextPaymentField = document.querySelectorAll('input[type="date"]')[1];
            if (nextPaymentField) {
                nextPaymentField.value = nextDate.toISOString().split('T')[0];
            }
        }

        // Listen for changes
        document.querySelector('input[name="start_date"]').addEventListener('change', calculateNextPaymentDate);
        document.querySelector('input[name="interval_value"]').addEventListener('change', calculateNextPaymentDate);
        document.querySelector('select[name="interval_unit"]').addEventListener('change', calculateNextPaymentDate);
        document.getElementById('is_lifetime').addEventListener('change', function() {
            if (this.checked) {
                const nextPaymentField = document.querySelectorAll('input[type="date"]')[1];
                if (nextPaymentField) {
                    nextPaymentField.value = '';
                }
            }
        });

        // Logo upload handler
        async function handleLogoUpload(input) {
            const file = input.files[0];
            if (!file) return;

            // Show loading state
            const placeholder = document.getElementById('logo-placeholder');
            const preview = document.getElementById('logo-preview');

            // Preview the image
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);

            // Upload to server
            const formData = new FormData();
            formData.append('logo', file);
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('api/upload/logo.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('logo-url').value = data.data.logo_url;
                } else {
                    alert(data.message || 'Logo上传失败');
                }
            } catch (e) {
                console.error('Logo upload error:', e);
            }
        }

        // Form submission handler
        document.getElementById('edit-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span> 保存中...';
            
            try {
                const res = await fetch('api/subscriptions/update.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    // Redirect back to subscription detail page
                    window.location.href = 'subscription.php?id=<?php echo $subscription['id']; ?>&updated=1';
                } else {
                    alert(data.message || '更新失败');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Update error:', err);
                alert('网络错误，请重试');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>

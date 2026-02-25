<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

// Get current user data for stats
$pdo = getDbConnection();

// Get user's preferred display currency (default to CNY)
$stmt = $pdo->prepare("SELECT default_currency_id FROM settings WHERE user_id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$preferredCurrencyId = $stmt->fetchColumn();

// Get currency info
if ($preferredCurrencyId) {
    $stmt = $pdo->prepare("SELECT code, symbol, rate_to_cny FROM currencies WHERE id = :id");
    $stmt->execute([':id' => $preferredCurrencyId]);
    $displayCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Default to CNY
    $stmt = $pdo->query("SELECT code, symbol, rate_to_cny FROM currencies WHERE code = 'CNY'");
    $displayCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no display currency found, use USD as base
if (!$displayCurrency) {
    $displayCurrency = ['code' => 'CNY', 'symbol' => '¥', 'rate_to_cny' => 1];
}

$displayCode = $displayCurrency['code'];
$displaySymbol = $displayCurrency['symbol'];
$displayRate = (float) ($displayCurrency['rate_to_cny'] ?? 1);

// Get current month and year
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Get monthly estimated spending (current month)
// Normalize all subscriptions to monthly amount with currency conversion
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(
        CASE
            WHEN s.interval_unit = 'month' THEN s.amount * s.interval_value * c.rate_to_cny
            WHEN s.interval_unit = 'year' THEN s.amount * s.interval_value / 12.0 * c.rate_to_cny
            WHEN s.interval_unit = 'week' THEN s.amount * s.interval_value * 4.33 * c.rate_to_cny
            WHEN s.interval_unit = 'day' THEN s.amount * s.interval_value * 30 * c.rate_to_cny
            ELSE s.amount * c.rate_to_cny
        END
    ), 0) as monthly_total
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$monthlyTotal = $stmt->fetchColumn();

// Get yearly spending with mixed method:
// occurred payments in this year use locked historical settlement,
// not-yet-occurred months use current-rate forecast.
$currentYear = date('Y');
$yearlyActualCny = 0.0;
$currentMonthNumber = (int) date('n');
for ($m = 1; $m <= $currentMonthNumber; $m++) {
    $monthKey = sprintf('%04d-%02d', (int) $currentYear, $m);
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.amount_cny IS NOT NULL AND p.amount_cny > 0 THEN p.amount_cny
                ELSE p.amount * c.rate_to_cny
            END
        ), 0) as total
        FROM payments p
        JOIN subscriptions s ON p.subscription_id = s.id
        LEFT JOIN currencies c ON p.currency_id = c.id
        WHERE s.user_id = :user_id
        AND strftime('%Y-%m', p.paid_at) = :month
        AND p.status = 'success'
    ");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':month' => $monthKey
    ]);
    $yearlyActualCny += (float) $stmt->fetchColumn();
}
$yearlyForecastFutureCny = 0.0;
if ($currentMonthNumber < 12) {
    $stmt = $pdo->prepare("
        SELECT s.amount, s.interval_value, s.interval_unit, c.rate_to_cny
        FROM subscriptions s
        LEFT JOIN currencies c ON s.currency_id = c.id
        WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $subsForYearForecast = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subsForYearForecast as $sub) {
        $monthlyAmountCny = match ($sub['interval_unit']) {
            'month' => $sub['amount'] * $sub['interval_value'] * (float) ($sub['rate_to_cny'] ?? 1),
            'year' => $sub['amount'] * $sub['interval_value'] / 12.0 * (float) ($sub['rate_to_cny'] ?? 1),
            'week' => $sub['amount'] * $sub['interval_value'] * 4.33 * (float) ($sub['rate_to_cny'] ?? 1),
            'day' => $sub['amount'] * $sub['interval_value'] * 30 * (float) ($sub['rate_to_cny'] ?? 1),
            default => $sub['amount'] * (float) ($sub['rate_to_cny'] ?? 1)
        };
        $yearlyForecastFutureCny += $monthlyAmountCny * (12 - $currentMonthNumber);
    }
}
$yearlyTotal = $yearlyActualCny + $yearlyForecastFutureCny;

// Get active subscriptions count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_count
    FROM subscriptions
    WHERE user_id = :user_id AND status = 'active'
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$activeCount = $stmt->fetchColumn();

// Get subscriptions renewing in next 7 days
$today = date('Y-m-d');
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$stmt = $pdo->prepare("
    SELECT COUNT(*) as renewal_count
    FROM subscriptions
    WHERE user_id = :user_id
    AND status = 'active'
    AND next_payment_date BETWEEN :today AND :next_week
");
$stmt->execute([':user_id' => $_SESSION['user_id'], ':today' => $today, ':next_week' => $nextWeek]);
$renewalCount = $stmt->fetchColumn();

// Get recent/upcoming subscriptions
$stmt = $pdo->prepare("
    SELECT s.*, c.symbol as currency_symbol
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id AND s.status = 'active'
    ORDER BY s.next_payment_date ASC
    LIMIT 5
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$recentSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly spending history for chart (last 12 months, historical actual with locked FX)
$monthlyHistory = [];
$currentMonth = date('Y-m');
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    
    // Get actual payments for this month
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.amount_cny IS NOT NULL AND p.amount_cny > 0 THEN p.amount_cny
                ELSE p.amount * c.rate_to_cny
            END
        ), 0) as total
        FROM payments p
        JOIN subscriptions s ON p.subscription_id = s.id
        LEFT JOIN currencies c ON p.currency_id = c.id
        WHERE s.user_id = :user_id
        AND strftime('%Y-%m', p.paid_at) = :month
        AND p.status = 'success'
    ");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':month' => $month
    ]);
    $totalInCny = $stmt->fetchColumn();
    
    // For current month, add expected subscriptions that haven't generated payments yet
    if ($month === $currentMonth) {
        // Get all active subscriptions that haven't generated a payment this month yet
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(s.amount * c.rate_to_cny), 0) as expected
            FROM subscriptions s
            LEFT JOIN currencies c ON s.currency_id = c.id
            WHERE s.user_id = :user_id
            AND s.status = 'active'
            AND s.is_lifetime = 0
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.subscription_id = s.id 
                AND strftime('%Y-%m', p.paid_at) = :month
                AND p.status = 'success'
            )
        ");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':month' => $month
        ]);
        $expectedInCny = $stmt->fetchColumn();
        $totalInCny += $expectedInCny;
    }
    
    // Convert to display currency
    $displayAmount = $totalInCny / ($displayRate ?: 1);

    $monthlyHistory[] = [
        'month' => date('n月', strtotime($month)),
        'amount' => $displayAmount
    ];
}
$maxAmount = max(array_column($monthlyHistory, 'amount'));
if ($maxAmount == 0) $maxAmount = 1;

// Get category breakdown with currency conversion
$stmt = $pdo->prepare("
    SELECT
        cat.name as category_name,
        COALESCE(SUM(
            CASE
                WHEN s.interval_unit = 'month' THEN s.amount * s.interval_value * c.rate_to_cny
                WHEN s.interval_unit = 'year' THEN s.amount * s.interval_value / 12.0 * c.rate_to_cny
                WHEN s.interval_unit = 'week' THEN s.amount * s.interval_value * 4.33 * c.rate_to_cny
                WHEN s.interval_unit = 'day' THEN s.amount * s.interval_value * 30 * c.rate_to_cny
                ELSE s.amount * c.rate_to_cny
            END
        ), 0) as total_amount
    FROM subscriptions s
    LEFT JOIN categories cat ON s.category_id = cat.id
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
    GROUP BY cat.id
    ORDER BY total_amount DESC
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoryTotal = array_sum(array_column($categories, 'total_amount'));

// Get current time for header
$currentTime = date('H:i');
$currentDate = date('Y年m月d日');

// Calculate progress for monthly card (just for visual)
$budget = 1000;
$monthlyProgress = min(100, ($monthlyTotal / $budget) * 100);

// Helper function to format billing cycle
function formatBillingCycle($interval_value, $interval_unit) {
    $unitMap = [
        'day' => '天',
        'week' => '周',
        'month' => '月',
        'year' => '年'
    ];
    $unit = $unitMap[$interval_unit] ?? $interval_unit;
    if ($interval_value == 1) {
        return '每' . $unit;
    }
    return '每' . $interval_value . $unit;
}

function getAvatarFallbackColors(string $name): array {
    $palettes = [
        ['bg' => '#E2E8F0', 'text' => '#334155'],
        ['bg' => '#DBEAFE', 'text' => '#1D4ED8'],
        ['bg' => '#D1FAE5', 'text' => '#047857'],
        ['bg' => '#FDE68A', 'text' => '#B45309'],
        ['bg' => '#FFE4E6', 'text' => '#BE123C'],
    ];

    $hash = (int) sprintf('%u', crc32($name));

    return $palettes[$hash % count($palettes)];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>仪表盘 - SubTrack</title>
    <link href="assets/css/app.tailwind.css" rel="stylesheet"/>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
        <style>
        .nav-active-fallback {
            background-color: #111827;
            color: #ffffff;
        }
    </style>
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
        .pie-chart {
            background: conic-gradient(
                #111827 0% 35%,
                #6366f1 35% 60%,
                #f59e0b 60% 100%);
            border-radius: 50%;
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
                <a class="nav-active-fallback flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="dashboard.php">
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
        <header class="flex items-center justify-between px-8 py-6 z-10">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">总览</h1>
                <p class="text-text-secondary-light mt-1">欢迎回来，查看今日概况。</p>
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
        <div class="flex-1 overflow-y-auto px-8 pb-8 z-10 space-y-8 custom-scrollbar">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Monthly Estimate -->
                <div class="bg-card-light p-6 rounded-3xl shadow-soft hover:shadow-lg transition-shadow duration-300 group flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-orange-100 text-orange-600 rounded-xl">
                                <span class="material-symbols-outlined text-xl">calendar_today</span>
                            </div>
                            <span class="text-sm font-medium text-text-secondary-light">本月预估支出（当前汇率）</span>
                        </div>
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900">¥<?php echo number_format($monthlyTotal, 2); ?></h2>
                    </div>
                    <div class="mt-4 h-1 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-500 rounded-full" style="width: <?php echo $monthlyProgress; ?>%"></div>
                    </div>
                </div>

                <!-- Yearly Total -->
                <div class="bg-card-light p-6 rounded-3xl shadow-soft hover:shadow-lg transition-shadow duration-300 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-purple-100 text-purple-600 rounded-xl">
                                <span class="material-symbols-outlined text-xl">date_range</span>
                            </div>
                            <span class="text-sm font-medium text-text-secondary-light">本年度预估支出（混合口径）</span>
                        </div>
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900"><?php echo $displaySymbol . number_format($yearlyTotal / ($displayRate ?: 1), 2); ?></h2>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs font-medium text-green-600">
                        <span class="bg-green-100 px-2 py-0.5 rounded-md flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">trending_down</span> 2.4%
                        </span>
                        <span class="text-text-secondary-light">同比去年</span>
                    </div>
                </div>

                <!-- Active Subscriptions -->
                <div class="bg-card-light p-6 rounded-3xl shadow-soft hover:shadow-lg transition-shadow duration-300 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-xl">
                                <span class="material-symbols-outlined text-xl">apps</span>
                            </div>
                            <span class="text-sm font-medium text-text-secondary-light">活跃订阅</span>
                        </div>
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900"><?php echo $activeCount; ?></h2>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex -space-x-3 overflow-hidden p-1">
                            <?php foreach (array_slice($recentSubscriptions, 0, 5) as $sub): ?>
                                <?php $fallbackColors = getAvatarFallbackColors((string) ($sub['name'] ?? '')); ?>
                                <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white bg-white overflow-hidden" title="<?php echo htmlspecialchars($sub['name']); ?>">
                                    <?php if (!empty($sub['logo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($sub['logo_url']); ?>" alt="" class="w-full h-full object-cover"/>
                                    <?php else: ?>
                                        <?php $initial = mb_strtoupper(mb_substr($sub['name'], 0, 1)); ?>
                                        <div class="w-full h-full flex items-center justify-center text-xs font-bold" style="background-color: <?php echo htmlspecialchars($fallbackColors['bg'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($fallbackColors['text'], ENT_QUOTES, 'UTF-8'); ?>;">
                                            <?php echo $initial; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($recentSubscriptions) > 5): ?>
                                <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white bg-gray-200 flex items-center justify-center text-gray-600 text-xs font-bold">
                                    +<?php echo count($recentSubscriptions) - 5; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="subscriptions.php" class="material-symbols-outlined text-gray-400 hover:text-primary transition-colors cursor-pointer">arrow_forward</a>
                    </div>
                </div>

                <!-- Renewals in 7 days -->
                <div class="bg-card-light p-6 rounded-3xl shadow-soft hover:shadow-lg transition-shadow duration-300 relative overflow-hidden flex flex-col justify-between h-full">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-red-100 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-red-100 text-red-600 rounded-xl">
                                <span class="material-symbols-outlined text-xl">notifications_active</span>
                            </div>
                            <span class="text-sm font-medium text-text-secondary-light">7天内续费</span>
                        </div>
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900"><?php echo $renewalCount; ?></h2>
                    </div>
                    <div class="mt-4 flex items-center justify-between relative z-10">
                        <div class="flex -space-x-2 overflow-hidden">
                            <?php
                            // Get upcoming renewals for avatar display
                            $stmt = $pdo->prepare("
                                SELECT s.*, c.symbol as currency_symbol
                                FROM subscriptions s
                                LEFT JOIN currencies c ON s.currency_id = c.id
                                WHERE s.user_id = :user_id AND s.status = 'active'
                                AND s.next_payment_date BETWEEN :today AND :next_week
                                LIMIT 3
                            ");
                            $stmt->execute([':user_id' => $_SESSION['user_id'], ':today' => $today, ':next_week' => $nextWeek]);
                            $upcomingRenewals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($upcomingRenewals as $sub):
                                $fallbackColors = getAvatarFallbackColors((string) ($sub['name'] ?? ''));
                            ?>
                                <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white bg-white overflow-hidden" title="<?php echo htmlspecialchars($sub['name']); ?>">
                                    <?php if (!empty($sub['logo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($sub['logo_url']); ?>" alt="" class="w-full h-full object-cover"/>
                                    <?php else: ?>
                                        <?php $initial = mb_strtoupper(mb_substr($sub['name'], 0, 1)); ?>
                                        <div class="w-full h-full flex items-center justify-center text-[10px] font-bold" style="background-color: <?php echo htmlspecialchars($fallbackColors['bg'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($fallbackColors['text'], ENT_QUOTES, 'UTF-8'); ?>;">
                                            <?php echo $initial; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="calendar.php" class="material-symbols-outlined text-gray-400 hover:text-primary transition-colors cursor-pointer">arrow_forward</a>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Spending Trend Chart -->
                <div class="lg:col-span-2 bg-card-light p-8 rounded-3xl shadow-soft">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">支出趋势</h3>
                            <p class="text-sm text-text-secondary-light">过去12个月历史实际（锁定汇率）</p>
                        </div>
                    </div>
                    <div class="flex items-end justify-between gap-2 h-48 w-full px-2">
                        <?php foreach ($monthlyHistory as $index => $data): ?>
                            <?php
                            $heightPercent = ($data['amount'] / $maxAmount) * 100;
                            $isCurrentMonth = $index === 11;
                            ?>
                            <div class="h-full flex flex-col items-center justify-end gap-2 flex-1">
                                <div class="w-full <?php echo $isCurrentMonth ? 'bg-green-600 rounded-t-lg shadow-lg shadow-green-500/30 relative' : 'bg-green-200 rounded-t-lg relative hover:bg-green-300 transition-colors'; ?>" style="height: <?php echo max(5, $heightPercent); ?>%; min-height: 4px;">
                                    <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-black text-white text-[10px] px-2 py-1 rounded whitespace-nowrap <?php echo $isCurrentMonth ? 'shadow-lg' : ''; ?>">¥<?php echo number_format($data['amount'], 0); ?></div>
                                </div>
                                <span class="text-xs <?php echo $isCurrentMonth ? 'font-bold text-green-600' : 'text-gray-500'; ?> shrink-0"><?php echo $data['month']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Pie Chart -->
                <div class="bg-card-light p-8 rounded-3xl shadow-soft flex flex-col items-center justify-center">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 self-start w-full">分类占比</h3>
                    <div class="flex items-center gap-8">
                        <?php
                        // Generate pie chart colors
                        $colors = ['#16a34a', '#22c55e', '#4ade80', '#86efac', '#facc15', '#f59e0b', '#0ea5e9', '#6366f1'];
                        $conicGradient = '';
                        $currentPercent = 0;
                        foreach ($categories as $index => $cat) {
                            $percent = $categoryTotal > 0 ? ($cat['total_amount'] / $categoryTotal) * 100 : 0;
                            $nextPercent = $currentPercent + $percent;
                            $conicGradient .= $colors[$index % count($colors)] . ' ' . $currentPercent . '% ' . $nextPercent . '%,';
                            $currentPercent = $nextPercent;
                        }
                        if ($conicGradient) {
                            $conicGradient = rtrim($conicGradient, ',');
                        } else {
                            $conicGradient = '#e5e7eb 0% 100%';
                        }
                        ?>
                        <div class="pie-chart w-40 h-40 shadow-inner relative" style="background: conic-gradient(<?php echo $conicGradient; ?>);">
                            <div class="absolute inset-0 m-auto w-24 h-24 bg-card-light rounded-full flex items-center justify-center flex-col">
                                <span class="text-xs text-gray-400">总计</span>
                                <span class="font-bold text-gray-900">¥<?php echo $categoryTotal >= 1000 ? number_format($categoryTotal / 1000, 1) . 'k' : number_format($categoryTotal, 0); ?></span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3">
                            <?php foreach ($categories as $index => $cat): ?>
                                <?php $percent = $categoryTotal > 0 ? round(($cat['total_amount'] / $categoryTotal) * 100) : 0; ?>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" style="background-color: <?php echo $colors[$index % count($colors)]; ?>"></div>
                                    <span class="text-sm font-medium text-text-secondary-light"><?php echo htmlspecialchars($cat['category_name'] ?? '未分类'); ?> (<?php echo $percent; ?>%)</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <div class="text-sm text-text-secondary-light">暂无分类数据</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <section class="bg-card-light rounded-3xl shadow-soft overflow-hidden">
                <div class="p-8 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">近期活动</h3>
                    <button onclick="window.location.href='add-subscription.php'" class="bg-primary text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-primary-hover transition-colors flex items-center gap-2 shadow-lg shadow-gray-300/50">
                        <span class="material-symbols-outlined text-sm">add</span> 新增订阅
                    </button>
                </div>
                <div class="p-4">
                    <?php if (empty($recentSubscriptions)): ?>
                        <div class="text-center py-12 text-gray-400">
                            <span class="material-symbols-outlined text-5xl mb-2">credit_card</span>
                            <p class="text-lg">暂无订阅</p>
                            <p class="text-sm mt-1">添加您的第一个订阅开始追踪</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentSubscriptions as $sub): ?>
                            <?php
                            // Calculate days until renewal
                            $renewalDate = new DateTime($sub['next_payment_date']);
                            $today = new DateTime();
                            $daysDiff = $today->diff($renewalDate)->days;
                            $isFuture = $renewalDate > $today;
                            $statusDot = $isFuture ? ($daysDiff <= 7 ? 'bg-orange-500' : 'bg-gray-400') : 'bg-green-500';
                            $statusText = $isFuture ? ($daysDiff == 0 ? '今日续费' : ($daysDiff == 1 ? '明天续费' : $daysDiff . '天后')) : '已续费';
                            $billingCycleText = formatBillingCycle($sub['interval_value'] ?? 1, $sub['interval_unit'] ?? 'month');
                            ?>
                            <a href="subscription.php?id=<?php echo $sub['id']; ?>" class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-2xl transition-colors group cursor-pointer">
                                <div class="flex items-center gap-4">
                                    <div class="h-12 w-12 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600 shrink-0">
                                        <?php if (!empty($sub['logo_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($sub['logo_url']); ?>" alt="" class="w-8 h-8 object-contain"/>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-2xl">credit_card</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($sub['name']); ?></h4>
                                        <p class="text-sm text-text-secondary-light flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full <?php echo $statusDot; ?>"></span>
                                            <?php echo $statusText; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-gray-900"><?php echo $sub['currency_symbol']; ?><?php echo number_format($sub['amount'], 2); ?></p>
                                    <div class="text-xs text-text-secondary-light flex items-center justify-end gap-1">
                                        <?php echo $billingCycleText; ?>
                                        <span class="material-symbols-outlined text-sm">cached</span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
    <script src="assets/js/notification-popover.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.initNotificationPopover === 'function') {
                window.initNotificationPopover();
            }
        });
    </script>
</body>
</html>

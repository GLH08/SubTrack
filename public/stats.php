<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

// Get statistics data
$pdo = getDbConnection();

// Helper function to convert amount to monthly equivalent
function getMonthlyAmount(float $amount, string $intervalUnit, int $intervalValue): float {
    switch ($intervalUnit) {
        case 'day':
            return $amount * 30 * $intervalValue;
        case 'week':
            return $amount * 4.33 * $intervalValue;
        case 'month':
            return $amount * $intervalValue;
        case 'year':
            return $amount / 12 * $intervalValue;
        default:
            return $amount;
    }
}

// Helper function to convert amount to yearly equivalent
function getYearlyAmount(float $amount, string $intervalUnit, int $intervalValue): float {
    switch ($intervalUnit) {
        case 'day':
            return $amount * 365 * $intervalValue;
        case 'week':
            return $amount * 52 * $intervalValue;
        case 'month':
            return $amount * 12 * $intervalValue;
        case 'year':
            return $amount * $intervalValue;
        default:
            return $amount;
    }
}

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

// Helper function to convert amount to display currency
function convertToDisplayCurrency(float $amount, float $originalRate, float $displayRate): float {
    // First convert to CNY (base), then to display currency
    // originalRate is "How many CNY is 1 Unit of Original Currency" (e.g. USD = 7.2)
    // displayRate is "How many CNY is 1 Unit of Display Currency" (e.g. EUR = 8.0)

    $cnyAmount = $amount * $originalRate;
    return $cnyAmount / ($displayRate ?: 1);
}

$getMonthActualTotalInCny = function (string $month) use ($pdo): float {
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

    return (float) $stmt->fetchColumn();
};

// Get monthly spending (normalized to monthly and converted to display currency)
$stmt = $pdo->prepare("
    SELECT s.amount, s.interval_value, s.interval_unit, c.rate_to_cny
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthlyTotal = 0;
foreach ($subscriptions as $sub) {
    // Calculate monthly amount in original currency
    $monthlyAmount = match ($sub['interval_unit']) {
        'day' => $sub['amount'] * 30 * $sub['interval_value'],
        'week' => $sub['amount'] * 4.33 * $sub['interval_value'],
        'month' => $sub['amount'] * $sub['interval_value'],
        'year' => $sub['amount'] * $sub['interval_value'] / 12.0,
        default => $sub['amount']
    };
    // Convert to display currency
    $originalRate = (float) ($sub['rate_to_cny'] ?? 1);
    $monthlyTotal += convertToDisplayCurrency($monthlyAmount, $originalRate, $displayRate);
}

// Get yearly spending with mixed method:
// occurred payments in this year use locked historical settlement,
// not-yet-occurred months use current-rate forecast.
$currentYear = date('Y');
$yearlyActualCny = 0.0;
$currentMonthNumber = (int) date('n');
for ($m = 1; $m <= $currentMonthNumber; $m++) {
    $yearlyActualCny += $getMonthActualTotalInCny(sprintf('%04d-%02d', (int) $currentYear, $m));
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
            'day' => $sub['amount'] * 30 * $sub['interval_value'] * (float) ($sub['rate_to_cny'] ?? 1),
            'week' => $sub['amount'] * 4.33 * $sub['interval_value'] * (float) ($sub['rate_to_cny'] ?? 1),
            'month' => $sub['amount'] * $sub['interval_value'] * (float) ($sub['rate_to_cny'] ?? 1),
            'year' => $sub['amount'] * $sub['interval_value'] / 12.0 * (float) ($sub['rate_to_cny'] ?? 1),
            default => $sub['amount'] * (float) ($sub['rate_to_cny'] ?? 1)
        };

        $yearlyForecastFutureCny += $monthlyAmountCny * (12 - $currentMonthNumber);
    }
}
$yearlyTotal = ($yearlyActualCny + $yearlyForecastFutureCny) / ($displayRate ?: 1);

$currentMonth = date('Y-m');

// Get pending payments in current month (forecast only, not included in historical actual)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(s.amount * c.rate_to_cny), 0) as pending
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id
    AND s.status = 'active'
    AND s.is_lifetime = 0
    AND s.next_payment_date IS NOT NULL
    AND strftime('%Y-%m', s.next_payment_date) = :month
    AND NOT EXISTS (
        SELECT 1 FROM payments p
        WHERE p.subscription_id = s.id
        AND strftime('%Y-%m', p.paid_at) = :month
        AND p.status = 'success'
    )
");
$stmt->execute([
    ':user_id' => $_SESSION['user_id'],
    ':month' => $currentMonth
]);
$pendingCurrentMonthCny = (float) $stmt->fetchColumn();
$pendingCurrentMonth = $pendingCurrentMonthCny / ($displayRate ?: 1);

// Chart range mode
$view = $_GET['view'] ?? '12m';
$allowedViews = ['12m', '6m', 'year'];
if (!in_array($view, $allowedViews, true)) {
    $view = '12m';
}

$historyMonths = match ($view) {
    '6m' => 6,
    'year' => 12,
    default => 12,
};
$viewLabel = match ($view) {
    '6m' => '近6个月',
    'year' => '年度',
    default => '近12个月',
};

// Get monthly history for chart (historical actual)
$monthlyHistory = [];
$currentMonth = date('Y-m');

if ($view === 'year') {
    $selectedYear = (int) date('Y');
    for ($m = 1; $m <= 12; $m++) {
        $month = sprintf('%04d-%02d', $selectedYear, $m);
        $totalInCny = $getMonthActualTotalInCny($month);
        $displayAmount = $totalInCny / ($displayRate ?: 1);

        $monthlyHistory[] = [
            'month' => $m . '月',
            'amount' => $displayAmount,
            'raw_date' => $month
        ];
    }
} else {
    for ($i = $historyMonths - 1; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));

        $totalInCny = $getMonthActualTotalInCny($month);

        // 历史实际口径：不叠加本月尚未发生的预测账单

        // Convert to display currency
        $displayAmount = $totalInCny / ($displayRate ?: 1);

        $monthlyHistory[] = [
            'month' => date('n月', strtotime($month)),
            'amount' => $displayAmount,
            'raw_date' => $month
        ];
    }
}

$historyCount = count($monthlyHistory);
$currentMonthVal = $monthlyHistory[$historyCount - 1]['amount'] ?? 0;
$lastMonthVal = $historyCount >= 2 ? ($monthlyHistory[$historyCount - 2]['amount'] ?? 0) : 0;

// If history calculation differs from $monthlyTotal (snapshot vs history loop), prefer $monthlyTotal for current display
// but use history for trend.
// Actually $monthlyTotal (lines 83-96) is logically same as $monthlyHistory[last] except for date check.
// Let's use $monthlyHistory values for consistency in growth rate.

$growthRate = 0;
if ($lastMonthVal > 0) {
    $growthRate = (($currentMonthVal - $lastMonthVal) / $lastMonthVal) * 100;
} else if ($currentMonthVal > 0) {
    $growthRate = 100; // 0 to something is 100% growth effectively (or infinite)
}
$maxAmount = !empty($monthlyHistory) ? max(array_column($monthlyHistory, 'amount')) : 1;
if ($maxAmount == 0) $maxAmount = 1;

// Get category breakdown (with currency conversion)
$stmt = $pdo->prepare("
    SELECT
        cat.id,
        cat.name as category_name,
        cat.color as category_color,
        s.amount,
        s.interval_value,
        s.interval_unit,
        c.rate_to_cny
    FROM subscriptions s
    LEFT JOIN categories cat ON s.category_id = cat.id
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$categorySubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category and calculate totals
$categoryMap = [];
foreach ($categorySubs as $sub) {
    $catId = $sub['id'] ?? 0;
    if (!isset($categoryMap[$catId])) {
        $categoryMap[$catId] = [
            'id' => $catId,
            'category_name' => $sub['category_name'] ?: '未分类',
            'category_icon' => 'folder',
            'category_color' => $sub['category_color'] ?? '#6b7280',
            'total_amount' => 0
        ];
    }

    $monthlyAmount = match ($sub['interval_unit']) {
        'day' => $sub['amount'] * 30 * $sub['interval_value'],
        'week' => $sub['amount'] * 4.33 * $sub['interval_value'],
        'month' => $sub['amount'] * $sub['interval_value'],
        'year' => $sub['amount'] * $sub['interval_value'] / 12.0,
        default => $sub['amount']
    };
    $originalRate = (float) ($sub['rate_to_cny'] ?? 1);
    $categoryMap[$catId]['total_amount'] += convertToDisplayCurrency($monthlyAmount, $originalRate, $displayRate);
}

// Sort by total amount
usort($categoryMap, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);
$categories = array_values($categoryMap);
$categoryTotal = array_sum(array_column($categories, 'total_amount'));

// Get top subscriptions (with currency conversion)
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.name,
        s.amount,
        s.interval_value,
        s.interval_unit,
        c.symbol as currency_symbol,
        c.rate_to_cny,
        cat.color as category_color
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    LEFT JOIN categories cat ON s.category_id = cat.id
    WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$topSubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate monthly amounts for each subscription
$topSubscriptions = [];
foreach ($topSubs as $sub) {
    $monthlyAmount = match ($sub['interval_unit']) {
        'day' => $sub['amount'] * 30 * $sub['interval_value'],
        'week' => $sub['amount'] * 4.33 * $sub['interval_value'],
        'month' => $sub['amount'] * $sub['interval_value'],
        'year' => $sub['amount'] * $sub['interval_value'] / 12.0,
        default => $sub['amount']
    };
    $originalRate = (float) ($sub['rate_to_cny'] ?? 1);
    $monthlyAmountConverted = convertToDisplayCurrency($monthlyAmount, $originalRate, $displayRate);

    $topSubscriptions[] = [
        'id' => $sub['id'],
        'name' => $sub['name'],
        'amount' => $sub['amount'],
        'currency_symbol' => $sub['currency_symbol'],
        'category_icon' => 'credit_card',
        'category_color' => $sub['category_color'],
        'monthly_amount' => $monthlyAmountConverted
    ];
}

// Sort by monthly amount
usort($topSubscriptions, fn($a, $b) => $b['monthly_amount'] <=> $a['monthly_amount']);
$topSubscriptions = array_slice($topSubscriptions, 0, 5);
$topMaxAmount = !empty($topSubscriptions) ? max(array_column($topSubscriptions, 'monthly_amount')) : 1;
if ($topMaxAmount == 0) $topMaxAmount = 1;

// Get currency distribution (converted to display currency)
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.code as currency_code,
        c.name as currency_name,
        c.symbol as currency_symbol,
        c.rate_to_cny,
        s.amount,
        s.interval_value,
        s.interval_unit
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id AND s.status = 'active' AND s.is_lifetime = 0
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$currencySubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by currency
$currencyMap = [];
foreach ($currencySubs as $sub) {
    $currId = $sub['id'] ?? 0;
    if (!isset($currencyMap[$currId])) {
        $currencyMap[$currId] = [
            'id' => $currId,
            'currency_code' => $sub['currency_code'] ?? 'USD',
            'currency_name' => $sub['currency_name'] ?? 'US Dollar',
            'currency_symbol' => $sub['currency_symbol'] ?? '$',
            'total_amount' => 0
        ];
    }

    $monthlyAmount = match ($sub['interval_unit']) {
        'day' => $sub['amount'] * 30 * $sub['interval_value'],
        'week' => $sub['amount'] * 4.33 * $sub['interval_value'],
        'month' => $sub['amount'] * $sub['interval_value'],
        'year' => $sub['amount'] * $sub['interval_value'] / 12.0,
        default => $sub['amount']
    };
    $originalRate = (float) ($sub['rate_to_cny'] ?? 1);
    $currencyMap[$currId]['total_amount'] += convertToDisplayCurrency($monthlyAmount, $originalRate, $displayRate);
}

$currencies = array_values($currencyMap);
$currencyTotal = array_sum(array_column($currencies, 'total_amount'));

// Get active subscription count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = :user_id AND status = 'active'");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$activeCount = $stmt->fetchColumn();

// Get lifetime subscriptions count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = :user_id AND is_lifetime = 1");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$lifetimeCount = $stmt->fetchColumn();

// Get current time for header
$currentTime = date('H:i');
$currentDate = date('Y年m月d日');

// Format numbers for display
$monthlyDisplay = $displaySymbol . number_format($monthlyTotal, 2);
$yearlyDisplay = $displaySymbol . number_format($yearlyTotal, 2);
$pendingCurrentMonthDisplay = $displaySymbol . number_format($pendingCurrentMonth, 2);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>统计分析 - SubTrack</title>
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
        .pie-chart {
            background: conic-gradient(
                #3b82f6 0% 35%, #8b5cf6 35% 60%, #10b981 60% 80%, #f59e0b 80% 92%, #ef4444 92% 100%);
        }
        .currency-chart {
            background: conic-gradient(
                #60a5fa 0% 45%, #fb7185 45% 75%, #34d399 75% 100%);
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
                <a class="flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="stats.php">
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
    <main class="flex-1 flex flex-col overflow-hidden relative h-full">
        <!-- Background gradient -->
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-b from-gray-200/50 to-transparent pointer-events-none z-0"></div>

        <!-- Header -->
        <header class="flex items-center justify-between px-8 py-6 z-10 gap-8 shrink-0">
            <div class="flex items-center gap-8 flex-1">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">统计分析</h1>
                    <p class="text-text-secondary-light mt-1 text-sm">深入了解您的订阅支出情况与趋势（当前视图：<?php echo $viewLabel; ?>）</p>
                </div>
            </div>
            <div class="flex items-center gap-4 shrink-0">
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
        <div class="flex-1 overflow-y-auto z-10 px-8 pb-8">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <!-- Monthly Total -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center justify-between group hover:border-blue-200 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-text-secondary-light mb-1">本月总支出（历史实际）</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $monthlyDisplay; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                    </div>

                    <!-- Yearly Total -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center justify-between group hover:border-purple-200 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-text-secondary-light mb-1">年度预估支出（混合口径）</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $yearlyDisplay; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined">calendar_today</span>
                        </div>
                    </div>

                    <!-- Pending Current Month -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center justify-between group hover:border-amber-200 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-text-secondary-light mb-1">本月待发生（预测）</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $pendingCurrentMonthDisplay; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined">schedule</span>
                        </div>
                    </div>

                    <!-- Growth Rate -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center justify-between group hover:border-green-200 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-text-secondary-light mb-1 whitespace-nowrap">环比增长（<?php echo $viewLabel; ?>）</p>
                            <div class="flex items-center gap-2">
                                <h3 class="text-2xl font-bold text-gray-900"><?php echo $growthRate >= 0 ? '+' : ''; ?><?php echo number_format(abs($growthRate), 1); ?>%</h3>
                                <span class="text-xs font-medium <?php echo $growthRate >= 0 ? 'text-red-500 bg-red-50' : 'text-green-500 bg-green-50'; ?> px-1.5 py-0.5 rounded flex items-center">
                                    <span class="material-symbols-outlined text-[14px]"><?php echo $growthRate >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                                </span>
                            </div>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-600 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined">monitoring</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Spending Trend Chart -->
                    <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col">
                        <div class="flex items-center justify-between mb-6 gap-3">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2 whitespace-nowrap">
                                <span class="w-1 h-5 bg-blue-500 rounded-full"></span>
                                支出走势图（历史实际，<?php echo $viewLabel; ?>）
                            </h3>
                            <div class="bg-gray-100 p-1 rounded-lg flex items-center">
                                <a href="?view=12m" class="px-3 py-1 text-xs font-medium rounded-md transition-all <?php echo $view === '12m' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'; ?>">近 12 个月</a>
                                <a href="?view=6m" class="px-3 py-1 text-xs font-medium rounded-md transition-all <?php echo $view === '6m' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'; ?>">近 6 个月</a>
                                <a href="?view=year" class="px-3 py-1 text-xs font-medium rounded-md transition-all <?php echo $view === 'year' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'; ?>">年度</a>
                            </div>
                        </div>
                        <div class="flex-1 relative min-h-[320px] flex items-end justify-between px-2 pt-8 pb-6 border-b border-gray-100">
                            <!-- Y-axis labels -->
                            <div class="absolute inset-y-0 left-0 bottom-6 flex flex-col justify-between text-xs font-medium text-gray-400 pr-2 pointer-events-none w-10 text-right z-10 pt-8">
                                <span><?php echo $maxAmount >= 1000 ? (round($maxAmount / 1000) . 'k') : round($maxAmount); ?></span>
                                <span><?php echo $maxAmount >= 1000 ? (round($maxAmount * 0.67 / 1000) . 'k') : round($maxAmount * 0.67); ?></span>
                                <span><?php echo $maxAmount >= 1000 ? (round($maxAmount * 0.33 / 1000) . 'k') : round($maxAmount * 0.33); ?></span>
                                <span>0</span>
                            </div>

                            <!-- Grid lines -->
                            <div class="absolute inset-0 left-12 bottom-6 pt-8 flex flex-col justify-between pointer-events-none">
                                <div class="border-b border-dashed border-gray-100 h-0 w-full"></div>
                                <div class="border-b border-dashed border-gray-100 h-0 w-full"></div>
                                <div class="border-b border-dashed border-gray-100 h-0 w-full"></div>
                                <div class="border-b border-gray-200 h-0 w-full"></div>
                            </div>

                            <!-- Bar chart -->
                            <div class="relative z-10 w-full flex justify-between items-end h-full gap-2 lg:gap-3 pl-12">
                            <?php foreach ($monthlyHistory as $index => $data): ?>
                                    <?php
                                    $heightPercent = ($data['amount'] / $maxAmount) * 100;
                                    $isCurrentMonth = $index === ($historyCount - 1);
                                    ?>
                                    <div class="h-full flex flex-col items-center justify-end flex-1">
                                        <div class="w-full max-w-[28px] <?php echo $isCurrentMonth ? 'bg-green-600 rounded-t-sm relative shadow-lg shadow-green-500/20' : 'bg-green-300 rounded-t-sm relative hover:bg-green-400 transition-colors'; ?>" style="height: <?php echo max(5, $heightPercent); ?>%">
                                            <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-primary text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-20 <?php echo $isCurrentMonth ? 'font-bold' : ''; ?>">
                                                <?php echo $data['amount'] >= 1000 ? (number_format($data['amount'] / 1000, 1) . 'k') : number_format($data['amount'], 0); ?>
                                            </div>
                                        </div>
                                        <span class="text-[10px] <?php echo $isCurrentMonth ? 'text-green-900 font-medium mt-2' : 'text-gray-500 mt-2'; ?>"><?php echo $data['month']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Category Pie Chart -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span class="w-1 h-5 bg-purple-500 rounded-full"></span>
                                分类占比
                            </h3>
                            <button class="text-gray-400 hover:text-primary">
                                <span class="material-symbols-outlined text-lg">more_horiz</span>
                            </button>
                        </div>
                        <div class="flex-1 flex flex-col items-center justify-center">
                            <div class="relative w-48 h-48 mb-6">
                                <?php
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
                                <div class="w-full h-full rounded-full shadow-xl" style="background: conic-gradient(<?php echo $conicGradient; ?>);"></div>
                                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-32 h-32 bg-white rounded-full flex flex-col items-center justify-center shadow-inner">
                                    <span class="text-xs text-gray-400">总计</span>
                                    <span class="text-xl font-bold text-gray-900"><?php echo $categoryTotal >= 1000 ? ($displaySymbol . number_format($categoryTotal / 1000, 1) . 'k') : ($displaySymbol . number_format($categoryTotal, 0)); ?></span>
                                </div>
                            </div>
                            <div class="w-full space-y-3">
                                <?php foreach ($categories as $index => $cat): ?>
                                    <?php $percent = $categoryTotal > 0 ? round(($cat['total_amount'] / $categoryTotal) * 100) : 0; ?>
                                    <div class="flex items-center justify-between text-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo $colors[$index % count($colors)]; ?>"></span>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($cat['category_name'] ?? '未分类'); ?></span>
                                        </div>
                                        <span class="font-semibold text-gray-900"><?php echo $percent; ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                    <div class="text-sm text-text-secondary-light text-center py-4">暂无分类数据</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Charts Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Top Subscriptions -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span class="w-1 h-5 bg-emerald-500 rounded-full"></span>
                                订阅项目支出 TOP 5
                            </h3>
                        </div>
                        <div class="space-y-5">
                            <?php foreach ($topSubscriptions as $index => $sub): ?>
                                <?php $percent = ($sub['monthly_amount'] / $topMaxAmount) * 100; ?>
                                <?php $iconColors = ['bg-orange-100 text-orange-600', 'bg-red-100 text-red-600', 'bg-green-100 text-green-600', 'bg-blue-100 text-blue-600', 'bg-gray-100 text-gray-600']; ?>
                                <div class="group">
                                    <div class="flex justify-between items-center mb-1">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded flex items-center justify-center <?php echo $iconColors[$index % count($iconColors)]; ?>">
                                                <span class="material-symbols-outlined text-sm"><?php echo htmlspecialchars($sub['category_icon'] ?? 'credit_card'); ?></span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($sub['name']); ?></span>
                                        </div>
                                        <span class="text-sm font-bold text-gray-900"><?php echo $displaySymbol; ?><?php echo number_format($sub['monthly_amount'], 0); ?></span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="<?php echo $iconColors[$index % count($iconColors)]; ?> bg-opacity-50 h-2 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($topSubscriptions)): ?>
                                <div class="text-center py-8 text-text-secondary-light">暂无订阅数据</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Currency Distribution -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
                                币种分布
                            </h3>
                        </div>
                        <div class="flex-1 flex items-center gap-8 justify-center">
                            <div class="relative w-40 h-40">
                                <?php
                                $currencyColors = ['#16a34a', '#22c55e', '#4ade80', '#86efac', '#facc15', '#f59e0b', '#0ea5e9', '#6366f1'];
                                $currencyGradient = '';
                                $currentPercent = 0;
                                foreach ($currencies as $index => $curr) {
                                    $percent = $currencyTotal > 0 ? ($curr['total_amount'] / $currencyTotal) * 100 : 0;
                                    $nextPercent = $currentPercent + $percent;
                                    $currencyGradient .= $currencyColors[$index % count($currencyColors)] . ' ' . $currentPercent . '% ' . $nextPercent . '%,';
                                    $currentPercent = $nextPercent;
                                }
                                if ($currencyGradient) {
                                    $currencyGradient = rtrim($currencyGradient, ',');
                                } else {
                                    $currencyGradient = '#e5e7eb 0% 100%';
                                }
                                ?>
                                <div class="w-full h-full rounded-full shadow-xl" style="background: conic-gradient(<?php echo $currencyGradient; ?>);"></div>
                                <div class="absolute inset-0 m-auto w-24 h-24 bg-white rounded-full shadow-inner flex items-center justify-center">
                                    <span class="material-symbols-outlined text-gray-300 text-3xl">currency_exchange</span>
                                </div>
                            </div>
                            <div class="space-y-4 flex-1">
                                <?php foreach ($currencies as $index => $curr): ?>
                                    <?php $percent = $currencyTotal > 0 ? round(($curr['total_amount'] / $currencyTotal) * 100) : 0; ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full shadow-sm" style="background-color: <?php echo $currencyColors[$index % count($currencyColors)]; ?>"></span>
                                            <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($curr['currency_code']); ?> (<?php echo htmlspecialchars($curr['currency_name'] ?? ''); ?>)</span>
                                        </div>
                                        <span class="text-sm font-bold text-gray-900"><?php echo $percent; ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($currencies)): ?>
                                    <div class="text-sm text-text-secondary-light">暂无币种数据</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

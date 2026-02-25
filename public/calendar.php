<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

// Get month from URL or use current month
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// Validate month range
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// Calculate navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get first day of month and total days
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$totalDays = date('t', $firstDay);
$firstDayOfWeek = date('w', $firstDay); // 0 = Sunday

// Get subscriptions for this month
$pdo = getDbConnection();
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $totalDays);

// Get all active/paused subscriptions
$stmt = $pdo->prepare("
    SELECT s.*, c.symbol as currency_symbol
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    WHERE s.user_id = :user_id
    AND s.status IN ('active', 'paused')
    AND s.is_lifetime = 0
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subscriptions by day
$subscriptionsByDay = [];
foreach ($subscriptions as $sub) {
    // Calculate the billing day for this month
    $startDate = new DateTime($sub['start_date']);
    $intervalValue = $sub['interval_value'] ?? 1;
    $intervalUnit = $sub['interval_unit'] ?? 'month';

    // Calculate all billing dates in this month
    $billingDates = [];
    $current = clone $startDate;

    // Start directly from start date and advance until we pass the end of the month
    $monthStartDt = new DateTime($monthStart);
    $monthEndDt = new DateTime($monthEnd);

    while ($current <= $monthEndDt) {
        // If current date is within the month, add it
        if ($current >= $monthStartDt) {
            $billingDates[] = (int)$current->format('j');
        }

        // Advance date
        switch ($intervalUnit) {
            case 'day':
                $current->add(new DateInterval('P' . $intervalValue . 'D'));
                break;
            case 'week':
                $current->add(new DateInterval('P' . ($intervalValue * 7) . 'D'));
                break;
            case 'month':
                $current->add(new DateInterval('P' . $intervalValue . 'M'));
                break;
            case 'year':
                $current->add(new DateInterval('P' . $intervalValue . 'Y'));
                break;
            default:
                $current->add(new DateInterval('P1M'));
        }
    }

    // Add to calendar
    foreach ($billingDates as $day) {
        if ($day >= 1 && $day <= $totalDays) {
            if (!isset($subscriptionsByDay[$day])) {
                $subscriptionsByDay[$day] = [];
            }
            $subscriptionsByDay[$day][] = $sub;
        }
    }
}

// Get current time for header
$currentTime = date('H:i');
$currentDate = date('Y年m月d日');

// Format month for display
$monthDisplay = sprintf('%d年 %d月', $year, $month);

// Calendar colors based on auto_renew
$autoColorClass = 'bg-blue-100 text-blue-700';
$manualColorClass = 'bg-orange-100 text-orange-700';
$cancelledColorClass = 'bg-gray-100 text-gray-600';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>续费日历 - <?php echo $monthDisplay; ?> - SubTrack</title>
    <link href="assets/css/app.tailwind.css" rel="stylesheet"/>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
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
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-template-rows: auto repeat(5, 1fr);
            height: 100%;
        }
        .calendar-cell {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .cell-items-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .cell-items-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .cell-items-scroll::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 2px;
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
                <a class="flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="calendar.php">
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
    <main class="flex-1 flex flex-col overflow-hidden relative h-full">
        <!-- Background gradient -->
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-b from-gray-200/50 to-transparent pointer-events-none z-0"></div>

        <!-- Header -->
        <header class="flex items-center justify-between px-8 py-6 z-10 gap-8 shrink-0">
            <div class="flex items-center gap-8 flex-1">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">续费日历</h1>
                    <p class="text-text-secondary-light mt-1 text-sm">查看订阅续费计划概览</p>
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

        <!-- Calendar Content -->
        <div class="flex-1 overflow-hidden z-10 px-8 pb-8">
            <div class="flex flex-col bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-full">
                <!-- Calendar Controls -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-4 shrink-0">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center bg-gray-50 rounded-lg p-1 border border-gray-100">
                            <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" class="p-1 hover:bg-white rounded-md shadow-sm transition-all">
                                <span class="material-symbols-outlined text-gray-600">chevron_left</span>
                            </a>
                            <span class="px-4 font-bold text-lg text-gray-900 whitespace-nowrap"><?php echo $monthDisplay; ?></span>
                            <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" class="p-1 hover:bg-white rounded-md shadow-sm transition-all">
                                <span class="material-symbols-outlined text-gray-600">chevron_right</span>
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-medium">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                            <span class="text-gray-600">自动续费</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                            <span class="text-gray-600">手动续费</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                            <span class="text-gray-600">已暂停</span>
                        </div>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="flex-1 overflow-hidden">
                    <div class="calendar-grid text-sm">
                        <!-- Weekday Headers -->
                        <div class="py-3 text-center text-gray-400 border-b border-r border-gray-100 bg-gray-50/50 font-medium">周日</div>
                        <div class="py-3 text-center text-gray-400 border-b border-r border-gray-100 bg-gray-50/50 font-medium">周一</div>
                        <div class="py-3 text-center text-gray-400 border-b border-r border-gray-100 bg-gray-50/50 font-medium">周二</div>
                        <div class="py-3 text-center text-gray-400 border-b border-r border-gray-100 bg-gray-50/50 font-medium">周三</div>
                        <div class="py-3 text-center text-gray-400 border-b border-r border-gray-100 bg-gray-50/50 font-medium">周四</div>
                        <div class="py-3 text-center text-gray-400 border-b border-r border-gray-100 bg-gray-50/50 font-medium">周五</div>
                        <div class="py-3 text-center text-gray-400 border-b border-gray-100 bg-gray-50/50 font-medium">周六</div>

                        <?php
                        // Group subscriptions by day
                        $subscriptionsByDay = [];
                        
                        foreach ($subscriptions as $sub) {
                            $startDate = new DateTime($sub['start_date']);
                            $startDay = (int)$startDate->format('j'); // Original day (e.g., 30)
                            
                            $intervalValue = $sub['interval_value'] ?? 1;
                            $intervalUnit = $sub['interval_unit'] ?? 'month';

                            // We iterate from start_date until we pass the current view month
                            $current = clone $startDate;
                            $viewMonthStart = new DateTime($monthStart); // 1st of current view month
                            $viewMonthEnd = new DateTime($monthEnd);   // Last of current view month
                            
                            // Safety break to prevent infinite loops
                            $limit = 1000; 
                            
                            while ($current <= $viewMonthEnd && $limit-- > 0) {
                                // Check if $current falls within the target month
                                // Logic: If year/month matches our view month
                                if ($current->format('Y') == $year && $current->format('n') == $month) {
                                    $day = (int)$current->format('j');
                                    // Make sure it's valid for this month (DateTime handles this, but custom logic below enforces snap)
                                    $subscriptionsByDay[$day][] = $sub;
                                }

                                // Advance logic with Snap-to-End-Of-Month
                                if ($intervalUnit === 'month') {
                                    // Custom Month Addition
                                    // Get current year/month
                                    $y = (int)$current->format('Y');
                                    $m = (int)$current->format('n');
                                    
                                    // Add interval
                                    $m += $intervalValue;
                                    while ($m > 12) {
                                        $m -= 12;
                                        $y++;
                                    }
                                    
                                    // Determine max days in target month
                                    $daysInTarget = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
                                    
                                    // Snap day: min(original_start_day, days_in_target)
                                    // This fixes the 1/30 + 1mo -> 2/28 (not 3/2) issue
                                    $targetDay = min($startDay, $daysInTarget);
                                    
                                    $current->setDate($y, $m, $targetDay);
                                    
                                } elseif ($intervalUnit === 'year') {
                                     // Similar logic for year if needed (leap year 2/29 -> 2/28)
                                    $y = (int)$current->format('Y') + $intervalValue;
                                    $m = (int)$current->format('n');
                                    $daysInTarget = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
                                    $targetDay = min($startDay, $daysInTarget);
                                    $current->setDate($y, $m, $targetDay);

                                } else {
                                    // Day/Week allows simple addition
                                    $addStr = $intervalUnit === 'day' ? "P{$intervalValue}D" : "P" . ($intervalValue * 7) . "D";
                                    $current->add(new DateInterval($addStr));
                                }
                            }
                        }

                        // Render leading empty cells before day 1
                        for ($lead = 0; $lead < $firstDayOfWeek; $lead++) {
                            echo '<div class="calendar-cell p-2 border-b border-r border-gray-100 bg-gray-50/20"></div>';
                        }

                        // Current month days
                        $today = (int)date('j');
                        $isCurrentMonth = ($year === (int)date('Y') && $month === (int)date('m'));

                        for ($day = 1; $day <= $totalDays; $day++) {
                            $isToday = $isCurrentMonth && $day === $today;
                            $isFuture = !$isCurrentMonth || $day > $today;

                            $cellClass = 'calendar-cell p-2 border-b border-r border-gray-100 hover:bg-gray-50 transition-colors';
                            if ($isToday) {
                                $cellClass = 'calendar-cell p-2 border-b border-r border-gray-100';
                            }

                            echo '<div class="' . $cellClass . '">';

                            $dayClass = 'block text-right mb-1 text-gray-400 shrink-0';
                            echo '<span class="' . $dayClass . '">' . $day . '</span>';

                            // Show subscriptions for this day
                            if (isset($subscriptionsByDay[$day])) {
                                echo '<div class="flex flex-col gap-1 cell-items-scroll overflow-y-auto">';
                                foreach ($subscriptionsByDay[$day] as $sub) {
                                    $autoRenew = !empty($sub['auto_renew']);
                                    $status = $sub['status'];

                                    if ($status === 'paused') {
                                        $colorClass = $cancelledColorClass;
                                    } elseif ($autoRenew) {
                                        $colorClass = $autoColorClass;
                                    } else {
                                        $colorClass = $manualColorClass;
                                    }

                                    $displayAmount = $sub['currency_symbol'] . number_format($sub['amount'], 0);

                                    echo '<a href="subscription.php?id=' . $sub['id'] . '" class="w-full px-2 py-1 rounded ' . $colorClass . ' text-xs font-medium flex items-center gap-1 cursor-pointer hover:opacity-80 transition-opacity" title="' . htmlspecialchars($sub['name'] . ' ' . $displayAmount, ENT_QUOTES, 'UTF-8') . '">';
                                    echo '<span class="material-symbols-outlined text-[10px] shrink-0">credit_card</span>';
                                    echo '<span class="min-w-0 flex-1 truncate">' . htmlspecialchars($sub['name']) . '</span>';
                                    echo '<span class="shrink-0 text-[10px] font-semibold">' . htmlspecialchars($displayAmount) . '</span>';
                                    echo '</a>';
                                }
                                echo '</div>';
                            }

                            echo '</div>';
                        }

                        // Next month days to fill remaining cells
                        $totalCells = $firstDayOfWeek + $totalDays;
                        $remainingCells = 35 - $totalCells; // 5 rows = 35 cells
                        if ($remainingCells < 0) {
                            $remainingCells = 42 - $totalCells; // 6 rows = 42 cells
                        }

                        for ($day = 1; $day <= $remainingCells; $day++) {
                            echo '<div class="calendar-cell p-2 border-b border-r border-gray-100 bg-gray-50/20' . ($day === $remainingCells ? ' border-r-0' : '') . '">';
                            echo '<span class="block text-right mb-1 text-gray-300 shrink-0">' . $day . '</span>';
                            echo '</div>';
                        }
                        ?>
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

<?php
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

// Get subscription ID from URL
$subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch subscription detail
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT
        s.*,
        c.name as currency_name,
        c.symbol as currency_symbol,
        pm.name as payment_method_name,
        cat.name as category_name
    FROM subscriptions s
    LEFT JOIN currencies c ON s.currency_id = c.id
    LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id
    LEFT JOIN categories cat ON s.category_id = cat.id
    WHERE s.id = :id AND s.user_id = :user_id
");
$stmt->execute([':id' => $subscriptionId, ':user_id' => $_SESSION['user_id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header('Location: subscriptions.php');
    exit;
}

// Fetch payment records
$stmt = $pdo->prepare("
    SELECT p.*, c.symbol AS currency_symbol
    FROM payments p
    LEFT JOIN currencies c ON p.currency_id = c.id
    WHERE p.subscription_id = :subscription_id
    ORDER BY p.paid_at DESC
");
$stmt->execute([':subscription_id' => $subscriptionId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate billing cycle progress
$billingStart = new DateTime($subscription['start_date']);
$billingEnd = new DateTime($subscription['next_payment_date'] ?? $subscription['start_date']);
$today = new DateTime();
$totalDays = $billingStart->diff($billingEnd)->days;
$elapsedDays = $billingStart->diff($today)->days;
$progressPercent = min(100, max(0, ($elapsedDays / max(1, $totalDays)) * 100));
$remainingDays = max(0, $billingEnd->diff($today)->days);

// Format billing cycle display
$intervalValue = $subscription['interval_value'] ?? 1;
$intervalUnit = $subscription['interval_unit'] ?? 'month';
$cycleLabels = ['day' => '日', 'week' => '周', 'month' => '月', 'year' => '年'];
$billingCycleDisplay = $intervalValue . $cycleLabels[$intervalUnit] ?? '月';

// Get current time for header
$currentTime = date('H:i');
$currentDate = date('Y年m月d日');

// Status badge mapping
$statusMap = [
    'active' => ['class' => 'bg-green-100 text-green-800', 'label' => '活跃'],
    'paused' => ['class' => 'bg-yellow-100 text-yellow-800', 'label' => '已暂停'],
    'expired' => ['class' => 'bg-red-100 text-red-800', 'label' => '已过期'],
    'cancelled' => ['class' => 'bg-gray-100 text-gray-800', 'label' => '已取消'],
];
$status = $statusMap[$subscription['status']] ?? $statusMap['active'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>订阅详情 - <?php echo htmlspecialchars($subscription['name']); ?> - SubTrack</title>
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
        .custom-checkbox:checked {
            background-color: #111827;
            border-color: #111827;
        }
        .timeline-line::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 11px;
            width: 2px;
            background-color: #f3f4f6;
            z-index: 0;
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
                <a class="flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="subscriptions.php">
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
        <header class="flex items-center justify-between px-8 py-6 z-10 gap-8">
            <div class="flex items-center gap-8 flex-1">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">订阅详情</h1>
                    <p class="text-text-secondary-light mt-1 text-sm">查看服务详情与消费记录</p>
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

        <!-- Content Area -->
        <div class="flex-1 overflow-hidden flex flex-col relative z-10">
            <div class="flex-1 overflow-y-auto px-8 pb-8 custom-scrollbar">
                <!-- Breadcrumb and Actions -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
                    <nav class="flex items-center text-sm text-gray-500">
                        <a class="hover:text-primary transition-colors" href="subscriptions.php">订阅列表</a>
                        <span class="mx-2">/</span>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($subscription['name']); ?></span>
                    </nav>
                    <div class="flex items-center gap-3 self-end sm:self-auto">
                        <button onclick="toggleStatus(<?php echo $subscription['id']; ?>, '<?php echo $subscription['status']; ?>')" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium <?php echo $subscription['status'] === 'active' ? 'text-yellow-600 hover:bg-yellow-50' : 'text-green-600 hover:bg-green-50'; ?> transition-all shadow-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg"><?php echo $subscription['status'] === 'active' ? 'pause' : 'play_arrow'; ?></span>
                            <?php echo $subscription['status'] === 'active' ? '暂停' : '恢复'; ?>
                        </button>
                        <button onclick="window.location.href='subscriptions.php'" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-sm flex items-center gap-2 group">
                            <span class="material-symbols-outlined text-lg group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                            返回
                        </button>
                        <button onclick="editSubscription(<?php echo $subscription['id']; ?>)" class="px-4 py-2 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-hover transition-colors shadow-lg shadow-gray-300/50 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">edit</span>
                            编辑
                        </button>
                        <button onclick="deleteSubscription(<?php echo $subscription['id']; ?>)" class="px-4 py-2 bg-white border border-red-200 text-red-500 rounded-xl text-sm font-medium hover:bg-red-50 transition-all shadow-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">delete</span>
                            删除
                        </button>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 max-w-7xl mx-auto h-auto">
                    <!-- Left Column (2/3 width) -->
                    <div class="xl:col-span-2 space-y-6">
                        <!-- Service Info Card -->
                        <div class="bg-card-light rounded-3xl p-8 shadow-soft border border-gray-100">
                            <div class="flex flex-col lg:flex-row items-start justify-between gap-6">
                                <div class="flex items-center gap-6 min-w-0 flex-1">
                                    <div class="w-20 h-20 rounded-2xl bg-gray-100 flex items-center justify-center shadow-lg ring-4 ring-gray-50 shrink-0">
                                        <?php if (!empty($subscription['logo_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($subscription['logo_url']); ?>" alt="<?php echo htmlspecialchars($subscription['name']); ?>" class="w-12 h-12 object-contain"/>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-5xl"><?php echo htmlspecialchars($subscription['category_icon'] ?? 'credit_card'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start gap-3 min-w-0">
                                            <h1 class="text-3xl font-bold text-gray-900 tracking-tight min-w-0 truncate" title="<?php echo htmlspecialchars($subscription['name']); ?>"><?php echo htmlspecialchars($subscription['name']); ?></h1>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold shrink-0 mt-1 <?php echo $status['class']; ?>">
                                                <?php echo $status['label']; ?>
                                            </span>
                                        </div>
                                        <p class="text-text-secondary-light mt-1 text-lg truncate"><?php echo htmlspecialchars($subscription['plan_name'] ?? '基础套餐'); ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end shrink-0 self-start">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-5xl font-bold text-gray-900 tracking-tight"><?php echo $subscription['currency_symbol']; ?><?php echo number_format($subscription['amount'], 2); ?></span>
                                        <span class="text-lg text-gray-500 font-medium">/<?php echo $billingCycleDisplay; ?></span>
                                    </div>
                                    <?php if (!empty($subscription['payment_method_name'])): ?>
                                    <div class="text-sm text-gray-400 mt-1 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-base">credit_card</span>
                                        <?php echo htmlspecialchars($subscription['payment_method_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($subscription['note'])): ?>
                            <div class="mt-8 pt-8 border-t border-gray-100">
                                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-gray-400 text-xl">sticky_note_2</span>
                                    备注信息
                                </h3>
                                <div class="bg-gray-50 rounded-2xl p-5 text-gray-600 text-sm leading-relaxed border border-gray-100 relative">
                                    <span class="absolute left-0 top-0 w-1 h-full bg-primary rounded-l-2xl opacity-50"></span>
                                    <?php echo htmlspecialchars($subscription['note']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Billing Cycle Card -->
                        <div class="bg-card-light rounded-3xl p-8 shadow-soft border border-gray-100">
                            <div class="flex items-center justify-between mb-8">
                                <div>
                                    <h3 class="font-bold text-xl text-gray-900">本期用量</h3>
                                    <p class="text-sm text-text-secondary-light mt-0.5">计费周期进度</p>
                                </div>
                                <div class="bg-gray-100 px-3 py-1 rounded-lg text-sm font-medium text-gray-600">
                                    <?php echo $billingCycleDisplay; ?>付周期
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="relative">
                                <div class="flex justify-between text-sm font-bold mb-3">
                                    <span class="text-primary">已过 <?php echo $elapsedDays; ?> 天</span>
                                    <span class="text-gray-400">剩余 <?php echo $remainingDays; ?> 天</span>
                                </div>
                                <div class="h-5 bg-gray-100 rounded-full overflow-hidden shadow-inner relative">
                                    <div class="h-full bg-primary rounded-full transition-all duration-1000 ease-out" style="width: <?php echo $progressPercent; ?>%">
                                        <div class="absolute inset-0 bg-white/20 w-full h-full rounded-full"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cycle Dates -->
                            <div class="grid grid-cols-2 gap-4 pt-8 mt-2">
                                <div class="flex flex-col">
                                    <p class="text-xs text-text-secondary-light font-medium mb-1.5 uppercase tracking-wider">本期开始</p>
                                    <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl border border-gray-100 w-full md:w-fit">
                                        <span class="material-symbols-outlined text-gray-400">calendar_today</span>
                                        <p class="font-bold text-gray-900"><?php echo date('n月 j日', strtotime($subscription['start_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <p class="text-xs text-text-secondary-light font-medium mb-1.5 uppercase tracking-wider">下次续费</p>
                                    <div class="flex items-center gap-2 p-3 bg-primary/5 rounded-xl border border-primary/10 w-full md:w-fit justify-end">
                                        <p class="font-bold text-primary"><?php echo date('n月 j日', strtotime($subscription['next_payment_date'] ?? $subscription['start_date'])); ?></p>
                                        <span class="material-symbols-outlined text-primary">update</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (1/3 width) - Payment History -->
                    <div class="xl:col-span-1 h-full">
                        <div class="bg-card-light rounded-3xl shadow-soft border border-gray-100 h-full flex flex-col overflow-hidden max-h-[800px] xl:max-h-none xl:h-full">
                            <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-20">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary">history</span>
                                    <h3 class="font-bold text-lg text-gray-900">扣款历史</h3>
                                </div>
                                <button class="text-xs font-bold text-gray-400 hover:text-primary transition-colors uppercase tracking-wider">查看全部</button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-6 relative">
                                <div class="absolute top-6 bottom-6 left-[35px] w-0.5 bg-gray-100 z-0"></div>
                                <div class="space-y-8 relative z-10">
                                    <?php foreach ($payments as $index => $payment): ?>
                                    <div class="flex gap-4 group">
                                        <div class="w-3 h-3 mt-1.5 rounded-full ring-4 ring-white shrink-0 z-10 shadow-sm ml-1.5 <?php echo $index === 0 ? 'bg-primary' : 'bg-gray-300 group-hover:bg-primary transition-colors'; ?>"></div>
                                        <div class="flex-1 pb-2 min-w-0">
                                            <div class="flex items-center justify-between mb-1 gap-3">
                                                <span class="text-sm font-bold <?php echo $index === 0 ? 'text-gray-900' : 'text-gray-700'; ?> truncate">
                                                    <?php echo $payment['currency_symbol'] ?? $subscription['currency_symbol']; ?><?php echo number_format((float) $payment['amount'], 2); ?>
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 shrink-0">
                                                    <?php echo $payment['status'] === 'success' ? '支付成功' : ($payment['status'] === 'pending' ? '待支付' : '支付失败'); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-text-secondary-light font-medium"><?php echo date('Y年 n月 j日', strtotime($payment['paid_at'])); ?></p>
                                            <p class="text-[10px] text-gray-400 mt-1">锁定汇率结算：¥<?php echo number_format((float) ($payment['amount_cny'] ?? ((float) $payment['amount'] * (float) ($payment['fx_rate_to_cny'] ?? 1))), 2); ?>（1 <?php echo $payment['currency_symbol'] ?? $subscription['currency_symbol']; ?> = ¥<?php echo number_format((float) ($payment['fx_rate_to_cny'] ?? 1), 4); ?>）</p>
                                            <?php if (!empty($payment['note'])): ?>
                                                <p class="text-[10px] text-gray-400 mt-1 break-words"><?php echo htmlspecialchars((string) $payment['note']); ?></p>
                                            <?php endif; ?>
                                            <div class="mt-2 flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="js-payment-edit px-2.5 py-1 rounded-lg text-[11px] font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50"
                                                    data-payment-id="<?php echo (int) $payment['id']; ?>"
                                                    data-amount="<?php echo htmlspecialchars((string) $payment['amount'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-fx-rate="<?php echo htmlspecialchars((string) ($payment['fx_rate_to_cny'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-note="<?php echo htmlspecialchars((string) ($payment['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-currency-symbol="<?php echo htmlspecialchars((string) ($payment['currency_symbol'] ?? $subscription['currency_symbol']), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-paid-at="<?php echo htmlspecialchars((string) $payment['paid_at'], ENT_QUOTES, 'UTF-8'); ?>"
                                                >编辑本期</button>
                                                <button
                                                    type="button"
                                                    class="js-payment-logs px-2.5 py-1 rounded-lg text-[11px] font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50"
                                                    data-payment-id="<?php echo (int) $payment['id']; ?>"
                                                    data-paid-at="<?php echo htmlspecialchars((string) $payment['paid_at'], ENT_QUOTES, 'UTF-8'); ?>"
                                                >变更记录</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>

                                    <?php if (empty($payments)): ?>
                                    <div class="text-center py-8 text-gray-400">
                                        <span class="material-symbols-outlined text-4xl mb-2">receipt_long</span>
                                        <p class="text-sm">暂无扣款记录</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Payment Edit Modal -->
    <div id="paymentEditModal" class="hidden fixed inset-0 z-50 bg-black/40 backdrop-blur-sm items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">编辑本期扣款</h3>
                    <p id="paymentEditMeta" class="text-xs text-gray-500 mt-1"></p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closePaymentEditModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-[12px] text-amber-700">
                    此操作仅修改当前这一期历史记录，不影响订阅后续自动扣款规则。
                </div>
                <input type="hidden" id="paymentEditId" value="" />
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="paymentEditAmount">金额</label>
                    <input id="paymentEditAmount" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-300" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="paymentEditFxRate">锁定汇率（兑人民币）</label>
                    <input id="paymentEditFxRate" type="number" step="0.0001" min="0.0001" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-300" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="paymentEditNote">备注（可选）</label>
                    <textarea id="paymentEditNote" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-300"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="paymentEditReason">变更原因（必填）</label>
                    <textarea id="paymentEditReason" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-300"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 font-medium hover:bg-gray-100" onclick="closePaymentEditModal()">取消</button>
                <button type="button" id="paymentEditSubmitBtn" class="px-4 py-2 rounded-xl bg-primary text-white font-medium hover:bg-primary-hover" onclick="submitPaymentEdit()">保存本期修正</button>
            </div>
        </div>
    </div>

    <!-- Payment Logs Modal -->
    <div id="paymentLogsModal" class="hidden fixed inset-0 z-50 bg-black/40 backdrop-blur-sm items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">本期变更记录</h3>
                    <p id="paymentLogsMeta" class="text-xs text-gray-500 mt-1"></p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closePaymentLogsModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="paymentLogsContent" class="px-6 py-4 max-h-[70vh] overflow-y-auto"></div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end">
                <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 font-medium hover:bg-gray-100" onclick="closePaymentLogsModal()">关闭</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function editSubscription(id) {
            window.location.href = 'edit-subscription.php?id=' + id;
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-payment-edit').forEach((button) => {
                button.addEventListener('click', () => {
                    openPaymentEditModalFromButton(button);
                });
            });

            document.querySelectorAll('.js-payment-logs').forEach((button) => {
                button.addEventListener('click', () => {
                    openPaymentLogsModalFromButton(button);
                });
            });
        });

        async function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'paused' : 'active';
            if (!confirm('确定要' + (newStatus === 'active' ? '恢复' : '暂停') + '此订阅吗？')) return;

            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', newStatus);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('api/subscriptions/update.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || '操作失败');
                }
            } catch (e) {
                alert('操作失败，请稍后重试');
            }
        }

        async function deleteSubscription(id) {
            if (!confirm('确定要删除此订阅吗？此操作不可恢复。')) return;

            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch('api/subscriptions/delete.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'subscriptions.php';
                } else {
                    alert(data.message || '删除失败');
                }
            } catch (e) {
                alert('删除失败，请稍后重试');
            }
        }

        function openPaymentEditModalFromButton(button) {
            const paymentId = Number(button.dataset.paymentId || '0');
            const amount = Number(button.dataset.amount || '0');
            const fxRateToCny = Number(button.dataset.fxRate || '1');
            const note = button.dataset.note || '';
            const currencySymbol = button.dataset.currencySymbol || '';
            const paidAt = button.dataset.paidAt || '';
            openPaymentEditModal(paymentId, amount, fxRateToCny, note, currencySymbol, paidAt);
        }

        function openPaymentEditModal(paymentId, amount, fxRateToCny, note, currencySymbol, paidAt) {
            document.getElementById('paymentEditId').value = String(paymentId);
            document.getElementById('paymentEditAmount').value = Number(amount).toFixed(2);
            document.getElementById('paymentEditFxRate').value = Number(fxRateToCny || 1).toFixed(4);
            document.getElementById('paymentEditNote').value = note || '';
            document.getElementById('paymentEditReason').value = '';
            document.getElementById('paymentEditMeta').textContent = `${paidAt} · ${currencySymbol}`;

            const modal = document.getElementById('paymentEditModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closePaymentEditModal() {
            const modal = document.getElementById('paymentEditModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function submitPaymentEdit() {
            const paymentId = Number(document.getElementById('paymentEditId').value || '0');
            const amount = document.getElementById('paymentEditAmount').value;
            const fxRate = document.getElementById('paymentEditFxRate').value;
            const note = document.getElementById('paymentEditNote').value;
            const reason = document.getElementById('paymentEditReason').value.trim();

            if (!paymentId) {
                alert('无效的扣款记录ID');
                return;
            }
            if (!reason) {
                alert('请填写变更原因');
                return;
            }

            const submitBtn = document.getElementById('paymentEditSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '保存中...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('payment_id', String(paymentId));
            formData.append('amount', amount);
            formData.append('fx_rate_to_cny', fxRate);
            formData.append('note', note);
            formData.append('change_reason', reason);

            try {
                const res = await fetch('api/payments/update.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await res.json();

                if (!data.success) {
                    alert(data.message || '保存失败');
                    return;
                }

                closePaymentEditModal();
                location.reload();
            } catch (err) {
                alert('保存失败，请稍后重试');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '保存本期修正';
            }
        }

        function openPaymentLogsModalFromButton(button) {
            const paymentId = Number(button.dataset.paymentId || '0');
            const paidAt = button.dataset.paidAt || '';
            openPaymentLogsModal(paymentId, paidAt);
        }

        async function openPaymentLogsModal(paymentId, paidAt) {
            const modal = document.getElementById('paymentLogsModal');
            const content = document.getElementById('paymentLogsContent');
            const meta = document.getElementById('paymentLogsMeta');
            meta.textContent = `${paidAt}`;

            content.innerHTML = '<p class="text-sm text-gray-500">加载中...</p>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(`api/payments/change-logs.php?payment_id=${encodeURIComponent(paymentId)}`);
                const data = await res.json();

                if (!data.success) {
                    content.innerHTML = `<p class="text-sm text-red-500">${(data.message || '加载失败')}</p>`;
                    return;
                }

                if (!Array.isArray(data.logs) || data.logs.length === 0) {
                    content.innerHTML = '<p class="text-sm text-gray-500">暂无变更记录</p>';
                    return;
                }

                const html = data.logs.map((log) => {
                    const changedBy = log.changed_by_username ? escapeHtml(String(log.changed_by_username)) : `用户#${escapeHtml(String(log.changed_by || ''))}`;
                    const beforeAmount = Number(log.before_amount || 0).toFixed(2);
                    const afterAmount = Number(log.after_amount || 0).toFixed(2);
                    const beforeFx = Number(log.before_fx_rate_to_cny || 1).toFixed(4);
                    const afterFx = Number(log.after_fx_rate_to_cny || 1).toFixed(4);
                    const beforeCny = Number(log.before_amount_cny || 0).toFixed(2);
                    const afterCny = Number(log.after_amount_cny || 0).toFixed(2);
                    const beforeNote = log.before_note ? escapeHtml(String(log.before_note)) : '-';
                    const afterNote = log.after_note ? escapeHtml(String(log.after_note)) : '-';
                    const reason = log.change_reason ? escapeHtml(String(log.change_reason)) : '-';
                    const createdAt = log.created_at ? escapeHtml(String(log.created_at)) : '';

                    return `
                        <div class="p-4 rounded-xl border border-gray-100 mb-3 bg-white">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-semibold text-gray-900">${changedBy}</p>
                                <p class="text-xs text-gray-400">${createdAt}</p>
                            </div>
                            <p class="text-xs text-gray-600 mb-2">原因：${reason}</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-gray-600">
                                <p>金额：${beforeAmount} → <span class="font-semibold text-gray-900">${afterAmount}</span></p>
                                <p>汇率：${beforeFx} → <span class="font-semibold text-gray-900">${afterFx}</span></p>
                                <p>折CNY：¥${beforeCny} → <span class="font-semibold text-gray-900">¥${afterCny}</span></p>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">备注：${beforeNote} → ${afterNote}</p>
                        </div>
                    `;
                }).join('');

                content.innerHTML = html;
            } catch (err) {
                content.innerHTML = '<p class="text-sm text-red-500">加载失败，请稍后重试</p>';
            }
        }

        function closePaymentLogsModal() {
            const modal = document.getElementById('paymentLogsModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>

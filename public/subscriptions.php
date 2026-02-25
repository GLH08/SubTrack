<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/checksession.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>订阅列表 - SubTrack</title>
    <link href="assets/css/fonts.css" rel="stylesheet"/>
        <link href="assets/css/app.tailwind.css" rel="stylesheet"/>
    <script src="assets/js/notification-popover.js"></script>
    <style>
      .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
      .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
      .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 20px; }
    </style>
</head>
<body class="bg-background-light font-sans text-gray-900 antialiased overflow-hidden h-screen flex">
    <aside class="w-56 bg-card-light border-r border-gray-200 flex flex-col justify-between hidden md:flex z-20 shrink-0">
        <div>
            <div class="h-20 flex items-center px-6 border-b border-gray-100">
                <div class="flex items-center gap-2 text-primary">
                    <span class="material-symbols-outlined text-3xl">donut_small</span>
                    <span class="text-xl font-bold tracking-tight">SubTrack</span>
                </div>
            </div>
            <nav class="p-3 space-y-1 mt-4">
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="/dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="font-medium">仪表盘</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl shadow-md transition-all" href="/subscriptions.php">
                    <span class="material-symbols-outlined">credit_card</span>
                    <span class="font-medium">订阅列表</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="/calendar.php">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span class="font-medium">续费日历</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="/stats.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="font-medium">统计分析</span>
                </a>
            </nav>
        </div>
        <div class="p-4 border-t border-gray-200">
            <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="/settings.php">
                <span class="material-symbols-outlined">settings</span>
                <span class="font-medium">系统设置</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-text-secondary-light hover:bg-gray-100 rounded-xl transition-all" href="/profile.php">
                <span class="material-symbols-outlined">person</span>
                <span class="font-medium">个人资料</span>
            </a>
            <form method="POST" action="/api/auth/logout.php" class="mt-1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"/>
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition-all">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="font-medium">退出登录</span>
                </button>
            </form>
        </div>
    </aside>
    <main class="flex-1 flex flex-col overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-b from-gray-200/50 to-transparent pointer-events-none z-0"></div>
        <header class="flex items-center justify-between px-8 py-6 z-10 gap-8">
            <div class="flex items-center gap-8 flex-1">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">订阅列表</h1>
                    <p class="text-text-secondary-light mt-1 text-sm">管理您的所有订阅服务</p>
                </div>
                <div class="relative max-w-md w-full hidden lg:block">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input id="search-input" class="w-full pl-10 pr-4 py-2.5 bg-white border-none rounded-xl shadow-sm focus:ring-2 focus:ring-primary text-sm placeholder-gray-400" placeholder="搜索订阅服务..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-4 shrink-0">
                <button class="p-2 rounded-full bg-white shadow-sm text-gray-500 hover:text-primary transition-colors relative" id="notification-btn">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <div class="flex flex-col items-end justify-center px-4 py-1.5 bg-white/50 backdrop-blur-sm rounded-xl border border-white/20">
                    <span class="text-sm font-bold text-gray-900 tracking-wide" id="current-time">--:--</span>
                    <span class="text-xs text-text-secondary-light font-medium" id="current-date">----年--月--日</span>
                </div>
            </div>
        </header>

        <div class="px-8 pb-6 z-10 flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
                <div class="bg-white rounded-lg p-1 shadow-sm border border-gray-100 flex items-center h-[38px]">
                    <button id="view-card" class="p-1 rounded-md bg-gray-100 text-primary shadow-sm h-full flex items-center justify-center aspect-square">
                        <span class="material-symbols-outlined text-xl">grid_view</span>
                    </button>
                    <button id="view-table" class="p-1 rounded-md text-gray-400 hover:text-primary transition-colors h-full flex items-center justify-center aspect-square">
                        <span class="material-symbols-outlined text-xl">table_rows</span>
                    </button>
                </div>
                <div class="w-px h-8 bg-gray-200 mx-1 hidden sm:block"></div>
                <select id="filter-category" class="pl-3 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors h-[38px] appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20stroke%3D%22%236b7280%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%221.5%22%20d%3D%22m6%208%204%204%204-4%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat">
                    <option value="">全部分类</option>
                </select>
                <select id="filter-currency" class="pl-3 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors h-[38px] appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20stroke%3D%22%236b7280%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%221.5%22%20d%3D%22m6%208%204%204%204-4%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat">
                    <option value="">全部币种</option>
                </select>
                <select id="filter-status" class="pl-3 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors h-[38px] appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20stroke%3D%22%236b7280%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%221.5%22%20d%3D%22m6%208%204%204%204-4%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat">
                    <option value="">全部状态</option>
                    <option value="active">活跃</option>
                    <option value="paused">已暂停</option>
                    <option value="cancelled">已取消</option>
                </select>
                <select id="filter-payment" class="pl-3 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors h-[38px] appearance-none cursor-pointer bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20stroke%3D%22%236b7280%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%221.5%22%20d%3D%22m6%208%204%204%204-4%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem_1.25rem] bg-[right_0.5rem_center] bg-no-repeat">
                    <option value="">全部支付方式</option>
                </select>
                <button type="button" id="batch-select-all" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors h-[38px]">
                    全选
                </button>
            </div>
            <div class="flex items-center gap-3 w-full xl:w-auto justify-between xl:justify-end">
                <div id="batch-actions" class="hidden flex items-center gap-2 mr-4">
                    <span class="text-xs text-gray-500 font-medium">已选择 <span id="selected-count">0</span> 项</span>
                    <button type="button" onclick="App.batchUpdateStatus('active')" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700 transition-colors">
                        恢复
                    </button>
                    <button type="button" onclick="App.batchUpdateStatus('paused')" class="px-3 py-1.5 bg-yellow-500 text-white rounded-lg text-xs font-medium hover:bg-yellow-600 transition-colors">
                        暂停
                    </button>
                    <button type="button" onclick="App.batchDelete()" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700 transition-colors">
                        删除
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 font-medium">排序:</span>
                    <button id="sort-btn" class="flex items-center gap-1 px-3 py-2 bg-transparent text-sm font-medium text-gray-700 hover:text-primary transition-colors">
                        下次支付日期 <span class="material-symbols-outlined text-base">swap_vert</span>
                    </button>
                </div>
                <button id="add-subscription-btn" class="bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-primary-hover transition-colors flex items-center gap-2 shadow-lg whitespace-nowrap">
                    <span class="material-symbols-outlined text-sm">add</span> 新增订阅
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-8 pb-8 z-10">
            <div id="subscription-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6">
                <!-- 订阅卡片将通过 JavaScript 渲染 -->
            </div>
            <div id="subscription-pagination" class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-6 pb-2">
                <!-- 分页将通过 JavaScript 渲染 -->
            </div>
        </div>
    </main>

    <!-- 新增订阅 Modal -->
    <div id="subscription-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 hidden">
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" id="modal-backdrop"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100 shrink-0 bg-white/50 backdrop-blur-md z-10">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">新增订阅</h2>
                    <p class="text-xs text-gray-500 mt-0.5">添加新的订阅服务以追踪支出</p>
                </div>
                <button id="modal-close" class="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-8 py-6 space-y-6 custom-scrollbar">
                <form id="subscription-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"/>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">服务名称</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="例如: Netflix"/>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">金额</label>
                                <input type="number" name="amount" required step="0.01" min="0" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="0.00"/>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">币种</label>
                                <select name="currency_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">分类</label>
                                <select name="category_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">支付方式</label>
                                <select name="payment_method_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">付款周期</label>
                                <div class="flex gap-2">
                                    <input type="number" name="interval_value" value="1" min="1" class="w-20 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                                    <select name="interval_unit" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                                        <option value="month">月</option>
                                        <option value="year">年</option>
                                        <option value="week">周</option>
                                        <option value="day">日</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">开始日期</label>
                                <input type="date" name="start_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary"/>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_lifetime" value="1" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"/>
                                <span class="text-sm text-gray-700">一次性买断</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="auto_renew" value="1" checked class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"/>
                                <span class="text-sm text-gray-700">自动续费</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">备注</label>
                            <textarea name="note" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="添加账号信息、备忘录等..."></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" id="modal-cancel" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">取消</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-hover shadow-lg transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">check</span> 保存订阅
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    const App = {
        state: {
            view: 'card',
            page: 1,
            perPage: 12,
            total: 0,
            sortBy: 'next_payment_date',
            sortOrder: 'ASC',
            filters: { search: '', category_id: '', currency_id: '', status: '', payment_method_id: '' },
            meta: { categories: [], currencies: [], payment_methods: [] },
            selected: [] // For batch operations
        },

        init() {
            this.updateTime();
            setInterval(() => this.updateTime(), 1000);
            this.bindEvents();
            this.loadMeta().then(() => this.loadSubscriptions());
        },

        updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('current-date').textContent = now.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric' });
        },

        bindEvents() {
            document.getElementById('search-input').addEventListener('input', (e) => {
                this.state.filters.search = e.target.value;
                this.state.page = 1;
                this.loadSubscriptions();
            });
            document.getElementById('view-card').addEventListener('click', () => this.switchView('card'));
            document.getElementById('view-table').addEventListener('click', () => this.switchView('table'));

            // Batch selection
            document.getElementById('batch-select-all')?.addEventListener('click', () => this.toggleSelectAll());
            document.getElementById('batch-select-all-table')?.addEventListener('change', (e) => this.toggleSelectAll(e.target.checked));

            // Checkbox change events
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('subscription-checkbox')) {
                    this.toggleSelection(parseInt(e.target.dataset.id), e.target.checked);
                }
                if (e.target.classList.contains('subscription-checkbox-table')) {
                    this.toggleSelection(parseInt(e.target.dataset.id), e.target.checked);
                }
            });

            document.getElementById('filter-category').addEventListener('change', (e) => {
                this.state.filters.category_id = e.target.value;
                this.state.page = 1;
                this.loadSubscriptions();
            });
            document.getElementById('filter-currency').addEventListener('change', (e) => {
                this.state.filters.currency_id = e.target.value;
                this.state.page = 1;
                this.loadSubscriptions();
            });
            document.getElementById('filter-status').addEventListener('change', (e) => {
                this.state.filters.status = e.target.value;
                this.state.page = 1;
                this.loadSubscriptions();
            });
            document.getElementById('filter-payment').addEventListener('change', (e) => {
                this.state.filters.payment_method_id = e.target.value;
                this.state.page = 1;
                this.loadSubscriptions();
            });
            document.getElementById('sort-btn').addEventListener('click', () => {
                this.state.sortOrder = this.state.sortOrder === 'ASC' ? 'DESC' : 'ASC';
                this.loadSubscriptions();
            });
            document.getElementById('add-subscription-btn').addEventListener('click', () => this.openModal());
            document.getElementById('modal-close').addEventListener('click', () => this.closeModal());
            document.getElementById('modal-backdrop').addEventListener('click', () => this.closeModal());
            document.getElementById('modal-cancel').addEventListener('click', () => this.closeModal());
            document.getElementById('subscription-form').addEventListener('submit', (e) => this.handleSubmit(e));
        },

        toggleSelectAll(checked = null) {
            const checkboxes = document.querySelectorAll('.subscription-checkbox, .subscription-checkbox-table');
            const shouldCheck = typeof checked === 'boolean' ? checked : !(checkboxes.length > 0 && this.state.selected.length === checkboxes.length);

            this.state.selected = [];
            checkboxes.forEach(cb => {
                cb.checked = shouldCheck;
                if (shouldCheck) {
                    const id = parseInt(cb.dataset.id);
                    if (!this.state.selected.includes(id)) {
                        this.state.selected.push(id);
                    }
                }
            });

            this.updateBatchActions();
        },

        toggleSelection(id, checked) {
            if (checked) {
                if (!this.state.selected.includes(id)) {
                    this.state.selected.push(id);
                }
            } else {
                this.state.selected = this.state.selected.filter(i => i !== id);
            }
            this.updateBatchActions();
        },

        updateBatchActions() {
            const batchActions = document.getElementById('batch-actions');
            const countSpan = document.getElementById('selected-count');
            if (batchActions && countSpan) {
                countSpan.textContent = this.state.selected.length;
                batchActions.classList.toggle('hidden', this.state.selected.length === 0);
            }

            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.subscription-checkbox, .subscription-checkbox-table');
            const allChecked = allCheckboxes.length > 0 && this.state.selected.length === allCheckboxes.length;
            const selectAllCheckbox = document.getElementById('batch-select-all-table');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
            }
        },

        async batchUpdateStatus(status) {
            if (this.state.selected.length === 0) return;
            if (!confirm(`确定要${status === 'active' ? '恢复' : '暂停'}选中的 ${this.state.selected.length} 个订阅吗？`)) return;

            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            this.state.selected.forEach(id => formData.append('ids[]', id));
            formData.append('status', status);

            try {
                const res = await fetch('api/subscriptions/batch-update.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.state.selected = [];
                    this.loadSubscriptions();
                    this.updateBatchActions();
                } else {
                    alert(data.message || '批量更新失败');
                }
            } catch (e) {
                alert('批量更新失败，请稍后重试');
            }
        },

        async batchDelete() {
            if (this.state.selected.length === 0) return;
            if (!confirm(`确定要删除选中的 ${this.state.selected.length} 个订阅吗？此操作不可恢复。`)) return;

            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            this.state.selected.forEach(id => formData.append('ids[]', id));

            try {
                const res = await fetch('api/subscriptions/batch-delete.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.state.selected = [];
                    this.loadSubscriptions();
                    this.updateBatchActions();
                } else {
                    alert(data.message || '批量删除失败');
                }
            } catch (e) {
                alert('批量删除失败，请稍后重试');
            }
        },

        switchView(view) {
            this.state.view = view;
            document.getElementById('view-card').className = view === 'card'
                ? 'p-1 rounded-md bg-gray-100 text-primary shadow-sm h-full flex items-center justify-center aspect-square'
                : 'p-1 rounded-md text-gray-400 hover:text-primary transition-colors h-full flex items-center justify-center aspect-square';
            document.getElementById('view-table').className = view === 'table'
                ? 'p-1 rounded-md bg-gray-100 text-primary shadow-sm h-full flex items-center justify-center aspect-square'
                : 'p-1 rounded-md text-gray-400 hover:text-primary transition-colors h-full flex items-center justify-center aspect-square';
            this.loadSubscriptions();
        },

        async loadMeta() {
            try {
                const res = await fetch('api/meta/form-options.php');
                const data = await res.json();
                this.state.meta = data;
                this.renderFilters();
            } catch (e) {
                console.error('Failed to load meta:', e);
            }
        },

        renderFilters() {
            const catSel = document.getElementById('filter-category');
            const curSel = document.getElementById('filter-currency');
            const paySel = document.getElementById('filter-payment');
            const formCurSel = document.querySelector('select[name="currency_id"]');
            const formCatSel = document.querySelector('select[name="category_id"]');
            const formPaySel = document.querySelector('select[name="payment_method_id"]');

            this.state.meta.categories.forEach(c => {
                catSel.add(new Option(c.name, c.id));
                formCatSel.add(new Option(c.name, c.id));
            });
            this.state.meta.currencies.forEach(c => {
                curSel.add(new Option(`${c.code} (${c.symbol})`, c.id));
                formCurSel.add(new Option(`${c.name} (${c.symbol})`, c.id));
            });
            this.state.meta.payment_methods.forEach(p => {
                paySel.add(new Option(p.name, p.id));
                formPaySel.add(new Option(p.name, p.id));
            });

            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="start_date"]').value = today;
        },

        async loadSubscriptions() {
            const params = new URLSearchParams({
                view: this.state.view,
                page: this.state.page,
                per_page: this.state.perPage,
                sort_by: this.state.sortBy,
                sort_order: this.state.sortOrder,
                ...Object.fromEntries(Object.entries(this.state.filters).filter(([_, v]) => v !== ''))
            });
            try {
                const res = await fetch('api/subscriptions/list.php?' + params);
                const data = await res.json();
                this.state.total = data.total;
                this.renderSubscriptions(data.items);
                this.renderPagination(data);
            } catch (e) {
                console.error('Failed to load subscriptions:', e);
            }
        },

        renderSubscriptions(items) {
            const container = document.getElementById('subscription-grid');
            if (items.length === 0) {
                container.innerHTML = '<div class="col-span-full text-center py-12 text-gray-500">暂无订阅，点击上方按钮添加</div>';
                return;
            }
            if (this.state.view === 'table') {
                this.renderTable(items, container);
            } else {
                this.renderCards(items, container);
            }
        },

        renderCards(items, container) {
            container.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6';
            container.innerHTML = items.map(item => `
                <div class="bg-card-light rounded-3xl p-5 shadow-soft hover:shadow-xl transition-all duration-300 group relative border border-transparent hover:border-gray-100 h-full flex flex-col cursor-pointer ${this.state.selected.includes(item.id) ? 'ring-2 ring-primary' : ''}" onclick="if(!event.target.closest('input[type=checkbox]') && !event.target.closest('button')) location.href='/subscription.php?id=${item.id}'">
                    <div class="absolute top-3 left-3 z-10" onclick="event.stopPropagation()">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer subscription-checkbox" data-id="${item.id}" ${this.state.selected.includes(item.id) ? 'checked' : ''}/>
                    </div>
                    <div class="flex items-start justify-between mb-3 pl-6 gap-2">
                        <div class="flex items-start gap-3 min-w-0 flex-1">
                            <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600 shadow-sm shrink-0 overflow-hidden mt-0.5">
                                ${item.logo_url ? `<img src="${item.logo_url}" class="w-full h-full object-cover"/>` : '<span class="material-symbols-outlined text-2xl">category</span>'}
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 leading-tight min-w-0 truncate" title="${this.escapeHtml(item.name)}">${this.escapeHtml(item.name)}</h3>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium shrink-0 self-start mt-0.5 ${this.statusClass(item.status)}">${this.statusText(item.status)}</span>
                    </div>
                    <div class="mb-6">
                        <p class="text-sm text-text-secondary-light">${item.category_name || '未分类'}</p>
                    </div>
                    <div class="mt-auto pt-4 border-t border-gray-100 flex items-end justify-between">
                        <div class="flex flex-col gap-1 min-w-0">
                            <div class="flex items-baseline gap-1">
                                <p class="text-2xl font-bold text-gray-900">${item.currency_symbol}${Number(item.amount).toFixed(2)}</p>
                                <span class="text-xs text-gray-500 font-normal">/${item.interval_unit === 'month' ? '月' : item.interval_unit === 'year' ? '年' : item.interval_unit}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-text-secondary-light truncate">
                                <span class="material-symbols-outlined text-sm">calendar_clock</span>
                                ${item.next_payment_date ? this.formatDate(item.next_payment_date) + ' 续费' : '永久有效'}
                            </div>
                        </div>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all duration-200 translate-y-2 group-hover:translate-y-0 pb-0.5 shrink-0" onclick="event.stopPropagation()">
                            <button class="p-1.5 text-gray-400 hover:text-primary rounded-md hover:bg-gray-100 transition-colors" title="编辑" onclick="App.openModal(${item.id})">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            <button class="p-1.5 text-gray-400 hover:text-red-500 rounded-md hover:bg-gray-100 transition-colors" title="删除" onclick="App.deleteSubscription(${item.id})">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                            ${item.website_url ? `<a href="${item.website_url}" target="_blank" class="p-1.5 text-gray-400 hover:text-blue-500 rounded-md hover:bg-gray-100 transition-colors" title="访问官网">
                                <span class="material-symbols-outlined text-lg">open_in_new</span>
                            </a>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        },

        renderTable(items, container) {
            container.className = 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm';
            container.innerHTML = `
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-text-secondary-light font-medium">
                        <tr>
                            <th class="px-4 py-3 w-12">
                                <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary cursor-pointer" id="batch-select-all-table"/>
                            </th>
                            <th class="px-6 py-3">名称</th>
                            <th class="px-6 py-3">分类</th>
                            <th class="px-6 py-3">费用</th>
                            <th class="px-6 py-3">下次续费</th>
                            <th class="px-6 py-3">状态</th>
                            <th class="px-6 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        ${items.map(item => `
                            <tr class="hover:bg-gray-50 transition-colors ${this.state.selected.includes(item.id) ? 'bg-primary/5' : ''}" onclick="if(!event.target.closest('input[type=checkbox]')) location.href='/subscription.php?id=${item.id}'">
                                <td class="px-4 py-4" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary cursor-pointer subscription-checkbox-table" data-id="${item.id}" ${this.state.selected.includes(item.id) ? 'checked' : ''}/>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900">${this.escapeHtml(item.name)}</td>
                                <td class="px-6 py-4 text-gray-600">${item.category_name || '-'}</td>
                                <td class="px-6 py-4 font-medium text-gray-900">${item.currency_symbol}${Number(item.amount).toFixed(2)}/${item.interval_unit === 'month' ? '月' : item.interval_unit}</td>
                                <td class="px-6 py-4 text-gray-600">${item.next_payment_date ? this.formatDate(item.next_payment_date) : '永久'}</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${this.statusClass(item.status)}">${this.statusText(item.status)}</span></td>
                                <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                                    <button class="text-gray-400 hover:text-primary mx-1" onclick="App.openModal(${item.id})"><span class="material-symbols-outlined">edit</span></button>
                                    <button class="text-gray-400 hover:text-red-500 mx-1" onclick="App.deleteSubscription(${item.id})"><span class="material-symbols-outlined">delete</span></button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        },

        renderPagination(data) {
            const container = document.getElementById('subscription-pagination');
            if (data.total_pages <= 1) {
                container.innerHTML = `<span class="text-sm text-gray-500">共 ${data.total} 条</span>`;
                return;
            }
            const prevDisabled = data.page === 1 ? 'opacity-50 pointer-events-none' : '';
            const nextDisabled = data.page === data.total_pages ? 'opacity-50 pointer-events-none' : '';
            container.innerHTML = `
                <span class="text-sm text-gray-500">共 ${data.total} 条</span>
                <div class="flex items-center gap-2">
                    <button class="p-1.5 rounded-md hover:bg-gray-100 text-gray-400 hover:text-primary transition-colors ${prevDisabled}" onclick="App.goPage(${data.page - 1})">
                        <span class="material-symbols-outlined text-lg">chevron_left</span>
                    </button>
                    <span class="px-2 text-sm font-medium">${data.page} / ${data.total_pages}</span>
                    <button class="p-1.5 rounded-md hover:bg-gray-100 text-gray-400 hover:text-primary transition-colors ${nextDisabled}" onclick="App.goPage(${data.page + 1})">
                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                    </button>
                </div>
            `;
        },

        goPage(page) {
            this.state.page = page;
            this.loadSubscriptions();
        },

        openModal(id = null) {
            document.getElementById('subscription-modal').classList.remove('hidden');
            if (id) {
                // TODO: 加载现有订阅数据进行编辑
                console.log('Edit subscription:', id);
            } else {
                document.getElementById('subscription-form').reset();
                const today = new Date().toISOString().split('T')[0];
                document.querySelector('input[name="start_date"]').value = today;
                document.querySelector('input[name="auto_renew"]').checked = true;
            }
        },

        closeModal() {
            document.getElementById('subscription-modal').classList.add('hidden');
        },

        async handleSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('auto_renew', e.target.querySelector('input[name="auto_renew"]').checked ? '1' : '0');
            formData.append('is_lifetime', e.target.querySelector('input[name="is_lifetime"]').checked ? '1' : '0');
            try {
                const res = await fetch('/api/subscriptions/add.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.closeModal();
                    this.loadSubscriptions();
                } else {
                    alert(data.message || '保存失败');
                }
            } catch (e) {
                console.error('Submit error:', e);
                alert('保存失败');
            }
        },

        async deleteSubscription(id) {
            if (!confirm('确定要删除此订阅吗？')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            try {
                const res = await fetch('/api/subscriptions/delete.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.loadSubscriptions();
                } else {
                    alert(data.message || '删除失败');
                }
            } catch (e) {
                console.error('Delete error:', e);
                alert('删除失败');
            }
        },

        statusClass(status) {
            const map = {
                active: 'bg-green-100 text-green-800',
                paused: 'bg-yellow-100 text-yellow-800',
                cancelled: 'bg-gray-100 text-gray-800'
            };
            return map[status] || 'bg-gray-100 text-gray-800';
        },

        statusText(status) {
            const map = { active: '活跃', paused: '已暂停', cancelled: '已取消' };
            return map[status] || status;
        },

        formatDate(dateStr) {
            const d = new Date(dateStr);
            const m = d.getMonth() + 1;
            const day = d.getDate();
            return `${m}月${day}日`;
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }
    };
    document.addEventListener('DOMContentLoaded', () => {
        App.init();
        if (typeof window.initNotificationPopover === 'function') {
            window.initNotificationPopover();
        }
    });
    </script>
</body>
</html>

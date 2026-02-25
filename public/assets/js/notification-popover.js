(() => {
    function createDot(btn) {
        let dot = btn.querySelector('.absolute.top-2.right-2');
        if (!dot) {
            dot = document.createElement('span');
            dot.className = 'absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white';
            btn.appendChild(dot);
        }
    }

    function removeDot(btn) {
        const dot = btn.querySelector('.absolute.top-2.right-2');
        if (dot) {
            dot.remove();
        }
    }

    function csrfToken() {
        return document.querySelector('input[name="csrf_token"]')?.value || '';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) {
            return '';
        }
        const d = new Date(dateStr + 'T00:00:00');
        if (Number.isNaN(d.getTime())) {
            return dateStr;
        }
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function buildPopover(btn) {
        const pop = document.createElement('div');
        pop.id = 'notification-popover';
        pop.className = 'hidden fixed w-96 max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-gray-200 shadow-xl';
        pop.style.zIndex = '9999';
        pop.innerHTML = `
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">待续费提醒</h3>
                <button type="button" data-action="close" class="text-gray-400 hover:text-gray-600">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div id="notification-popover-body" class="max-h-80 overflow-y-auto p-3 space-y-2 text-sm text-gray-600"></div>
            <div class="px-4 py-2 border-t border-gray-100 text-right">
                <a href="subscriptions.php?filter=active&sort=next_payment_date" class="text-xs text-primary hover:underline">前往订阅列表</a>
            </div>
        `;

        document.body.appendChild(pop);
        return pop;
    }

    function applyPopoverMaxHeight(popover) {
        const viewportMargin = 8;
        const maxPopoverHeight = Math.max(220, window.innerHeight - viewportMargin * 2);
        popover.style.maxHeight = `${maxPopoverHeight}px`;

        const body = popover.querySelector('#notification-popover-body');
        if (!body) {
            return;
        }

        const headerHeight = popover.firstElementChild?.offsetHeight || 52;
        const footerHeight = popover.lastElementChild?.offsetHeight || 40;
        const bodyMaxHeight = Math.max(120, maxPopoverHeight - headerHeight - footerHeight);
        body.style.maxHeight = `${bodyMaxHeight}px`;
    }

    function positionPopover(popover, triggerBtn) {
        const rect = triggerBtn.getBoundingClientRect();
        const popoverWidth = Math.min(384, window.innerWidth - 16);

        popover.style.width = `${popoverWidth}px`;
        applyPopoverMaxHeight(popover);

        let left = rect.right - popoverWidth;
        const minLeft = 8;
        const maxLeft = Math.max(8, window.innerWidth - popoverWidth - 8);
        if (left < minLeft) {
            left = minLeft;
        }
        if (left > maxLeft) {
            left = maxLeft;
        }

        const viewportMargin = 8;
        const popoverHeight = Math.min(popover.offsetHeight || 320, window.innerHeight - viewportMargin * 2);
        let top = rect.bottom + 8;

        const maxTop = window.innerHeight - popoverHeight - viewportMargin;
        if (top > maxTop) {
            const topAbove = rect.top - popoverHeight - 8;
            if (topAbove >= viewportMargin) {
                top = topAbove;
            } else {
                top = maxTop;
            }
        }

        popover.style.left = `${left}px`;
        popover.style.top = `${Math.max(viewportMargin, top)}px`;
    }


    function renderItems(popover, items) {
        const body = popover.querySelector('#notification-popover-body');
        if (!items.length) {
            body.innerHTML = '<div class="py-6 text-center text-gray-400">暂无待续费项目</div>';
            return;
        }

        body.innerHTML = items.map((item) => {
            const amount = Number(item.amount || 0).toFixed(2);
            return `
                <div class="border border-gray-100 rounded-xl p-3 flex items-start justify-between gap-3" data-item="${item.subscription_id}:${escapeHtml(item.next_payment_date)}">
                    <div>
                        <div class="font-medium text-gray-900">${escapeHtml(item.name)}</div>
                        <div class="text-xs text-gray-500 mt-1">${escapeHtml(item.currency_symbol || '')}${amount} · ${escapeHtml(formatDate(item.next_payment_date))}</div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 px-2.5 py-1 text-xs rounded-lg bg-gray-900 text-white hover:bg-black transition-colors"
                        data-action="mark-read"
                        data-subscription-id="${item.subscription_id}"
                        data-due-date="${escapeHtml(item.next_payment_date)}"
                    >
                        标记已读
                    </button>
                </div>
            `;
        }).join('');
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('Invalid response type');
        }
        return res.json();
    }

    async function fetchCheck(btn) {
        const data = await fetchJson('api/notifications/check.php');
        if (data.success && Number(data.count) > 0) {
            createDot(btn);
        } else {
            removeDot(btn);
        }
        return data;
    }

    async function fetchList(popover) {
        const body = popover.querySelector('#notification-popover-body');
        body.innerHTML = '<div class="py-6 text-center text-gray-400">加载中...</div>';

        const data = await fetchJson('api/notifications/list.php');
        if (!data.success) {
            body.innerHTML = '<div class="py-6 text-center text-red-400">加载失败</div>';
            return;
        }

        renderItems(popover, data.items || []);
    }


    async function markRead(btn, popover, triggerBtn) {
        const subscriptionId = Number(btn.dataset.subscriptionId || 0);
        const dueDate = btn.dataset.dueDate || '';
        const token = csrfToken();

        if (!token) {
            alert('CSRF token 不存在，无法提交');
            return;
        }

        btn.disabled = true;

        try {
            const data = await fetchJson('api/notifications/mark-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: token,
                    subscription_id: subscriptionId,
                    due_date: dueDate,
                }),
            });
            if (!data.success) {
                alert(data.message || '标记失败');
                btn.disabled = false;
                return;
            }

            const item = btn.closest('[data-item]');
            if (item) {
                item.remove();
            }

            const remaining = Number(data.unread_count || 0);
            if (remaining <= 0) {
                removeDot(triggerBtn);
                renderItems(popover, []);
            } else {
                createDot(triggerBtn);
            }
        } catch (e) {
            console.error('mark read failed', e);
            alert('标记失败');
            btn.disabled = false;
        }
    }

    function initNotificationPopover() {
        const triggerBtn = document.getElementById('notification-btn');
        if (!triggerBtn) {
            return;
        }

        const popover = buildPopover(triggerBtn);

        fetchCheck(triggerBtn).catch((err) => {
            console.error('notification check failed', err);
            removeDot(triggerBtn);
        });

        triggerBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const isHidden = popover.classList.contains('hidden');
            if (isHidden) {
                popover.classList.remove('hidden');
                positionPopover(popover, triggerBtn);
                fetchList(popover).catch((err) => {
                    console.error('notification list failed', err);
                    const body = popover.querySelector('#notification-popover-body');
                    body.innerHTML = '<div class="py-6 text-center text-red-400">加载失败</div>';
                });
            } else {
                popover.classList.add('hidden');
            }
        });

        window.addEventListener('resize', () => {
            if (!popover.classList.contains('hidden')) {
                positionPopover(popover, triggerBtn);
            }
        });

        window.addEventListener('scroll', () => {
            if (!popover.classList.contains('hidden')) {
                positionPopover(popover, triggerBtn);
            }
        }, true);

        popover.addEventListener('click', (event) => {
            event.stopPropagation();

            const closeBtn = event.target.closest('[data-action="close"]');
            if (closeBtn) {
                popover.classList.add('hidden');
                return;
            }

            const markBtn = event.target.closest('[data-action="mark-read"]');
            if (markBtn) {
                markRead(markBtn, popover, triggerBtn);
            }
        });

        document.addEventListener('click', (event) => {
            if (!popover.classList.contains('hidden') && !popover.contains(event.target) && event.target !== triggerBtn && !triggerBtn.contains(event.target)) {
                popover.classList.add('hidden');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                popover.classList.add('hidden');
            }
        });
    }

    window.initNotificationPopover = initNotificationPopover;
})();

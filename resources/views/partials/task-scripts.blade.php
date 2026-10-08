{{-- Общие JS: методы модалки задачи и API этапов (используют доска и «Мои задачи»).
     taskModalMethods() добавляется в Alpine-компонент через Object.assign. --}}
<script>
function taskModalMethods() {
    return {
        modal: {
            open: false,
            tab: 'stages',
            loading: false,
            taskId: null,
            data: {
                task: '', project: '', responsible: '', can_move: false, stages: [], history: [],
                description: null, due_date: null, due_date_raw: null, due: null, priority: 'normal',
                can_edit: false, items: { items: [], done: 0, total: 0, can_manage: false },
            },
            comments: [],
            canWrite: false,
        },
        // Поля трекера (этап 11)
        descOpen: false,
        descText: '',
        dueText: '',
        priorityText: 'normal',
        newItem: '',
        commentBody: '',
        // Подписка на событие открытия карточки (из ⋮ или карточек «Мои задачи»)
        initTaskModal() {
            window.addEventListener('open-task', (e) => {
                this.openTask(e.detail.taskId, e.detail.tab);
            });
        },
        async openTask(taskId, tab) {
            this.modal.tab = tab || 'stages';
            this.modal.taskId = taskId;
            this.modal.open = true;
            await this.loadDetail();
        },
        async loadDetail() {
            if (!this.modal.taskId) return;
            this.modal.loading = true;
            try {
                const r = await fetch('/project-tasks/' + this.modal.taskId + '/detail', {
                    headers: { 'Accept': 'application/json' },
                });
                this.modal.data = await r.json();
                this.descText = this.modal.data.description || '';
                this.dueText = this.modal.data.due_date_raw || '';
                this.priorityText = this.modal.data.priority || 'normal';
                await this.loadComments();
            } finally {
                this.modal.loading = false;
            }
        },
        async doStage(stage, action) {
            // Те же API, что drag/меню ⋮: start/reopen → move, finish → complete
            if (action === 'finish') {
                await window.completeStage(this.modal.taskId, stage.id);
            } else {
                await window.moveTask(this.modal.taskId, stage.id, {});
            }
            await this.loadDetail();
            this.syncCard(this.modal.taskId);
        },
        // --- Трекер задачи (этап 11) ---
        dueToneClass(due) {
            if (!due) return 'bg-slate-100 text-muted';
            if (due.tone === 'overdue') return 'bg-red-50 text-danger';
            if (due.tone === 'soon') return 'bg-amber-50 text-warn';
            return 'bg-slate-100 text-muted';
        },
        async saveTracker() {
            await this.trackerRequest('PATCH', '/project-tasks/' + this.modal.taskId, {
                description: this.descText || null,
                due_date: this.dueText || null,
                priority: this.priorityText,
            }, 'Не удалось сохранить. Недостаточно прав.');
        },
        async addItem() {
            const title = (this.newItem || '').trim();
            if (!title) return;
            const ok = await this.itemRequest('POST', '/project-tasks/' + this.modal.taskId + '/items', { title });
            if (ok) this.newItem = '';
        },
        async toggleItem(it) {
            await this.itemRequest('PATCH', '/project-tasks/' + this.modal.taskId + '/items/' + it.id, { is_done: !it.is_done });
        },
        async removeItem(id) {
            await this.itemRequest('DELETE', '/project-tasks/' + this.modal.taskId + '/items/' + id);
        },
        async itemRequest(method, url, body) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const opts = { method, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } };
            if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
            try {
                const r = await fetch(url, opts);
                if (!r.ok) throw new Error('forbidden');
                this.modal.data.items = await r.json();
                this.syncCard(this.modal.taskId);
                return true;
            } catch (e) {
                alert('Не удалось изменить чек-лист. Недостаточно прав.');
                return false;
            }
        },
        async trackerRequest(method, url, body, errorText) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                const r = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(body),
                });
                if (!r.ok) throw new Error('forbidden');
                await this.loadDetail();
                this.syncCard(this.modal.taskId);
            } catch (e) {
                alert(errorText);
            }
        },
        // Синхронизировать индикаторы карточки задачи (доска и «Мои задачи») без перезагрузки
        syncCard(taskId) {
            const d = this.modal.data;
            const all = (sel) => document.querySelectorAll('[data-task-id="' + taskId + '"] ' + sel);

            // Чек-лист «☑ N/M»
            const items = d.items || {};
            all('[data-checklist]').forEach((badge) => {
                if (items.total > 0) {
                    badge.textContent = '☑ ' + items.done + '/' + items.total;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            });

            // Приоритет: high / low / normal (normal — скрыт)
            const priority = d.priority || 'normal';
            all('[data-priority-badge]').forEach((badge) => {
                if (priority === 'normal') {
                    badge.classList.add('hidden');
                    return;
                }
                badge.textContent = priority === 'high' ? 'Высокий' : 'Низкий';
                badge.className = 'rounded font-medium px-2 py-0.5 text-[10px] whitespace-nowrap '
                    + (priority === 'high' ? 'bg-red-50 text-danger' : 'bg-slate-100 text-muted');
            });

            // Дедлайн: точка на доске
            const due = d.due;
            const dueDate = d.due_date;
            all('[data-due-dot]').forEach((dot) => {
                if (!dueDate) {
                    dot.classList.add('hidden');
                    return;
                }
                dot.classList.remove('hidden');
                dot.className = 'inline-block w-1.5 h-1.5 rounded-full shrink-0 '
                    + (due && due.tone === 'overdue' ? 'bg-danger' : 'bg-slate-300');
                dot.title = 'Дедлайн задачи: ' + dueDate;
            });

            // Дедлайн: бейдж на «Мои задачи»
            all('[data-due-badge]').forEach((badge) => {
                if (!dueDate) {
                    badge.classList.add('hidden');
                    return;
                }
                const tone = due ? due.tone : 'ok';
                badge.className = 'inline-block mt-2 rounded px-2 py-0.5 text-[11px] font-medium '
                    + (tone === 'overdue' ? 'bg-red-50 text-danger' : (tone === 'soon' ? 'bg-amber-50 text-warn' : 'bg-slate-100 text-muted'));
                badge.textContent = 'Дедлайн ' + dueDate + (due && due.label ? ' · ' + due.label : '');
            });

            // Прогресс этапов «X/5» и полоса
            const stages = d.stages || [];
            if (stages.length) {
                const done = stages.filter((s) => s.status === 'done').length;
                all('[data-stage-progress]').forEach((el) => {
                    el.textContent = done + '/' + stages.length;
                });
                const percent = Math.round((done / stages.length) * 100);
                all('[data-stage-bar]').forEach((el) => {
                    el.style.width = percent + '%';
                });
            }
        },
        nl2brHtml(text) {
            // Тело уже экранировано на сервере (CommentController::formatted) — только переносы строк
            return (text || '').replace(/\n/g, '<br>');
        },
        async loadComments() {
            const r = await fetch('/project-tasks/' + this.modal.taskId + '/comments', {
                headers: { 'Accept': 'application/json' },
            });
            const c = await r.json();
            this.modal.comments = c.comments;
            this.modal.canWrite = c.can_write;
        },
        autoGrow(el) {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        },
        async submitComment() {
            const body = (this.commentBody || '').trim();
            if (!body) return;
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/comments', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ body, project_task_id: this.modal.taskId }),
            }).then(r => {
                if (!r.ok) throw new Error('forbidden');
                return r.json();
            }).then(() => {
                this.commentBody = '';
                const inp = this.$refs.commentInput;
                if (inp) { inp.style.height = 'auto'; }
                this.loadComments();
            }).catch(() => {
                alert('Комментарий не добавлен. Возможно, недостаточно прав.');
            });
        },
        async removeComment(id) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/comments/' + id, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            }).then(r => {
                if (!r.ok) throw new Error('forbidden');
                return r.json();
            }).then(() => {
                this.loadComments();
            }).catch(() => {
                alert('Удалить комментарий нельзя.');
            });
        },
    };
}

// API этапов/задач: перенос, завершение, отрисовка статуса карточки.
// На странице без доски renderCardStatus просто не находит карточек (no-op).
window.moveTask = function (taskId, stageId, opts) {
    opts = opts || {};
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return fetch('/project-tasks/' + taskId + '/move', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ stage_id: stageId }),
    }).then(r => r.json()).then(data => {
        if (!data.ok) return;
        renderCardStatus(taskId, stageId, data.status, data.entered_at);
        if (opts.showPopover) {
            window.dispatchEvent(new CustomEvent('ask-complete', {
                detail: {
                    taskId,
                    stageId: opts.fromStageId,
                    stageName: opts.fromStageName,
                    x: opts.x,
                    y: opts.y,
                },
            }));
        }
    });
};

window.completeStage = function (taskId, stageId) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return fetch('/project-tasks/' + taskId + '/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ stage_id: stageId }),
    }).then(r => r.json()).then(data => {
        if (data.ok) renderCardStatus(taskId, stageId, data.status);
    });
};

function dateToDDMM(enteredAt) {
    return enteredAt ? enteredAt.slice(0, 5) : '';
}

function renderCardStatus(taskId, stageId, status, enteredAt) {
    document.querySelectorAll('.bccard[data-task-id="' + taskId + '"][data-stage-id="' + stageId + '"]').forEach(card => {
        card.classList.remove('bc-pending', 'bc-in_progress', 'bc-done', 'bc-draggable', 'bc-static');
        card.classList.add('bc-' + status, status === 'in_progress' ? 'bc-draggable' : 'bc-static');
        card.dataset.status = status;

        const dateEl = card.querySelector('[data-role="date"]');
        const checkEl = card.querySelector('[data-role="check"]');
        if (dateEl) {
            if (status === 'in_progress') {
                dateEl.textContent = dateToDDMM(enteredAt);
                dateEl.classList.remove('hidden');
            } else {
                dateEl.classList.add('hidden');
            }
        }
        if (checkEl) {
            checkEl.classList.toggle('hidden', status !== 'done');
        }
    });
}
</script>

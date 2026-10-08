@extends('layouts.app')

@section('title', 'Доска')

@section('content')
<div x-data="board()" class="relative">

    {{-- Шапка доски --}}
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-[24px] font-semibold">Доска</h1>

        @can('create', App\Models\Project::class)
            <button type="button" @click="openCreate()"
                    class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 transition-colors">
                Новый проект
            </button>
        @endcan
    </div>

    {{-- Ошибки валидации после отправки формы --}}
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-danger/40 text-danger px-4 py-3 text-[13px] space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Доска: проекты × 5 этапов × 4 задачи --}}
    <div class="bg-white border border-line rounded-xl shadow-sm overflow-auto">
        <table class="border-separate border-spacing-0 min-w-full">
            <thead>
                <tr>
                    <th class="sticky left-0 z-20 bg-white border-r border-b border-line px-3 py-2.5 text-left min-w-[220px] max-w-[260px] text-[12px] font-semibold text-muted uppercase">Проект</th>
                    @foreach ($stages as $stage)
                        <th class="border-r border-b border-line px-3 py-2.5 text-left text-[12px] font-semibold text-muted uppercase whitespace-nowrap min-w-[170px]">{{ $stage->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                <tr>
                    {{-- Колонка «Проект» — sticky при горизонтальной прокрутке --}}
                    <td class="sticky left-0 z-10 bg-white border-r border-b border-line px-3 py-2.5 align-top">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div title="{{ $project->title }}" class="font-semibold text-[13px] truncate">{{ $project->title }}</div>
                                <div class="text-[11px] text-muted mt-0.5 whitespace-nowrap">
                                    {{ $project->start_date->format('d.m.Y') }} → {{ $project->due_date->format('d.m.Y') }}
                                </div>
                                <div class="mt-1.5">
                                    @switch($project->status)
                                        @case('active')
                                            <span class="rounded bg-accent/10 text-accent font-medium px-2 py-0.5 text-[11px]">Активен</span>
                                            @break
                                        @case('paused')
                                            <span class="rounded bg-amber-50 text-warn font-medium px-2 py-0.5 text-[11px]">Приостановлен</span>
                                            @break
                                        @case('completed')
                                            <span class="rounded bg-green-50 text-done font-medium px-2 py-0.5 text-[11px]">Завершён</span>
                                            @break
                                        @case('cancelled')
                                            <span class="rounded bg-red-50 text-danger font-medium px-2 py-0.5 text-[11px]">Отменён</span>
                                            @break
                                    @endswitch
                                </div>
                            </div>
                            @can('update', $project)
                                <a href="{{ route('projects.edit', $project) }}" title="Редактировать" class="text-muted hover:text-accent shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.84 3.75A1.75 1.75 0 016.125 5.1H4.375A.375.375 0 004 5.475v10.15c0 .207.168.375.375.375h11.25a.375.375 0 00.375-.375V5.475a.375.375 0 00-.375-.375h-1.75A1.75 1.75 0 0112.16 3.75H7.84zm8.41 3.75A1.25 1.25 0 0116.5 8.5v8a1.5 1.5 0 01-1.5 1.5H5a1.5 1.5 0 01-1.5-1.5v-8A1.25 1.25 0 014.75 7.5h.625a1.875 1.875 0 001.874-1.735.375.375 0 01.375-.39h5.752a.375.375 0 01.375.39 1.875 1.875 0 001.874 1.735h.625z" clip-rule="evenodd"/>
                                        <path d="M12.5 11.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                    </svg>
                                </a>
                                @endcan
                        </div>
                    </td>

                    {{-- 5 колонок этапов. В каждой ячейке ровно 4 карточки в фиксированном порядке --}}
                    @foreach ($stages as $stage)
                    <td class="border-r border-b border-line px-2 py-2 align-top">
                        <div class="kanban-cell flex flex-col gap-1.5 min-h-[120px]"
                             data-stage-id="{{ $stage->id }}"
                             data-stage-name="{{ $stage->name }}">
                            @foreach ($taskTypes as $taskType)
                                @php
                                    $task = $project->projectTasks->firstWhere('task_type_id', $taskType->id);
                                    $ss = $task?->stageStatuses->firstWhere('stage_id', $stage->id);
                                    $cardStatus = $ss ? $ss->status : 'pending';
                                @endphp
                                @if ($task && $ss)
                                <div class="bccard bc-{{ $cardStatus }} {{ $cardStatus === 'in_progress' ? 'bc-draggable' : 'bc-static' }} {{ auth()->user()->can('move', $task) ? '' : 'bc-nodrag' }}"
                                     data-task-id="{{ $task->id }}"
                                     data-stage-id="{{ $stage->id }}"
                                     data-status="{{ $cardStatus }}"
                                     data-can-move="{{ auth()->user()->can('move', $task) ? '1' : '0' }}"
                                     title="{{ $taskType->name }} — {{ $stage->name }}"
                                     @click="openTask({{ $task->id }})">
                                    <div class="flex items-start justify-between gap-1">
                                        <span class="text-[12px] font-medium leading-tight truncate">{{ $taskType->name }}</span>

                                        {{-- Меню ⋮ — альтернатива drag, работает всегда --}}
                                        <div class="relative shrink-0" x-data="cardMenu()" @click.stop>
                                            <button type="button" @click="open = !open" class="text-muted hover:text-ink rounded leading-none px-0.5" aria-label="Меню">⋮</button>
                                            <div x-show="open" x-cloak @click.outside="open = false"
                                                 class="absolute right-0 top-full mt-1 z-20 w-48 bg-white border border-line rounded-lg shadow-lg py-1 text-[12px]">
                                                @can('move', $task)
                                                <div class="px-3 py-1 font-medium text-muted">Перенести на этап…</div>
                                                @foreach ($stages as $menuStage)
                                                    <button type="button"
                                                            @click="moveTo({{ $menuStage->id }})"
                                                            class="w-full text-left px-3 py-1.5 hover:bg-page"
                                                            :class="{'opacity-40 pointer-events-none': {{ $menuStage->id }} === {{ $stage->id }}}">
                                                        {{ $menuStage->name }}
                                                    </button>
                                                @endforeach
                                                <div class="border-t border-line my-1"></div>
                                                <button type="button" @click="finish()"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-page">
                                                    Завершить этап
                                                </button>
                                                @endcan
                                                <button type="button" @click="showCard()"
                                                        class="w-full text-left px-3 py-1.5 hover:bg-page">
                                                    Открыть карточку
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between mt-1">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent/10 text-accent text-[10px] font-bold shrink-0">{{ $task->responsible?->initials }}</span>
                                        <div class="flex items-center gap-1.5">
                                            <span data-role="date" title="История этапа"
                                                  class="text-[10px] text-muted hover:text-accent cursor-pointer {{ $cardStatus === 'in_progress' ? '' : 'hidden' }}"
                                                  @click.stop="openTask({{ $task->id }}, 'history')">{{ $ss->entered_at?->format('d.m') }}</span>
                                            <span data-role="check" class="text-[12px] font-bold text-done leading-none {{ $cardStatus === 'done' ? '' : 'hidden' }}">✓</span>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-muted">
                        Нет активных проектов. Создайте проект.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Поповер «Завершить этап?» у исходной колонки после переноса --}}
    <div x-show="pop.ask" x-cloak
         class="fixed z-50 w-72 bg-white border border-line rounded-lg shadow-xl p-4"
         :style="{ left: (pop.x - 144) + 'px', top: pop.y + 'px' }">
        <p class="text-[13px] text-ink mb-3">
            Завершить этап <span class="font-semibold" x-text="pop.stageName"></span>?
        </p>
        <div class="flex gap-2 justify-end">
            <button type="button" @click="keep()" class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium hover:bg-page">Оставить в работе</button>
            <button type="button" @click="finishPop()" class="rounded-lg bg-accent hover:bg-accent-hover px-3 py-1.5 text-sm font-medium text-white transition-colors">Завершить</button>
        </div>
    </div>

    {{-- Модалка карточки задачи: вкладки «Этапы» / «История» / «Комментарии» --}}
    <div x-show="modal.open" x-cloak @keydown.escape.window="modal.open = false"
         class="fixed inset-0 z-40 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="modal.open = false"></div>
        <div class="relative bg-white border border-line rounded-xl shadow-lg w-full max-w-xl max-h-[90vh] flex flex-col">
            <div class="p-5 pb-0 border-b border-line">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-[16px] font-semibold leading-tight">
                            <span x-text="modal.data.task"></span> — <span x-text="modal.data.project"></span>
                        </h2>
                        <p class="text-[13px] text-muted mt-0.5">
                            Ответственный: <span class="text-ink font-medium" x-text="modal.data.responsible"></span>
                        </p>
                    </div>
                    <button type="button" @click="modal.open = false" class="text-muted hover:text-ink text-[18px] leading-none">&times;</button>
                </div>

                {{-- Вкладки --}}
                <div class="flex gap-4 mt-3 text-[13px] font-medium">
                    <button type="button" @click="modal.tab = 'stages'"
                            :class="modal.tab === 'stages' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                            class="pb-2 border-b-2">Этапы</button>
                    <button type="button" @click="modal.tab = 'history'"
                            :class="modal.tab === 'history' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                            class="pb-2 border-b-2">История</button>
                    <button type="button" @click="modal.tab = 'comments'"
                            :class="modal.tab === 'comments' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                            class="pb-2 border-b-2">Комментарии</button>
                </div>
            </div>

            <div class="p-5 overflow-y-auto flex-1">
                <div x-show="modal.loading" x-cloak class="py-10 text-center text-[13px] text-muted">Загружаем…</div>

                {{-- Вкладка «Этапы» --}}
                <div x-show="!modal.loading && modal.tab === 'stages'" x-cloak class="space-y-2">
                    <template x-for="st in modal.data.stages">
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-[13px] font-medium truncate" x-text="st.name"></span>
                                <span x-show="st.status === 'pending'"
                                      class="rounded bg-slate-100 text-muted px-2 py-0.5 text-[11px] shrink-0">Ожидает</span>
                                <span x-show="st.status === 'in_progress'"
                                      class="rounded bg-accent/10 text-accent px-2 py-0.5 text-[11px] shrink-0">В работе · <span x-text="st.entered_at"></span></span>
                                <span x-show="st.status === 'done'"
                                      class="rounded bg-green-50 text-done px-2 py-0.5 text-[11px] shrink-0">Завершено</span>
                            </div>
                            <div class="flex gap-1.5 shrink-0" x-show="modal.data.can_move">
                                <button x-show="st.status === 'pending'" type="button" @click="doStage(st, 'start')"
                                        class="rounded-lg border border-accent/40 text-accent px-2.5 py-1 text-[12px] font-medium hover:border-accent">Начать</button>
                                <button x-show="st.status === 'in_progress'" type="button" @click="doStage(st, 'finish')"
                                        class="rounded-lg border border-line px-2.5 py-1 text-[12px] font-medium hover:bg-page">Завершить</button>
                                <button x-show="st.status === 'done'" type="button" @click="doStage(st, 'reopen')"
                                        class="rounded-lg border border-accent/40 text-accent px-2.5 py-1 text-[12px] font-medium hover:border-accent">Вернуть в работу</button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Вкладка «История» --}}
                <div x-show="!modal.loading && modal.tab === 'history'" x-cloak class="space-y-1.5">
                    <template x-for="h in modal.data.history">
                        <div class="flex gap-2 text-[13px] leading-snug">
                            <span class="text-muted whitespace-nowrap" x-text="h.time"></span>
                            <span class="text-muted shrink-0" x-text="h.user + ':'"></span>
                            <span class="min-w-0" x-text="h.text"></span>
                        </div>
                    </template>
                    <p x-show="modal.data.history.length === 0" class="text-[13px] text-muted">Пока нет записей.</p>
                </div>

                {{-- Вкладка «Комментарии» --}}
                <div x-show="!modal.loading && modal.tab === 'comments'" x-cloak>
                    {{-- Форма: ответственный или руководитель --}}
                    <template x-if="modal.canWrite">
                        <div>
                            <textarea x-ref="commentInput" rows="2" x-model="commentBody"
                                      @input="autoGrow($el)"
                                      @keydown.ctrl.enter="submitComment()"
                                      placeholder="Написать комментарий…"
                                      class="w-full rounded-lg border border-line px-3 py-2 text-sm resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent"></textarea>
                            <div class="flex justify-end mt-1.5">
                                <button type="button" @click="submitComment()"
                                        class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-1.5 transition-colors">
                                    Отправить
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Подпись для тех, кто не может комментировать --}}
                    <template x-if="!modal.canWrite">
                        <p class="text-[12px] text-muted rounded-lg bg-page border border-line px-3 py-2">
                            Комментировать может ответственный или руководитель.
                        </p>
                    </template>

                    {{-- Лента комментариев --}}
                    <div class="space-y-3.5 mt-4">
                        <template x-for="c in modal.comments" :key="c.id">
                            <div class="flex gap-2.5">
                                <span class="w-8 h-8 rounded-full shrink-0 inline-flex items-center justify-center text-[11px] font-bold"
                                      :class="c.user.color" x-text="c.user.initials"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-[13px] font-semibold" x-text="c.user.name"></span>
                                        <span class="text-[11px] text-muted whitespace-nowrap" x-text="c.time"></span>
                                    </div>
                                    <p class="text-[13px] leading-relaxed mt-0.5 break-words" x-html="nl2brHtml(c.body)"></p>
                                </div>
                                <button x-show="c.canDelete" type="button"
                                        @click="if (confirm('Удалить комментарий?')) removeComment(c.id)"
                                        class="text-muted hover:text-danger leading-none shrink-0 mt-0.5" title="Удалить">&times;</button>
                            </div>
                        </template>
                        <p x-show="modal.comments.length === 0" class="text-[13px] text-muted">Комментариев пока нет.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Модалка: новый проект (manager) --}}
    @can('create', App\Models\Project::class)
    <div x-show="project.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="project.open = false"></div>
        <div class="relative bg-white border border-line rounded-xl shadow-lg w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h2 class="text-[16px] font-semibold mb-4">Новый проект</h2>

            <form method="POST" action="{{ route('projects.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-[13px] font-medium mb-1">Название</label>
                    <input name="title" type="text" required maxlength="100" x-model="project.form.title"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium mb-1">Дата начала</label>
                        <input name="start_date" type="date" required x-model="project.form.start_date"
                               class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium mb-1">Желаемая дата завершения</label>
                        <input name="due_date" type="date" required :min="project.form.start_date" x-model="project.form.due_date"
                               class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    </div>
                </div>

                <div class="border-t border-line pt-4 space-y-3">
                    @foreach ($taskTypes as $taskType)
                    <div>
                        <label class="block text-[13px] font-medium mb-1">Ответственный: {{ $taskType->name }}</label>
                        <select name="responsible[{{ $taskType->id }}]" x-model="project.form.responsible[{{ $taskType->id }}]"
                                class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                            <option value="" disabled>— выберите —</option>
                            @foreach ($activeUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                    <p class="text-[13px] text-muted">Один человек может отвечать за несколько задач.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="project.open = false"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">
                        Отмена
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 transition-colors">
                        Создать
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>

<style>
    .bccard {
        border-radius: 8px;
        border: 1px solid #E2E8F0;
        padding: 6px 8px;
        cursor: pointer;
        min-height: 46px;
    }
    .bc-pending { background: #F8FAFC; color: #64748B; }
    .bc-in_progress { background: #fff; border-color: #2563EB; border-left: 3px solid #2563EB; }
    .bc-done { background: #F0FDF4; border-color: #16A34A; color: #16A34A; }
    .bc-draggable { }
    .bc-static { cursor: default; }
    .bc-nodrag { cursor: default; }
</style>

<script>
function board() {
    return {
        pop: { ask: false, taskId: null, stageId: null, stageName: '', x: 0, y: 0 },
        modal: {
            open: false,
            tab: 'stages',
            loading: false,
            taskId: null,
            data: { task: '', project: '', responsible: '', can_move: false, stages: [], history: [] },
            comments: [],
            canWrite: false,
        },
        project: {
            open: false,
            form: { title: '', start_date: '', due_date: '', responsible: {} },
        },
        commentBody: '',
        init() {
            document.addEventListener('ask-complete', (e) => {
                this.pop = {
                    ask: true,
                    taskId: e.detail.taskId,
                    stageId: e.detail.stageId,
                    stageName: e.detail.stageName,
                    x: e.detail.x,
                    y: e.detail.y,
                };
            });
            document.addEventListener('open-task', (e) => {
                this.openTask(e.detail.taskId, e.detail.tab);
            });
        },
        openCreate() {
            this.project.form = { title: '', start_date: '', due_date: '', responsible: {} };
            this.project.open = true;
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
                await this.loadComments();
            } finally {
                this.modal.loading = false;
            }
        },
        async doStage(stage, action) {
            // Здесь те же API, что и drag/меню ⋮: start/reopen → move, finish → complete
            if (action === 'finish') {
                await window.completeStage(this.modal.taskId, stage.id);
            } else {
                await window.moveTask(this.modal.taskId, stage.id, {});
            }
            await this.loadDetail();
        },
        finishPop() {
            if (!this.pop.taskId) return;
            window.completeStage(this.pop.taskId, this.pop.stageId);
            this.keep();
        },
nl2brHtml(text) {
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

function cardMenu() {
    return {
        open: false,
        moveTo(stageId) {
            const taskId = this.$el.closest('.bccard').dataset.taskId;
            window.moveTask(taskId, stageId, {});
            this.open = false;
        },
        finish() {
            const card = this.$el.closest('.bccard');
            window.completeStage(card.dataset.taskId, card.dataset.stageId);
            this.open = false;
        },
        showCard() {
            const card = this.$el.closest('.bccard');
            window.dispatchEvent(new CustomEvent('open-task', {
                detail: { taskId: card.dataset.taskId },
            }));
            this.open = false;
        },
    };
}

    let dragState = null;

    function initBoard() {
        document.querySelectorAll('.kanban-cell').forEach(cell => {
            new Sortable(cell, {
                group: { name: 'board', pull: true, put: true },
                sort: false,
                filter: '.bc-static, .bc-nodrag',
                onStart(evt) {
                    dragState = {
                        taskId: evt.item.dataset.taskId,
                        fromStageId: cell.dataset.stageId,
                        fromStageName: cell.dataset.stageName,
                    };
                },
                onAdd(evt) {
                    // DOM не двигаем — только данные. Возвращаем карточку обратно.
                    evt.from.appendChild(evt.item);
                    if (!dragState) return;
                    const toCell = evt.to.closest('.kanban-cell');
                    const toStageId = toCell?.dataset.stageId;
                    if (!toStageId || toStageId === dragState.fromStageId) return;

                    const rect = cell.getBoundingClientRect();
                    window.moveTask(dragState.taskId, toStageId, {
                        fromStageId: dragState.fromStageId,
                        fromStageName: dragState.fromStageName,
                        showPopover: true,
                        x: rect.left + rect.width / 2,
                        y: rect.top + rect.height + 8,
                    });
                },
            });
        });
    }

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
            if (opts.showPopover && dragState) {
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

    document.addEventListener('DOMContentLoaded', initBoard);
</script>
@endsection
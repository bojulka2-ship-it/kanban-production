@extends('layouts.app')

@section('title', 'Доска')

{{-- Доска — во всю ширину экрана (полная матрица 5 этапов), в отличие от остальных страниц --}}
@section('container', 'max-w-[1920px]')

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

    {{-- Панель фильтров: серверные GET-параметры, комбинируются --}}
    <div class="mb-4" x-data="{ open: false, mobile: window.matchMedia('(max-width: 767px)').matches }">
        <button type="button" x-show="mobile" @click="open = !open"
                class="mb-2 w-full rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium hover:bg-page">
            Фильтры
        </button>

        <form method="GET" action="{{ route('home') }}"
              x-show="!mobile || open" x-cloak
              class="flex flex-wrap items-end gap-3 rounded-xl bg-white border border-line shadow-sm px-4 py-3">
            <div class="flex flex-col gap-1 min-w-[170px] flex-1 max-w-[260px]">
                <label class="text-[11px] font-medium text-muted uppercase">Поиск</label>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Название проекта…"
                       class="rounded-lg border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-medium text-muted uppercase">Ответственный</label>
                <select name="responsible" @change="$el.form.submit()"
                        class="rounded-lg border border-line px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    <option value="">Все ответственные</option>
                    @foreach ($activeUsers as $u)
                        <option value="{{ $u->id }}" @selected($filters['responsible'] === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[11px] font-medium text-muted uppercase">Статус</label>
                <select name="status" @change="$el.form.submit()"
                        class="rounded-lg border border-line px-3 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    <option value="">Все активные</option>
                    @foreach (['active' => 'Активен', 'paused' => 'Приостановлен', 'completed' => 'Завершён', 'cancelled' => 'Отменён'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 pb-1.5 text-sm cursor-pointer select-none">
                <input type="checkbox" name="overdue" value="1" @checked($filters['overdue']) @change="$el.form.submit()"
                       class="rounded border-line text-accent focus:ring-accent/20">
                Только просроченные
            </label>

            <div class="flex items-center gap-4 pb-1.5">
                <label class="flex items-center gap-1.5 text-sm cursor-pointer select-none">
                    <input type="radio" name="archive" value="0" @checked(!$filters['archive']) @change="$el.form.submit()"
                           class="border-line text-accent focus:ring-accent/20">
                    Активные
                </label>
                <label class="flex items-center gap-1.5 text-sm cursor-pointer select-none">
                    <input type="radio" name="archive" value="1" @checked($filters['archive']) @change="$el.form.submit()"
                           class="border-line text-accent focus:ring-accent/20">
                    Архив
                </label>
            </div>

            @if ($filters['q'] !== '' || $filters['responsible'] > 0 || $filters['status'] !== '' || $filters['overdue'] || $filters['archive'])
                <a href="{{ route('home') }}" title="Сбросить фильтры"
                   class="flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-muted hover:text-danger hover:border-danger/40 hover:bg-page transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M6 6l8 8M14 6l-8 8"/>
                    </svg>
                    Сбросить
                </a>
            @endif
        </form>
    </div>

    {{-- Доска (md+): проекты × 5 этапов × 4 задачи. Свой скролл: шапка и колонка «Проект» sticky --}}
    <div class="hidden md:block bg-white border border-line rounded-xl shadow-sm overflow-auto max-h-[calc(100vh-13rem)]">
        <table class="border-separate border-spacing-0 min-w-full">
            <thead>
                <tr>
                    <th class="sticky left-0 top-0 z-30 bg-white border-r border-b border-line px-3 py-2.5 text-left min-w-[220px] max-w-[260px] text-[12px] font-semibold text-muted uppercase">Проект</th>
                    @foreach ($stages as $stage)
                        <th class="sticky top-0 z-10 bg-white border-r border-b border-line px-3 py-2.5 text-left text-[12px] font-semibold text-muted uppercase whitespace-nowrap min-w-[170px]">{{ $stage->name }}</th>
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
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
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

                                    {{-- Подсветка сроков: красный просрочен, жёлтый ≤3 дней (только активный неархивный) --}}
                                    @if ($project->status === 'active' && !$project->is_archived)
                                        @php
                                            // Отрицательное — дедлайн в прошлом (просрочен)
                                            $daysLeft = (int) now()->startOfDay()->diffInDays($project->due_date->copy()->startOfDay(), false);
                                        @endphp
                                        @if ($daysLeft < 0)
                                            <span class="rounded bg-red-50 text-danger font-medium px-2 py-0.5 text-[11px]">Просрочен на {{ -$daysLeft }} дн.</span>
                                        @elseif ($daysLeft <= 3)
                                            <span class="rounded bg-amber-50 text-warn font-medium px-2 py-0.5 text-[11px]">
                                                {{ $daysLeft === 0 ? 'Осталось сегодня' : 'Осталось ' . $daysLeft . ' дн.' }}
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                {{-- Архив: восстановление прямо из строки (страница редактирования для архива закрыта) --}}
                                @if ($filters['archive'])
                                    @can('restore', $project)
                                        <form method="POST" action="{{ route('projects.restore', $project) }}" class="mt-2">
                                            @csrf
                                            <button type="submit"
                                                    class="rounded-lg border border-line bg-white px-3 py-1.5 text-[12px] font-medium hover:bg-page">
                                                Восстановить
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </div>
                        @can('update', $project)
                                <a href="{{ route('projects.edit', $project) }}" title="Редактировать" class="text-muted hover:text-accent shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712Z"/>
                                        <path d="M19.513 8.199l-3.712-3.712-12.15 12.15a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32L19.513 8.2Z"/>
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
                                            <button type="button" @click="open = !open"
                                                    class="text-muted hover:text-ink rounded leading-none flex items-center justify-center min-h-[44px] min-w-[44px] md:min-h-0 md:min-w-0 md:px-0.5" aria-label="Меню">⋮</button>
                                            <div x-show="open" x-cloak @click.outside="open = false"
                                                 class="absolute right-0 top-full mt-1 z-20 w-48 bg-white border border-line rounded-lg shadow-lg py-1 text-[12px]">
                                                @can('move', $task)
                                                <div class="px-3 py-1 font-medium text-muted">Перенести на этап…</div>
                                                @foreach ($stages as $menuStage)
                                                    <button type="button"
                                                            @click="moveTo({{ $menuStage->id }})"
                                                            class="w-full text-left px-3 py-3 md:py-1.5 hover:bg-page"
                                                            :class="{'opacity-40 pointer-events-none': {{ $menuStage->id }} === {{ $stage->id }}}">
                                                        {{ $menuStage->name }}
                                                    </button>
                                                @endforeach
                                                <div class="border-t border-line my-1"></div>
                                                <button type="button" @click="finish()"
                                                        class="w-full text-left px-3 py-3 md:py-1.5 hover:bg-page">
                                                    Завершить этап
                                                </button>
                                                @endcan
                                                <button type="button" @click="showCard()"
                                                        class="w-full text-left px-3 py-3 md:py-1.5 hover:bg-page">
                                                    Открыть карточку
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Трекер (этап 11): прогресс чек-листа «N/M» и точка дедлайна --}}
                                    @php
                                        $taskItemsTotal = $task->items_count ?? 0;
                                        $taskItemsDone = $task->items_done_count ?? 0;
                                        $taskDueOverdue = $task->due_date && $task->due_date->copy()->startOfDay()->lt(now()->startOfDay());
                                    @endphp
                                    @if ($taskItemsTotal > 0 || $task->due_date)
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            @if ($taskItemsTotal > 0)
                                                <span data-checklist class="text-[10px] text-muted" title="Выполнено пунктов чек-листа">☑ {{ $taskItemsDone }}/{{ $taskItemsTotal }}</span>
                                            @endif
                                            @if ($task->due_date)
                                                <span title="Дедлайн задачи: {{ $task->due_date->format('d.m.Y') }}"
                                                      class="inline-block w-1.5 h-1.5 rounded-full shrink-0 {{ $taskDueOverdue ? 'bg-danger' : 'bg-slate-300' }}"></span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between mt-1">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent/10 text-accent text-[10px] font-bold shrink-0">{{ $task->responsible?->initials }}</span>
                                        <div class="flex items-center gap-1.5">
                                            @if ($filters['archive'])
                                                <span class="rounded bg-slate-100 text-muted px-1.5 py-0.5 text-[10px] shrink-0">Архив</span>
                                            @endif
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
                    <td colspan="6" class="px-4 py-10 text-center">
                        @if ($filters['q'] !== '' || $filters['responsible'] > 0 || $filters['status'] !== '' || $filters['overdue'] || $filters['archive'])
                            <p class="text-muted text-[14px]">Ничего не найдено, измените фильтры</p>
                            <a href="{{ route('home') }}"
                               class="inline-block mt-3 rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">Сбросить</a>
                        @else
                            <p class="text-muted">Нет активных проектов. Создайте проект.</p>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Мобильный список проектов (<768): карточки вместо таблицы. Тап → экран проекта --}}
    <div class="md:hidden space-y-3">
        @forelse ($projects as $project)
            <button type="button" @click="openProject({{ $project->id }})"
                    class="w-full text-left bg-white border border-line rounded-xl shadow-sm px-4 py-3 active:bg-page">
                <div class="font-semibold text-[14px] truncate">{{ $project->title }}</div>
                <div class="text-[11px] text-muted mt-0.5">
                    {{ $project->start_date->format('d.m.Y') }} → {{ $project->due_date->format('d.m.Y') }}
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-1.5">
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

                    @if ($project->status === 'active' && !$project->is_archived)
                        @php
                            $daysLeft = (int) now()->startOfDay()->diffInDays($project->due_date->copy()->startOfDay(), false);
                        @endphp
                        @if ($daysLeft < 0)
                            <span class="rounded bg-red-50 text-danger font-medium px-2 py-0.5 text-[11px]">Просрочен на {{ -$daysLeft }} дн.</span>
                        @elseif ($daysLeft <= 3)
                            <span class="rounded bg-amber-50 text-warn font-medium px-2 py-0.5 text-[11px]">
                                {{ $daysLeft === 0 ? 'Осталось сегодня' : 'Осталось ' . $daysLeft . ' дн.' }}
                            </span>
                        @endif
                    @endif

                    @if ($filters['archive'])
                        <span class="rounded bg-slate-100 text-muted px-2 py-0.5 text-[11px]">Архив</span>
                    @endif
                </div>

                {{-- Архив: восстановление прямо из карточки --}}
                @if ($filters['archive'])
                    @can('restore', $project)
                        <form method="POST" action="{{ route('projects.restore', $project) }}" class="mt-2">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-line bg-white px-3 py-2 text-[12px] font-medium hover:bg-page">
                                Восстановить
                            </button>
                        </form>
                    @endcan
                @endif

                {{-- Мини-прогресс: по каждой задаче выполнено этапов из 5 --}}
                <div class="mt-2.5 space-y-1.5">
                    @foreach ($project->projectTasks->sortBy('taskType.sort_order') as $task)
                        @php
                            $doneCount = $task->stageStatuses->where('status', 'done')->count();
                            $totalCount = $stages->count();
                            $isComplete = $doneCount === $totalCount;
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-muted w-24 shrink-0 truncate">{{ $task->taskType->name }}</span>
                            <div class="flex-1 h-1.5 rounded-full bg-line overflow-hidden">
                                <div class="h-full rounded-full {{ $isComplete ? 'bg-done' : 'bg-accent' }}"
                                     style="width: {{ (int) round($doneCount / max($totalCount, 1) * 100) }}%"></div>
                            </div>
                            <span class="text-[11px] {{ $isComplete ? 'text-done font-medium' : 'text-muted' }} w-7 text-right">{{ $doneCount }}/{{ $totalCount }}</span>
                        </div>
                    @endforeach
                </div>
            </button>
        @empty
            <div class="bg-white border border-line rounded-xl px-4 py-8 text-center">
                @if ($filters['q'] !== '' || $filters['responsible'] > 0 || $filters['status'] !== '' || $filters['overdue'] || $filters['archive'])
                    <p class="text-muted text-[14px]">Ничего не найдено, измените фильтры</p>
                    <a href="{{ route('home') }}"
                       class="inline-block mt-3 rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">Сбросить</a>
                @else
                    <p class="text-muted">Нет активных проектов. Создайте проект.</p>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Экран проекта (mobile <768): 5 секций этапов вертикально, drag отключён, перенос через меню ⋮ --}}
    <div x-show="proj.open" x-cloak @keydown.escape.window="closeProject()"
         class="fixed inset-0 z-40 bg-white overflow-y-auto md:hidden">
        @foreach ($projects as $project)
        <div x-show="proj.id === {{ $project->id }}">
            {{-- Шапка с возвратом в список --}}
            <div class="sticky top-0 bg-white border-b border-line px-2 py-2 flex items-center gap-2">
                <button type="button" @click="closeProject()" aria-label="Назад"
                        class="min-h-[44px] min-w-[44px] flex items-center justify-center text-[20px] text-ink">←</button>
                <div class="min-w-0">
                    <div class="font-semibold text-[15px] truncate">{{ $project->title }}</div>
                    <div class="text-[11px] text-muted">
                        {{ $project->start_date->format('d.m.Y') }} → {{ $project->due_date->format('d.m.Y') }}
                        · {{ ['active' => 'Активен', 'paused' => 'Приостановлен', 'completed' => 'Завершён', 'cancelled' => 'Отменён'][$project->status] ?? $project->status }}
                    </div>
                </div>
            </div>

            {{-- Секции этапов --}}
            <div class="px-3 py-3 space-y-4 pb-10">
                @foreach ($stages as $stage)
                <section>
                    <h3 class="text-[12px] font-semibold text-muted uppercase mb-2">{{ $stage->name }}</h3>
                    <div class="space-y-2">
                        @foreach ($taskTypes as $taskType)
                            @php
                                $task = $project->projectTasks->firstWhere('task_type_id', $taskType->id);
                                $ss = $task?->stageStatuses->firstWhere('stage_id', $stage->id);
                                $cardStatus = $ss ? $ss->status : 'pending';
                            @endphp
                            @if ($task && $ss)
                            <div class="bccard bc-{{ $cardStatus }}"
                                 data-task-id="{{ $task->id }}"
                                 data-stage-id="{{ $stage->id }}"
                                 data-status="{{ $cardStatus }}">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openTask({{ $task->id }})"
                                            class="flex-1 min-w-0 text-left min-h-[44px] flex flex-col justify-center gap-0.5">
                                        <span class="text-[13px] font-medium truncate">{{ $taskType->name }}</span>
                                        <span class="flex items-center gap-2 text-[11px]">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent/10 text-accent text-[10px] font-bold">{{ $task->responsible?->initials }}</span>
                                            <span class="text-muted">{{ $task->responsible?->name }}</span>
                                            <span data-role="date" class="{{ $cardStatus === 'in_progress' ? '' : 'hidden' }}">{{ $ss->entered_at?->format('d.m.Y') }}</span>
                                            <span data-role="check" class="font-bold text-done {{ $cardStatus === 'done' ? '' : 'hidden' }}">✓</span>
                                            @if (($task->items_count ?? 0) > 0)
                                                <span data-checklist class="text-muted">☑ {{ $task->items_done_count ?? 0 }}/{{ $task->items_count }}</span>
                                            @endif
                                            @if ($task->due_date)
                                                <span title="Дедлайн задачи: {{ $task->due_date->format('d.m.Y') }}"
                                                      class="inline-block w-1.5 h-1.5 rounded-full shrink-0 {{ $task->due_date->copy()->startOfDay()->lt(now()->startOfDay()) ? 'bg-danger' : 'bg-slate-300' }}"></span>
                                            @endif
                                        </span>
                                    </button>

                                    {{-- Меню ⋮: перенос без drag --}}
                                    <div class="relative shrink-0" x-data="cardMenu()" @click.stop>
                                        <button type="button" @click="open = !open"
                                                class="text-muted hover:text-ink rounded leading-none flex items-center justify-center min-h-[44px] min-w-[44px]" aria-label="Меню">⋮</button>
                                        <div x-show="open" x-cloak @click.outside="open = false"
                                             class="absolute right-0 top-full mt-1 z-20 w-52 bg-white border border-line rounded-lg shadow-lg py-1 text-[13px]">
                                            @can('move', $task)
                                            <div class="px-3 py-1.5 font-medium text-muted">Перенести на этап…</div>
                                            @foreach ($stages as $menuStage)
                                                <button type="button"
                                                        @click="moveTo({{ $menuStage->id }})"
                                                        class="w-full text-left px-3 py-3 hover:bg-page"
                                                        :class="{'opacity-40 pointer-events-none': {{ $menuStage->id }} === {{ $stage->id }}}">
                                                    {{ $menuStage->name }}
                                                </button>
                                            @endforeach
                                            <div class="border-t border-line my-1"></div>
                                            <button type="button" @click="finish()"
                                                    class="w-full text-left px-3 py-3 hover:bg-page">
                                                Завершить этап
                                            </button>
                                            @endcan
                                            <button type="button" @click="showCard()"
                                                    class="w-full text-left px-3 py-3 hover:bg-page">
                                                Открыть карточку
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </section>
                @endforeach
            </div>
        </div>
        @endforeach
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

    @include('partials.task-modal')

    {{-- Модалка: новый проект (manager) --}}
    @can('create', App\Models\Project::class)
    <div x-show="project.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-0 md:p-4">
        <div class="absolute inset-0 bg-black/40" @click="project.open = false"></div>
        <div class="relative bg-white border-0 md:border border-line rounded-none md:rounded-xl shadow-lg w-full md:max-w-lg p-4 md:p-6 h-full md:h-auto md:max-h-[90vh] overflow-y-auto">
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

@include('partials.task-scripts')

<script>
function board() {
    return Object.assign({
        pop: { ask: false, taskId: null, stageId: null, stageName: '', x: 0, y: 0 },
        project: {
            open: false,
            form: { title: '', start_date: '', due_date: '', responsible: {} },
        },
        // Экран проекта на мобильных (оверлей из данных страницы, без запроса)
        proj: { open: false, id: null },
        openProject(id) {
            this.proj = { open: true, id: id };
        },
        closeProject() {
            this.proj = { open: false, id: null };
        },
        init() {
            window.addEventListener('ask-complete', (e) => {
                this.pop = {
                    ask: true,
                    taskId: e.detail.taskId,
                    stageId: e.detail.stageId,
                    stageName: e.detail.stageName,
                    x: e.detail.x,
                    y: e.detail.y,
                };
            });
            this.initTaskModal();
        },
        openCreate() {
            this.project.form = { title: '', start_date: '', due_date: '', responsible: {} };
            this.project.open = true;
        },
        finishPop() {
            if (!this.pop.taskId) return;
            window.completeStage(this.pop.taskId, this.pop.stageId);
            this.keep();
        },
        keep() {
            this.pop.ask = false;
        },
    }, taskModalMethods());
}

function cardMenu() {
    return {
        open: false,
        moveTo(stageId) {
            const card = this.$el.closest('.bccard');
            const cell = this.$el.closest('.kanban-cell');
            const pos = popoverPos(card);
            window.moveTask(card.dataset.taskId, stageId, {
                fromStageId: card.dataset.stageId,
                fromStageName: cell.dataset.stageName,
                showPopover: true,
                x: pos.x,
                y: pos.y,
            });
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

    // Позиция поповера у карточки с ограничением по вьюпорту (не уезжает за экран)
    function popoverPos(anchor) {
        const r = anchor.getBoundingClientRect();
        const w = 288, h = 150, m = 8;
        const x = Math.min(Math.max(r.left + r.width / 2, w / 2 + m), window.innerWidth - w / 2 - m);
        const y = Math.min(Math.max(r.bottom + m, m), window.innerHeight - h - m);
        return { x, y };
    }

    function initBoard() {
        // На мобильных (<768) таблица скрыта, drag отключён — перенос через меню ⋮
        if (window.innerWidth < 768) return;
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
                        // Запоминаем место карточки, чтобы вернуть её точно туда же
                        nextSibling: evt.item.nextElementSibling,
                    };
                },
                onAdd(evt) {
                    // DOM не двигаем — только данные. Возвращаем карточку на исходную позицию
                    // (сортировка внутри ячейки фиксирована, порядок карточек не меняется).
                    const from = evt.from;
                    if (dragState && dragState.nextSibling && from.contains(dragState.nextSibling)) {
                        from.insertBefore(evt.item, dragState.nextSibling);
                    } else {
                        from.appendChild(evt.item);
                    }
                    if (!dragState) return;
                    const toCell = evt.to.closest('.kanban-cell');
                    const toStageId = toCell?.dataset.stageId;
                    if (!toStageId || toStageId === dragState.fromStageId) return;

                    // Поповер показываем у исходной карточки (которую покидаем)
                    const pos = popoverPos(evt.item);
                    window.moveTask(dragState.taskId, toStageId, {
                        fromStageId: dragState.fromStageId,
                        fromStageName: dragState.fromStageName,
                        showPopover: true,
                        x: pos.x,
                        y: pos.y,
                    });
                    dragState = null;
                },
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initBoard);
</script>
@endsection
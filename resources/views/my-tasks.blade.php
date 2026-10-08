@extends('layouts.app')

@section('title', 'Мои задачи · Трекер')
@section('container', 'max-w-5xl')

@section('content')
    @php
        $hasFilters = $filters['project'] > 0 || $filters['priority'] !== '' || $filters['overdue'];
    @endphp

    <div x-data="myTasks()">
        <div class="flex items-center justify-between mb-4 gap-3">
            <h1 class="text-[18px] font-semibold">Мои задачи</h1>
            <span class="text-[13px] text-muted whitespace-nowrap">
                Найдено: {{ $tasks->count() }}
                @if ($overdueCount > 0)
                    · <span class="text-danger font-medium">просрочено: {{ $overdueCount }}</span>
                @endif
            </span>
        </div>

        {{-- Фильтры: серверные, через GET-параметры --}}
        <form method="GET" action="{{ route('my-tasks.index') }}"
              class="bg-white border border-line rounded-xl p-3 mb-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-[11px] text-muted font-medium">Проект</span>
                <select name="project" onchange="this.form.submit()"
                        class="rounded-lg border border-line px-2.5 py-1.5 text-[13px] min-h-[44px] md:min-h-0 focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    <option value="0">Все проекты</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($filters['project'] === $project->id)>{{ $project->title }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-[11px] text-muted font-medium">Приоритет</span>
                <select name="priority" onchange="this.form.submit()"
                        class="rounded-lg border border-line px-2.5 py-1.5 text-[13px] min-h-[44px] md:min-h-0 focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    <option value="">Любой</option>
                    <option value="high" @selected($filters['priority'] === 'high')>Высокий</option>
                    <option value="normal" @selected($filters['priority'] === 'normal')>Обычный</option>
                    <option value="low" @selected($filters['priority'] === 'low')>Низкий</option>
                </select>
            </label>

            <label class="flex items-center gap-2 min-h-[44px] md:min-h-0 text-[13px] cursor-pointer">
                <input type="checkbox" name="overdue" value="1" @checked($filters['overdue'])
                       onchange="this.form.submit()"
                       class="w-4 h-4 rounded border-line text-accent focus:ring-accent/30">
                Только просроченные
            </label>

            <noscript>
                <button type="submit"
                        class="rounded-lg bg-accent hover:bg-accent-hover text-white text-[13px] font-medium px-3 py-2 transition-colors">
                    Применить
                </button>
            </noscript>

            @if ($hasFilters)
                <a href="{{ route('my-tasks.index') }}"
                   class="text-[13px] font-medium text-accent hover:underline ml-auto self-center">Сбросить</a>
            @endif
        </form>

        @if ($tasks->isEmpty())
            <div class="bg-white border border-dashed border-line rounded-xl px-6 py-16 text-center">
                <p class="text-[14px] font-medium">Задач нет</p>
                <p class="text-[13px] text-muted mt-1">
                    @if ($hasFilters)
                        По выбранным фильтрам ничего не найдено.
                        <a href="{{ route('my-tasks.index') }}" class="text-accent hover:underline">Сбросить фильтры</a>
                    @else
                        Вы пока не назначены ответственным ни по одной задаче.
                    @endif
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach ($tasks as $task)
                    @php
                        $done = $task->stageStatuses->where('status', 'done')->count();
                        $totalStages = $task->stageStatuses->count();
                        $percent = $totalStages ? (int) round($done / $totalStages * 100) : 0;
                        $itemsTotal = $task->items_count ?? 0;
                        $itemsDone = $task->items_done_count ?? 0;
                        $daysLeft = $task->due_date
                            ? (int) now()->startOfDay()->diffInDays($task->due_date->copy()->startOfDay(), false)
                            : null;
                    @endphp
                    <div class="my-task-card bg-white border border-line rounded-xl p-3 cursor-pointer hover:border-accent/50 transition-colors"
                         data-task-id="{{ $task->id }}"
                         @click="openTask({{ $task->id }})">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-[14px] font-semibold truncate">{{ $task->taskType->name }}</div>
                                <div class="text-[12px] text-muted truncate">{{ $task->project->title }}</div>
                            </div>
                            <div class="shrink-0">
                                <span data-priority-badge
                                      class="rounded font-medium px-2 py-0.5 text-[10px] whitespace-nowrap {{ $task->priority === 'normal' ? 'hidden' : ($task->priority === 'high' ? 'bg-red-50 text-danger' : 'bg-slate-100 text-muted') }}">{{ $task->priority === 'high' ? 'Высокий' : 'Низкий' }}</span>
                            </div>
                        </div>

                        <span data-due-badge
                              class="inline-block mt-2 rounded px-2 py-0.5 text-[11px] font-medium {{ !$task->due_date ? 'hidden' : ($daysLeft < 0 ? 'bg-red-50 text-danger' : ($daysLeft <= 3 ? 'bg-amber-50 text-warn' : 'bg-slate-100 text-muted')) }}">@if ($task->due_date)Дедлайн {{ $task->due_date->format('d.m.Y') }}@if ($daysLeft < 0) · просрочено на {{ -$daysLeft }} дн.@elseif ($daysLeft === 0) · сегодня@elseif ($daysLeft <= 3) · осталось {{ $daysLeft }} дн.@endif @endif</span>

                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[11px] text-muted mb-1">
                                <span>Этапы</span>
                                <span data-stage-progress>{{ $done }}/{{ $totalStages }}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-line overflow-hidden">
                                <div data-stage-bar class="h-full bg-accent transition-[width]" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>

                        <div class="mt-2 flex items-center justify-between text-[11px] text-muted">
                            <span data-checklist class="{{ $itemsTotal > 0 ? '' : 'hidden' }}">☑ {{ $itemsDone }}/{{ $itemsTotal }}</span>
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent/10 text-accent text-[10px] font-bold"
                                  title="Ответственный: {{ auth()->user()->name }}">{{ auth()->user()->initials }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @include('partials.task-modal')
    </div>

    @include('partials.task-scripts')

    <script>
        function myTasks() {
            return Object.assign({
                init() {
                    this.initTaskModal();
                },
            }, taskModalMethods());
        }
    </script>
@endsection

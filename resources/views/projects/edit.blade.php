@extends('layouts.app')

@section('title', 'Проект: '.$project->title)

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-[20px] font-semibold">{{ $project->title }}</h1>
            <p class="text-[13px] text-muted mt-0.5">Редактирование проекта</p>
        </div>
        <a href="{{ route('home') }}" class="text-[13px] text-accent hover:underline font-medium">← К проектам</a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-danger/40 text-danger px-4 py-3 text-[13px] space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-white border border-line rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="title" class="block text-[13px] font-medium mb-1">Название</label>
                <input id="title" name="title" type="text" required maxlength="100"
                       value="{{ old('title', $project->title) }}"
                       class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-[13px] font-medium mb-1">Дата начала</label>
                    <input id="start_date" name="start_date" type="date" required
                           value="{{ old('start_date', $project->start_date->format('Y-m-d')) }}"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>
                <div>
                    <label for="due_date" class="block text-[13px] font-medium mb-1">Желаемая дата завершения</label>
                    <input id="due_date" name="due_date" type="date" required min="{{ $project->start_date->format('Y-m-d') }}"
                           value="{{ old('due_date', $project->due_date->format('Y-m-d')) }}"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>
            </div>

            <div>
                <label for="status" class="block text-[13px] font-medium mb-1">Статус</label>
                <select id="status" name="status"
                        class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    @foreach (['active' => 'Активен', 'paused' => 'Приостановлен', 'completed' => 'Завершён', 'cancelled' => 'Отменён'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="border-t border-line pt-4 space-y-3">
                {{-- Ответственные по 4 задачам --}}
                @foreach ($taskTypes as $taskType)
                <div>
                    <label class="block text-[13px] font-medium mb-1">Ответственный: {{ $taskType->name }}</label>
                    <select name="responsible[{{ $taskType->id }}]"
                            class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                        @foreach ($activeUsers as $user)
                            <option value="{{ $user->id }}"
                                @selected((string) old('responsible.'.$taskType->id, $project->projectTasks->firstWhere('task_type_id', $taskType->id)->responsible_id ?? null) === (string) $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('home') }}"
                   class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">Отмена</a>
                <button type="submit"
                        class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-5 py-2 transition-colors">
                    Сохранить
                </button>
            </div>
        </form>
    </div>

    {{-- Архив --}}
    <div class="mt-4 bg-white border border-line rounded-xl shadow-sm p-4 flex items-center justify-between">
        <span class="text-[13px] text-muted">
            {{ $project->is_archived ? 'Проект в архиве и скрыт со списков' : 'Проект в работе' }}
        </span>
        @if ($project->is_archived)
            <form method="POST" action="{{ route('projects.restore', $project) }}">
                @csrf
                <button type="submit"
                        class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">
                    Восстановить
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('projects.archive', $project) }}">
                @csrf
                <button type="submit"
                        class="rounded-lg border border-danger/40 text-danger px-4 py-2 text-sm font-medium hover:bg-red-50">
                    В архив
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
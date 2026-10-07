@extends('layouts.app')

@section('title', 'Проекты')

@section('content')
<div x-data="projectPage()" class="relative">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-[24px] font-semibold">Проекты</h1>

        {{-- Кнопка «Новый проект» видна только руководителю --}}
        @auth
        @if (auth()->user()->role->value === 'manager')
            <button type="button" @click="openCreate()"
                    class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 transition-colors">
                Новый проект
            </button>
        @endif
        @endauth
    </div>

    {{-- Ошибки валидации после отправки формы --}}
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-danger/40 text-danger px-4 py-3 text-[13px] space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-white border border-line rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead>
                <tr class="text-left text-muted border-b border-line">
                    <th class="px-4 py-3 font-medium">Название</th>
                    <th class="px-4 py-3 font-medium">Период</th>
                    <th class="px-4 py-3 font-medium">Статус</th>
                    <th class="px-4 py-3 font-medium text-right">Управление</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                <tr class="border-b border-line last:border-0 hover:bg-page/60">
                    <td class="px-4 py-3 font-medium">{{ $project->title }}</td>
                    <td class="px-4 py-3 text-muted">
                        {{ $project->start_date->format('d.m.Y') }} — {{ $project->due_date->format('d.m.Y') }}
                    </td>
                    <td class="px-4 py-3">
                        @switch($project->status)
                            @case('active')
                                <span class="rounded bg-accent/10 text-accent font-medium px-2 py-0.5">Активен</span>
                                @break
                            @case('paused')
                                <span class="rounded bg-amber-50 text-warn font-medium px-2 py-0.5">Приостановлен</span>
                                @break
                            @case('completed')
                                <span class="rounded bg-green-50 text-done font-medium px-2 py-0.5">Завершён</span>
                                @break
                            @case('cancelled')
                                <span class="rounded bg-red-50 text-danger font-medium px-2 py-0.5">Отменён</span>
                                @break
                        @endswitch
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if (auth()->user()->role->value === 'manager')
                            <a href="{{ route('projects.edit', $project) }}"
                               class="text-accent hover:underline font-medium">Редактировать</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-muted">
                        Нет активных проектов.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Модалка: новый проект --}}
    <div x-show="show" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="show = false"></div>
        <div class="relative bg-white border border-line rounded-xl shadow-lg w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
            <h2 class="text-[16px] font-semibold mb-4">Новый проект</h2>

            <form method="POST" action="{{ route('projects.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-[13px] font-medium mb-1">Название</label>
                    <input name="title" type="text" required maxlength="100" x-model="form.title"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium mb-1">Дата начала</label>
                        <input name="start_date" type="date" required x-model="form.start_date"
                               class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium mb-1">Желаемая дата завершения</label>
                        <input name="due_date" type="date" required :min="form.start_date" x-model="form.due_date"
                               class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    </div>
                </div>

                <div class="border-t border-line pt-4 space-y-3">
                    {{-- Ответственный по каждой из 4 задач --}}
                    @foreach ($taskTypes as $taskType)
                    <div>
                        <label class="block text-[13px] font-medium mb-1">Ответственный: {{ $taskType->name }}</label>
                        <select name="responsible[{{ $taskType->id }}]" x-model="form.responsible[{{ $taskType->id }}]"
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
                    <button type="button" @click="show = false"
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
</div>

<script>
    function projectPage() {
        return {
            show: false,
            form: {
                title: '',
                start_date: '',
                due_date: '',
                responsible: {},
            },
            openCreate() {
                this.form = { title: '', start_date: '', due_date: '', responsible: {} };
                this.show = true;
            },
        };
    }
</script>
@endsection
@extends('layouts.app')

@section('title', 'Пользователи')

@section('content')
<div x-data="usersPage()" class="relative">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-[24px] font-semibold">Пользователи</h1>
        <button type="button" @click="openCreate()"
                class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 transition-colors">
            Добавить
        </button>
    </div>

    {{-- Ошибки валидации (после отправки формы модалка закрыта, ошибки видны здесь) --}}
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
                    <th class="px-4 py-3 font-medium">Имя</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Роль</th>
                    <th class="px-4 py-3 font-medium">Статус</th>
                    <th class="px-4 py-3 font-medium">Создан</th>
                    <th class="px-4 py-3 font-medium text-right">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                <tr class="border-b border-line last:border-0 hover:bg-page/60">
                    <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-muted">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        @if ($user->role->value === 'manager')
                            <span class="rounded bg-accent/10 text-accent font-medium px-2 py-0.5">Руководитель</span>
                        @else
                            <span class="rounded bg-page text-muted px-2 py-0.5">Сотрудник</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($user->is_active)
                            <span class="rounded bg-green-50 text-done font-medium px-2 py-0.5">Активен</span>
                        @else
                            <span class="rounded bg-slate-200 text-muted font-medium px-2 py-0.5">Деактивирован</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-muted">{{ $user->created_at->format('d.m.Y') }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <button type="button"
                                @click="openEdit({{ $user->id }}, @json($user->name), @json($user->email), @json($user->role->value))"
                                class="text-accent hover:underline font-medium">
                            Редактировать
                        </button>
                        <button type="button"
                                @click="openReset({{ $user->id }}, @json($user->name))"
                                class="text-accent hover:underline font-medium">
                            Сбросить пароль
                        </button>
                        <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="inline">
                            @csrf
                            <button type="submit" class="font-medium hover:underline
                                    {{ $user->is_active ? 'text-danger' : 'text-done' }}">
                                {{ $user->is_active ? 'Деактивировать' : 'Активировать' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-muted">
                        Пользователей пока нет — нажмите «Добавить».
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Модалка: создание / редактирование пользователя --}}
    <div x-show="show" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="show = false"></div>
        <div class="relative bg-white border border-line rounded-xl shadow-lg w-full max-w-md p-6">
            <h2 class="text-[16px] font-semibold mb-4" x-text="mode === 'create' ? 'Новый пользователь' : 'Редактирование'"></h2>

            <form :action="action" method="POST" class="space-y-4">
                @csrf
                <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PATCH"></template>

                <div>
                    <label class="block text-[13px] font-medium mb-1">Имя</label>
                    <input name="name" type="text" required maxlength="100" x-model="form.name"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>

                <div>
                    <label class="block text-[13px] font-medium mb-1">Email</label>
                    <input name="email" type="email" required x-model="form.email"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>

                <div>
                    <label class="block text-[13px] font-medium mb-1">Роль</label>
                    <select name="role" x-model="form.role"
                            class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                        <option value="employee">Сотрудник</option>
                        <option value="manager">Руководитель</option>
                    </select>
                </div>

                <div x-show="mode === 'create'">
                    <label class="block text-[13px] font-medium mb-1">Пароль</label>
                    <input name="password" type="password" required minlength="8" x-model="form.password"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    <p class="mt-1 text-[13px] text-muted">Минимум 8 символов</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="show = false"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">
                        Отмена
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 transition-colors">
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Модалка: сброс пароля --}}
    <div x-show="resetShow" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="resetShow = false"></div>
        <div class="relative bg-white border border-line rounded-xl shadow-lg w-full max-w-sm p-6">
            <h2 class="text-[16px] font-semibold mb-1">Сброс пароля</h2>
            <p class="text-[13px] text-muted mb-4" x-text="resetName"></p>

            <form :action="'{{ route('users.index') }}/' + resetId + '/reset-password'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[13px] font-medium mb-1">Новый пароль</label>
                    <input name="password" type="password" required minlength="8" x-model="resetPassword"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                    <p class="mt-1 text-[13px] text-muted">Минимум 8 символов</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="resetShow = false"
                            class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-page">
                        Отмена
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-2 transition-colors">
                        Сбросить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function usersPage() {
        return {
            show: false,
            mode: 'create',
            action: '',
            form: { name: '', email: '', role: 'employee', password: '' },
            resetShow: false,
            resetId: null,
            resetName: '',
            resetPassword: '',

            openCreate() {
                this.mode = 'create';
                this.action = '{{ route('users.store') }}';
                this.form = { name: '', email: '', role: 'employee', password: '' };
                this.show = true;
            },

            openEdit(id, name, email, role) {
                this.mode = 'edit';
                this.action = '{{ route('users.index') }}/' + id;
                this.form = { name: name, email: email, role: role, password: '' };
                this.show = true;
            },

            openReset(id, name) {
                this.resetId = id;
                this.resetName = name;
                this.resetPassword = '';
                this.resetShow = true;
            },
        };
    }
</script>
@endsection

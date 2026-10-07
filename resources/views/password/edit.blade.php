@extends('layouts.app')

@section('title', 'Смена пароля')

@section('content')
    <div class="max-w-md">
        <h1 class="text-[24px] font-semibold mb-4">Смена пароля</h1>

        <div class="bg-white border border-line rounded-xl shadow-sm p-6">
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-[13px] font-medium mb-1">Текущий пароль</label>
                    <input id="current_password" name="current_password" type="password" required
                           class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent
                                  @error('current_password') border-danger @else border-line @enderror">
                    @error('current_password')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-[13px] font-medium mb-1">Новый пароль</label>
                    <input id="password" name="password" type="password" required
                           class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent
                                  @error('password') border-danger @else border-line @enderror">
                    <p class="mt-1 text-[13px] text-muted">Минимум 8 символов</p>
                    @error('password')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-[13px] font-medium mb-1">Повторите новый пароль</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                </div>

                <button type="submit"
                        class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-5 py-2.5 transition-colors">
                    Сохранить
                </button>
            </form>
        </div>
    </div>
@endsection

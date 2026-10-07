@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <div class="min-h-[75vh] flex items-center justify-center">
        <div class="w-full max-w-sm bg-white border border-line rounded-xl shadow-sm p-6">
            <h1 class="text-[20px] font-semibold">Вход</h1>
            <p class="text-[13px] text-muted mt-1 mb-5">Войдите, чтобы открыть доску</p>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-[13px] font-medium mb-1">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent
                                  @error('email') border-danger @else border-line @enderror">
                    @error('email')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-[13px] font-medium mb-1">Пароль</label>
                    <input id="password" name="password" type="password" required
                           class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent
                                  @error('password') border-danger @else border-line @enderror">
                    @error('password')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Ошибки под формой: неверные данные и деактивированный аккаунт --}}
                @error('credentials')
                    <p class="rounded-lg bg-red-50 border border-danger/40 text-danger px-3 py-2 text-[13px]">{{ $message }}</p>
                @enderror
                @error('status')
                    <p class="rounded-lg bg-red-50 border border-danger/40 text-danger px-3 py-2 text-[13px] font-medium">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium py-2.5 transition-colors">
                    Войти
                </button>
            </form>
        </div>
    </div>
@endsection

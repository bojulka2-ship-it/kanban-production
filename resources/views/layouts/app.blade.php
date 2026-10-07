<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Производство · Трекер')</title>

    <!-- Frontend через CDN, сборка не используется (zero-build) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Цвета и шрифт из брендбука
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                    colors: {
                        accent: '#2563EB',
                        accentHover: '#1D4ED8',
                        page: '#F1F5F9',
                        ink: '#0F172A',
                        muted: '#64748B',
                        line: '#E2E8F0',
                        done: '#16A34A',
                        warn: '#F59E0B',
                        danger: '#DC2626',
                        waiting: '#CBD5E1',
                    },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-page font-sans text-[14px] text-ink">
    <header class="bg-white border-b border-line">
        <div class="mx-auto max-w-7xl px-4 h-14 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-bold text-[16px] tracking-wide">
                ПРОИЗВОДСТВО · Трекер
            </a>

            @auth
            <!-- Меню пользователя: Alpine-dropdown -->
            <div x-data="{ open: false }" class="relative">
                <button type="button"
                        @click="open = !open"
                        @click.outside="open = false"
                        class="flex items-center gap-2 text-sm font-medium hover:text-accent">
                    {{ auth()->user()->name }}
                    <svg class="w-4 h-4 text-muted" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <div x-show="open" x-cloak
                     class="absolute right-0 mt-2 w-52 bg-white border border-line rounded-lg shadow-lg py-1 z-30">
                    <a href="{{ route('password.edit') }}"
                       class="block px-4 py-2 text-sm hover:bg-page"
                       @click="open = false">Сменить пароль</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-left px-4 py-2 text-sm text-danger hover:bg-page">
                            Выйти
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-done/40 text-done px-4 py-3 text-[13px] font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 border border-danger/40 text-danger px-4 py-3 text-[13px] font-medium">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>

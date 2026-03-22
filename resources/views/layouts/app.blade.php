<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Accounting Portal') }} — @yield('title', 'Dashboard')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

    {{-- Navigation --}}
    <nav class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-14">
            <a href="{{ route('expense-periods.index') }}">
                <img src="{{ asset('images/logo.png') }}" alt="Jamelle 1122 Corporation" class="h-8">
            </a>
            <div class="flex items-center gap-6 text-sm font-medium text-gray-600">
                <a href="{{ route('expense-periods.index') }}" class="hover:text-indigo-600">Periods</a>
                <a href="{{ route('reports.consolidated') }}" class="hover:text-indigo-600">Consolidated</a>
                @can('manage users')
                    <a href="{{ route('users.index') }}" class="hover:text-indigo-600">Users</a>
                @endcan
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="hover:text-red-600">Logout</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="max-w-screen-2xl mx-auto px-4 mt-4">
            <div class="bg-green-50 border border-green-300 text-green-800 rounded px-4 py-3 text-sm flex justify-between">
                {{ session('success') }}
                <button @click="show = false" class="ml-4 font-bold">&times;</button>
            </div>
        </div>
    @endif

    {{-- Page Content --}}
    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>

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
    <nav class="bg-white border-b border-gray-200 shadow-sm" x-data="{ navOpen: false }">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <a href="{{ route('dashboard') }}">
                    <img src="{{ asset('images/logo_rectangle.png') }}" alt="Jamelle 1122 Corporation" class="h-12">
                </a>

                {{-- Desktop nav --}}
                <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600">
                    <a href="{{ route('dashboard') }}" class="hover:text-indigo-600">Dashboard</a>
                    <a href="{{ route('analytics') }}" class="hover:text-indigo-600">Analytics</a>
                    <a href="{{ route('expense-periods.index') }}" class="hover:text-indigo-600">Periods</a>
                    <a href="{{ route('passbooks.index') }}" class="hover:text-indigo-600">Passbooks</a>
                    <a href="{{ route('paymaya.index') }}" class="hover:text-indigo-600">PayMaya</a>
                    <a href="{{ route('deposit-slips.index') }}" class="hover:text-indigo-600">Deposit Slips</a>
                    <a href="{{ route('reports.consolidated') }}" class="hover:text-indigo-600">Consolidated</a>
                    <a href="{{ route('reports.branch-summary') }}" class="hover:text-indigo-600">Branch Summary</a>
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

                {{-- Mobile hamburger --}}
                <button type="button" @click="navOpen = !navOpen"
                        class="md:hidden p-2 rounded text-gray-500 hover:text-gray-700 hover:bg-gray-100">
                    <svg x-show="!navOpen" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="navOpen" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mobile menu --}}
            <div x-show="navOpen" x-cloak @click.outside="navOpen = false"
                 class="md:hidden border-t border-gray-100 py-2 space-y-0.5">
                <a href="{{ route('dashboard') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Dashboard</a>
                <a href="{{ route('analytics') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Analytics</a>
                <a href="{{ route('expense-periods.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Periods</a>
                <a href="{{ route('passbooks.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Passbooks</a>
                <a href="{{ route('paymaya.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">PayMaya</a>
                <a href="{{ route('deposit-slips.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Deposit Slips</a>
                <a href="{{ route('reports.consolidated') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Consolidated</a>
                <a href="{{ route('reports.branch-summary') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Branch Summary</a>
                @can('manage users')
                    <a href="{{ route('users.index') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-indigo-50 hover:text-indigo-600">Users</a>
                @endcan
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full text-left px-3 py-2 text-sm font-medium text-gray-700 rounded hover:bg-red-50 hover:text-red-600">Logout</button>
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

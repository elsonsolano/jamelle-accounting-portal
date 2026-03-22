<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Accounting Portal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-indigo-700">Accounting Portal</h1>
            <p class="text-sm text-gray-500 mt-1">Sign in to continue</p>
        </div>

        <div class="bg-white rounded-lg shadow border border-gray-100 p-8">
            <form method="POST" action="/login" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500
                                  @error('email') border-red-400 @enderror">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="rounded text-indigo-600">
                    <label for="remember" class="text-sm text-gray-600">Remember me</label>
                </div>

                <button type="submit"
                        class="w-full bg-indigo-600 text-white font-medium text-sm py-2.5 rounded hover:bg-indigo-700">
                    Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>

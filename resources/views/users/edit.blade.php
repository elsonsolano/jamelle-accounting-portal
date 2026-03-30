@extends('layouts.app')
@section('title', 'Edit User')

@section('content')

<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Edit User</h1>
    <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:underline">&larr; All Users</a>
</div>

<div class="max-w-lg bg-white rounded shadow border border-gray-100 p-6">
    <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
        @csrf
        @method('PUT')

        {{-- Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('name') border-red-400 @enderror">
            @error('name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('email') border-red-400 @enderror">
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Role --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select name="role" required
                    class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                           @error('role') border-red-400 @enderror">
                <option value="">Select a role…</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}"
                        {{ old('role', $user->roles->first()?->name) === $role->name ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
            @error('role')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- New Password (optional) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                New Password <span class="text-gray-400 font-normal">(leave blank to keep current)</span>
            </label>
            <input type="password" name="password"
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('password') border-red-400 @enderror">
            @error('password')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm New Password --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
            <input type="password" name="password_confirmation"
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400">
        </div>

        <div class="pt-2 flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded hover:bg-indigo-700">
                Save Changes
            </button>
            <a href="{{ route('users.index') }}"
               class="text-sm text-gray-500 px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection

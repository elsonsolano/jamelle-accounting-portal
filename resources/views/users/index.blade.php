@extends('layouts.app')
@section('title', 'Users')

@section('content')

<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Users</h1>
    <a href="{{ route('users.create') }}"
       class="text-sm bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
        + New User
    </a>
</div>

<div class="bg-white rounded shadow border border-gray-100 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Email</th>
                <th class="px-4 py-3 text-left">Role</th>
                <th class="px-4 py-3 text-left">Created</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        @foreach($user->roles as $role)
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                                {{ $role->name === 'Superadmin' ? 'bg-purple-100 text-purple-700' : '' }}
                                {{ $role->name === 'Admin'      ? 'bg-indigo-100 text-indigo-700' : '' }}
                                {{ $role->name === 'Accountant' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $role->name === 'Viewer'     ? 'bg-gray-100 text-gray-600' : '' }}">
                                {{ $role->name }}
                            </span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs">
                        {{ $user->created_at->format('M d, Y') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection

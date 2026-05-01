@extends('layouts.app')

@section('page-title', 'cPanel Accounts')

@section('content')

@php
    use Illuminate\Pagination\LengthAwarePaginator;

    $perPage = 20;
    $currentPage = request()->get('page', 1);
    $accountsCollection = collect($accounts);

    $paginatedAccounts = new LengthAwarePaginator(
        $accountsCollection->forPage($currentPage, $perPage),
        $accountsCollection->count(),
        $perPage,
        $currentPage,
        [
            'path' => request()->url(),
            'query' => request()->query(),
        ]
    );
@endphp

<div class="space-y-6">

    <div class="bg-white rounded-2xl shadow p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">cPanel Accounts</h2>
            <p class="text-slate-500">{{ $server->name }} - {{ $server->host }}</p>
            <p class="text-sm text-slate-400 mt-1">
                Showing {{ $paginatedAccounts->firstItem() ?? 0 }} to {{ $paginatedAccounts->lastItem() ?? 0 }}
                of {{ $paginatedAccounts->total() }} accounts
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
            <a href="{{ route('servers.show', $server) }}"
               class="w-full sm:w-auto text-center px-5 py-3 rounded-xl bg-slate-200 text-slate-800 hover:bg-slate-300">
                Back
            </a>

            <a href="{{ route('servers.cpanel.create', $server) }}"
               class="w-full sm:w-auto text-center px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                + Create Account
            </a>
        </div>
    </div>

    @if($error)
        <div class="bg-red-100 text-red-700 border border-red-300 rounded-xl p-4">
            {{ $error }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4">Domain</th>
                        <th class="p-4">Username</th>
                        <th class="p-4">IP</th>
                        <th class="p-4">Package</th>
                        <th class="p-4">Email</th>
                        <th class="p-4">Disk Used</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($paginatedAccounts as $account)
                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4 font-semibold">{{ $account['domain'] ?? '-' }}</td>
                            <td class="p-4">{{ $account['user'] ?? '-' }}</td>
                            <td class="p-4">{{ $account['ip'] ?? '-' }}</td>
                            <td class="p-4">{{ $account['plan'] ?? '-' }}</td>
                            <td class="p-4">{{ $account['email'] ?? '-' }}</td>
                            <td class="p-4">{{ $account['diskused'] ?? '-' }}</td>
                            <td class="p-4 text-right">
                                @if(!empty($account['user']))
                                    <a href="{{ route('servers.cpanel.edit', [$server, $account['user']]) }}"
                                       class="inline-block px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-700">
                                        Manage
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">
                                No cPanel accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginatedAccounts->hasPages())
            <div class="p-5 border-t bg-slate-50">
                {{ $paginatedAccounts->links() }}
            </div>
        @endif
    </div>

</div>

@endsection
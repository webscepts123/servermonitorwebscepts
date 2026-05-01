@extends('layouts.app')

@section('page-title', 'Firewall')

@section('content')

<div class="space-y-6">

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-2xl font-bold">Firewall Manager</h2>
        <p class="text-slate-500">CSF / Ports / IP Blocking</p>
    </div>

    <div class="grid md:grid-cols-3 gap-4">

        <div class="bg-white p-5 rounded-xl shadow">
            <h4 class="font-bold">Firewall Status</h4>
            <p class="mt-2 text-green-600 font-bold">{{ $firewallStatus ?? 'ACTIVE' }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow">
            <h4 class="font-bold">Open Ports</h4>
            <p class="mt-2 text-sm">{{ $ports ?? '22, 80, 443, 2083' }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow">
            <h4 class="font-bold">Blocked IPs</h4>
            <p class="mt-2">{{ $blockedCount ?? 0 }}</p>
        </div>

    </div>

    <div class="bg-white p-5 rounded-xl shadow">
        <h4 class="font-bold mb-3">Block IP</h4>

        <form method="POST" action="{{ route('security.block.ip') }}">
            @csrf
            <input type="text" name="ip" class="border p-3 rounded w-full" placeholder="Enter IP">
            <button class="mt-3 px-4 py-2 bg-red-600 text-white rounded">Block</button>
        </form>
    </div>

</div>

@endsection
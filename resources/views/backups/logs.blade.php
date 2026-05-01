@extends('layouts.app')

@section('content')

<div class="bg-white rounded-2xl shadow p-6">
    <h2 class="text-2xl font-bold text-slate-800">Backup Logs</h2>
    <p class="text-slate-500 mt-1">View backup, transfer and sync history</p>
</div>

@foreach($servers as $server)
    <div class="bg-white rounded-2xl shadow p-6 mt-5">
        <h3 class="text-lg font-bold">{{ $server->name }}</h3>

        <div class="mt-3 text-sm text-slate-600">
            <p>Last Check: {{ optional($server->checks->first())->checked_at }}</p>
            <p>Alerts: {{ $server->securityAlerts->count() }}</p>
        </div>
    </div>
@endforeach

@endsection
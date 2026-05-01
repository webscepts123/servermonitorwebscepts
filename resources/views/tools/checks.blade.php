@extends('layouts.app')

@section('page-title', 'Run Checks Logs')

@section('content')

<div class="bg-white p-6 rounded-2xl shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b">
                <th>Server</th>
                <th>Status</th>
                <th>CPU</th>
                <th>RAM</th>
                <th>Disk</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($checks as $check)
                <tr class="border-b">
                    <td>{{ $check->server->name ?? '-' }}</td>
                    <td>{{ $check->status }}</td>
                    <td>{{ $check->cpu_usage }}%</td>
                    <td>{{ $check->ram_usage }}%</td>
                    <td>{{ $check->disk_usage }}%</td>
                    <td>{{ $check->checked_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
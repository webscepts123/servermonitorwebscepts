@extends('layouts.app')

@section('page-title', 'Security Alerts')

@section('content')

<div class="bg-white p-6 rounded-2xl shadow">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b">
                <th>Server</th>
                <th>Type</th>
                <th>Level</th>
                <th>Message</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($alerts as $alert)
                <tr class="border-b">
                    <td>{{ $alert->server->name ?? '-' }}</td>
                    <td>{{ $alert->type }}</td>
                    <td>{{ $alert->level }}</td>
                    <td>{{ $alert->title }}</td>
                    <td>{{ $alert->detected_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $alerts->links() }}
</div>

@endsection
@extends('layouts.app')

@section('page-title', 'Website / Ports')

@section('content')
<div class="bg-white rounded-2xl p-6 shadow">
    <h3 class="text-lg font-semibold mb-4">Port Monitoring</h3>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left border-b">
                <th>Server</th>
                <th>Port</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b">
                <td>Server 1</td>
                <td>80</td>
                <td class="text-green-600">Open</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
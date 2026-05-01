@extends('layouts.app')

@section('page-title', 'Domains (CloudDNS)')

@section('content')

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

@foreach($servers as $server)
    <div class="bg-white p-5 rounded-2xl shadow">
        <h3 class="font-bold text-lg">{{ $server->name }}</h3>
        <p class="text-sm text-gray-500">{{ $server->host }}</p>

        <div class="mt-3">
            <p class="text-xs text-gray-400">Linked Domain</p>
            <p class="font-semibold">{{ $server->domain ?? 'Not linked' }}</p>
        </div>

        <div class="mt-4 flex gap-2">
            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs">
                Active
            </span>
        </div>
    </div>
@endforeach

</div>

@endsection
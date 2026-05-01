@extends('layouts.app')

@section('page-title', 'Uptime Status')

@section('content')
<div class="bg-white rounded-2xl p-6 shadow">
    <h3 class="text-lg font-semibold mb-4">Server Uptime</h3>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-green-100 text-green-800">
            Server 1 - Online ✅
        </div>
        <div class="p-4 rounded-xl bg-red-100 text-red-800">
            Server 2 - Offline ❌
        </div>
    </div>
</div>
@endsection
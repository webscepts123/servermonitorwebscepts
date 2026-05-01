@extends('layouts.app')

@section('page-title', 'SSH Security')

@section('content')

<div class="space-y-6">

    <div class="bg-white p-6 rounded-2xl shadow">
        <h2 class="text-xl font-bold">SSH Login Activity</h2>
    </div>

    <div class="bg-white p-5 rounded-xl shadow">
        <h4 class="font-bold mb-3">Recent Logins</h4>

        @foreach($logins ?? [] as $login)
            <div class="border-b py-2 flex justify-between">
                <span>{{ $login['ip'] }}</span>
                <span>{{ $login['time'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="bg-white p-5 rounded-xl shadow">
        <h4 class="font-bold">Failed Attempts</h4>

        <p class="text-red-600 text-2xl mt-2">{{ $failed ?? 0 }}</p>
    </div>

</div>

@endsection
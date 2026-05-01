@extends('layouts.app')

@section('page-title', 'Email Security')

@section('content')

<div class="space-y-6">

    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-bold">Email Security</h2>
    </div>

    <div class="grid md:grid-cols-3 gap-4">

        <div class="bg-white p-5 rounded-xl shadow">
            <h4>Mail Queue</h4>
            <p class="text-2xl font-bold">{{ $queue ?? 0 }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow">
            <h4>Spam Detected</h4>
            <p class="text-red-600 text-2xl font-bold">{{ $spam ?? 0 }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow">
            <h4>Forwarders</h4>
            <p>{{ $forwarders ?? 0 }}</p>
        </div>

    </div>

</div>

@endsection
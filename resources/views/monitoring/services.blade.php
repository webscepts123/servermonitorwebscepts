@extends('layouts.app')

@section('page-title', 'Services')

@section('content')
<div class="bg-white rounded-2xl p-6 shadow">
    <h3 class="text-lg font-semibold mb-4">Service Status</h3>

    <ul class="space-y-2">
        <li class="p-3 bg-green-100 rounded-xl">Apache - Running</li>
        <li class="p-3 bg-red-100 rounded-xl">MySQL - Stopped</li>
    </ul>
</div>
@endsection
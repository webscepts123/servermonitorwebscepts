@extends('layouts.app')

@section('page-title', 'Abuse Reports')

@section('content')

<div class="space-y-6">

    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-bold">Abuse Monitoring</h2>
    </div>

    <div class="bg-white p-5 rounded-xl shadow">

        <h4 class="font-bold mb-3">Detected Issues</h4>

        @forelse($abuse ?? [] as $item)
            <div class="border-b py-3">
                <p class="font-bold text-red-600">{{ $item['type'] }}</p>
                <p class="text-sm text-slate-500">{{ $item['message'] }}</p>
            </div>
        @empty
            <p class="text-slate-500">No abuse detected</p>
        @endforelse

    </div>

</div>

@endsection
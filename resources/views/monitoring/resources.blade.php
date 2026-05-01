@extends('layouts.app')

@section('page-title', 'CPU / RAM / Disk')

@section('content')
<div class="bg-white rounded-2xl p-6 shadow">
    <h3 class="text-lg font-semibold mb-4">System Resources</h3>

    <div class="space-y-4">

        <div>
            <p>CPU Usage</p>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-blue-600 h-3 rounded-full" style="width: 45%"></div>
            </div>
        </div>

        <div>
            <p>RAM Usage</p>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-green-600 h-3 rounded-full" style="width: 70%"></div>
            </div>
        </div>

        <div>
            <p>Disk Usage</p>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-red-600 h-3 rounded-full" style="width: 85%"></div>
            </div>
        </div>

    </div>
</div>
@endsection
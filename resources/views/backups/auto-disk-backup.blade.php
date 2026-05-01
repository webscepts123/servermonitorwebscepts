@extends('layouts.app')

@section('content')

<div class="space-y-6">

    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-2xl font-bold text-slate-800">Auto Disk Backup & Protection</h2>
        <p class="text-slate-500 mt-1">
            Automatically trigger backup or transfer when disk usage exceeds limits.
        </p>
    </div>

    @foreach($servers as $server)

        @php
            $latest = $server->checks->first();
            $disk = $latest->disk_usage ?? 0;
            $warning = $server->disk_warning_percent ?? 60;
            $transfer = $server->disk_transfer_percent ?? 90;
        @endphp

        <div class="bg-white rounded-2xl shadow overflow-hidden">

            <div class="px-6 py-5 border-b flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-slate-800">{{ $server->name }}</h3>
                    <p class="text-slate-500">{{ $server->host }}</p>
                </div>

                <span class="px-4 py-2 rounded-full
                    @if($disk >= $transfer) bg-red-100 text-red-700
                    @elseif($disk >= $warning) bg-yellow-100 text-yellow-700
                    @else bg-green-100 text-green-700
                    @endif font-semibold">

                    {{ $disk }}% Disk Usage
                </span>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Disk Status --}}
                <div class="space-y-3">
                    <h4 class="font-bold text-slate-800">Disk Status</h4>

                    <div class="h-3 bg-slate-200 rounded-full">
                        <div class="h-3 rounded-full
                            @if($disk >= $transfer) bg-red-600
                            @elseif($disk >= $warning) bg-yellow-500
                            @else bg-green-600
                            @endif"
                            style="width: {{ min($disk,100) }}%">
                        </div>
                    </div>

                    <p class="text-sm text-slate-500">
                        Warning at {{ $warning }}% · Transfer at {{ $transfer }}%
                    </p>
                </div>

                {{-- Actions --}}
                <div class="space-y-3">
                    <h4 class="font-bold text-slate-800">Auto Actions</h4>

                    <div class="flex flex-wrap gap-3">

                        <form method="POST" action="{{ route('backups.pull') }}">
                            @csrf
                            <input type="hidden" name="server_id" value="{{ $server->id }}">
                            <button class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-700">
                                Pull Backup
                            </button>
                        </form>

                        <form method="POST" action="{{ route('backups.google') }}">
                            @csrf
                            <input type="hidden" name="server_id" value="{{ $server->id }}">
                            <button class="px-4 py-2 rounded-xl bg-green-600 text-white hover:bg-green-700">
                                Google Sync
                            </button>
                        </form>

                        <form method="POST" action="{{ route('backups.transfer') }}">
                            @csrf
                            <input type="hidden" name="server_id" value="{{ $server->id }}">
                            <button class="px-4 py-2 rounded-xl bg-purple-600 text-white hover:bg-purple-700">
                                Transfer Server
                            </button>
                        </form>

                    </div>

                    {{-- Auto status --}}
                    <div class="mt-4 space-y-2 text-sm">

                        @if($server->auto_transfer)
                            <p class="text-purple-600 font-semibold">Auto Transfer Enabled</p>
                        @else
                            <p class="text-slate-500">Auto Transfer Disabled</p>
                        @endif

                        @if($server->google_drive_sync)
                            <p class="text-green-600 font-semibold">Google Sync Enabled</p>
                        @else
                            <p class="text-slate-500">Google Sync Disabled</p>
                        @endif

                    </div>

                </div>

            </div>

            {{-- AUTO LOGIC DISPLAY --}}
            <div class="bg-slate-50 px-6 py-4 text-sm text-slate-600 border-t">

                @if($disk >= $transfer)
                    🚨 <b>Critical:</b> Disk above {{ $transfer }}% → Should auto transfer to backup server
                @elseif($disk >= $warning)
                    ⚠️ <b>Warning:</b> Disk above {{ $warning }}% → Should trigger backup
                @else
                    ✅ System healthy
                @endif

            </div>

        </div>

    @endforeach

</div>

@endsection
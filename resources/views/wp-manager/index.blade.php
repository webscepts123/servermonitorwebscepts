@extends('layouts.app')

@section('page-title', 'WordPress Manager')

@section('content')

<div class="container-fluid py-4">

    @if(session('success'))
        <div class="alert alert-success rounded-3 shadow-sm">
            {!! nl2br(e(session('success'))) !!}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger rounded-3 shadow-sm">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">

        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-1">
                            <i class="fab fa-wordpress text-primary"></i>
                            WordPress Manager
                        </h3>
                        <p class="text-muted mb-0">
                            Manage WordPress core, plugins, themes and security checks.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('wp.core.update') }}">
                        @csrf
                        <button class="btn btn-primary rounded-pill px-4">
                            Update WP Core
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold">WP Core</h5>
                    <p class="text-muted mb-2">Current WordPress version</p>
                    <h2 class="fw-bold text-primary">{{ $coreVersion ?: 'Not detected' }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Security Checks</h5>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <div class="border rounded-4 p-3">
                                <strong>wp-config.php</strong>
                                <p class="mb-1">
                                    Status:
                                    @if($security['wp_config_exists'])
                                        <span class="badge bg-success">Found</span>
                                    @else
                                        <span class="badge bg-danger">Missing</span>
                                    @endif
                                </p>
                                <p class="mb-0">
                                    Permission:
                                    <span class="badge bg-secondary">
                                        {{ $security['wp_config_permission'] }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded-4 p-3">
                                <strong>Writable Config</strong>
                                <p class="mb-1">
                                    @if($security['wp_config_writable'])
                                        <span class="badge bg-danger">Writable - Risk</span>
                                    @else
                                        <span class="badge bg-success">Protected</span>
                                    @endif
                                </p>
                                <p class="text-muted mb-0">
                                    Recommended permission: 0444 or 0400
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded-4 p-3">
                                <strong>public_html Permission</strong>
                                <p class="mb-0">
                                    <span class="badge bg-secondary">
                                        {{ $security['public_html_permission'] }}
                                    </span>
                                </p>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="fw-bold mb-1">Plugins</h4>
                            <p class="text-muted mb-0">Activate, deactivate and update WordPress plugins.</p>
                        </div>

                        <form method="POST" action="{{ route('wp.plugins.update.all') }}">
                            @csrf
                            <button class="btn btn-success rounded-pill px-4">
                                Update All Plugins
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Plugin</th>
                                    <th>Status</th>
                                    <th>Version</th>
                                    <th>Update</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($plugins as $plugin)
                                    <tr>
                                        <td>
                                            <strong>{{ $plugin['name'] }}</strong>
                                        </td>

                                        <td>
                                            @if($plugin['status'] === 'active')
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>

                                        <td>{{ $plugin['version'] ?? '-' }}</td>

                                        <td>
                                            @if(($plugin['update'] ?? 'none') !== 'none')
                                                <span class="badge bg-warning text-dark">Available</span>
                                            @else
                                                <span class="badge bg-success">Updated</span>
                                            @endif
                                        </td>

                                        <td class="text-end">

                                            @if($plugin['status'] === 'active')
                                                <form method="POST" action="{{ route('wp.plugin.deactivate') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="plugin" value="{{ $plugin['name'] }}">
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('wp.plugin.activate') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="plugin" value="{{ $plugin['name'] }}">
                                                    <button class="btn btn-sm btn-outline-success rounded-pill">
                                                        Activate
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('wp.plugin.update') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="plugin" value="{{ $plugin['name'] }}">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill">
                                                    Update
                                                </button>
                                            </form>

                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No WordPress plugins found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                        </table>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">

                    <h4 class="fw-bold mb-1">Themes</h4>
                    <p class="text-muted mb-3">Manage installed WordPress themes.</p>

                    <div class="row g-3">

                        @forelse($themes as $theme)
                            <div class="col-md-4">
                                <div class="border rounded-4 p-3 h-100">

                                    <h5 class="fw-bold">{{ $theme['name'] }}</h5>

                                    <p class="mb-1">
                                        Version:
                                        <strong>{{ $theme['version'] ?? '-' }}</strong>
                                    </p>

                                    <p class="mb-3">
                                        Status:
                                        @if($theme['status'] === 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </p>

                                    @if($theme['status'] !== 'active')
                                        <form method="POST" action="{{ route('wp.theme.activate') }}">
                                            @csrf
                                            <input type="hidden" name="theme" value="{{ $theme['name'] }}">
                                            <button class="btn btn-sm btn-primary rounded-pill">
                                                Activate Theme
                                            </button>
                                        </form>
                                    @endif

                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center text-muted py-4">
                                    No themes found.
                                </div>
                            </div>
                        @endforelse

                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

@endsection
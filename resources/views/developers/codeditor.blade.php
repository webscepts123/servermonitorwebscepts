<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web VS Code - Developer Codes</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
            background: #020617;
        }

        .editor-frame {
            width: 100%;
            height: 100%;
            border: 0;
            background: #ffffff;
        }

        .loader {
            width: 42px;
            height: 42px;
            border: 4px solid rgba(255,255,255,.2);
            border-top-color: #3b82f6;
            border-radius: 999px;
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>

@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;

    $developerName = $developer->name
        ?? $developer->cpanel_username
        ?? $developer->email
        ?? 'Developer';

    $developerEmail = $developer->email
        ?? $developer->contact_email
        ?? 'No email';

    $domain = $developer->cpanel_domain
        ?? $developer->domain
        ?? 'developer workspace';

    $cpanelUsername = $developer->cpanel_username
        ?? $developer->ssh_username
        ?? 'developer';

    $framework = strtoupper($developer->framework ?? 'custom');

    $projectRoot = $projectRoot
        ?? $developer->project_root
        ?? $developer->allowed_project_path
        ?? null;

    if (!$projectRoot) {
        $projectRoot = '/home/' . $cpanelUsername . '/public_html';
    }

    $projectRoot = rtrim($projectRoot, '/');

    $editorBackendUrl = $editorBackendUrl
        ?? $developer->code_editor_url
        ?? $developer->vscode_url
        ?? null;

    if (!$editorBackendUrl && !empty($cpanelUsername)) {
        $safeUsername = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $cpanelUsername));
        $safeUsername = trim($safeUsername, '-');
        $editorBackendUrl = 'https://code-' . $safeUsername . '.webscepts.com';
    }

    if ($editorBackendUrl && !Str::startsWith($editorBackendUrl, ['http://', 'https://'])) {
        $editorBackendUrl = 'https://' . $editorBackendUrl;
    }

    $editorBackendUrl = $editorBackendUrl ? rtrim($editorBackendUrl, '/') : null;

    $currentPublicEditorUrl = Route::has('developer.domain.codeditor')
        ? route('developer.domain.codeditor')
        : url('/codeditor');

    $workspaceUrl = Route::has('developer.domain.workspace')
        ? route('developer.domain.workspace')
        : url('/workspace');

    $logoutUrl = Route::has('developer.logout')
        ? route('developer.logout')
        : url('/logout');

    $isSelfIframe = false;

    if ($editorBackendUrl) {
        $cleanBackend = rtrim($editorBackendUrl, '/');
        $cleanPublic = rtrim($currentPublicEditorUrl, '/');

        $isSelfIframe = $cleanBackend === $cleanPublic
            || str_contains($cleanBackend, 'developercodes.webscepts.com/codeditor')
            || str_ends_with($cleanBackend, '/codeditor');
    }
@endphp

<div class="h-screen w-screen flex flex-col bg-slate-950 overflow-hidden">

    {{-- Top Bar --}}
    <header class="h-16 bg-slate-950 border-b border-white/10 px-4 lg:px-6 flex items-center justify-between text-white shrink-0">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-11 h-11 rounded-2xl bg-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-code text-lg"></i>
            </div>

            <div class="min-w-0">
                <h1 class="font-black text-lg leading-tight">
                    Web VS Code
                </h1>

                <p class="text-xs text-slate-400 truncate">
                    Developer: {{ $developerName }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 lg:gap-3 min-w-0">
            <span class="hidden xl:inline-flex px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black max-w-[280px] truncate">
                Domain: {{ $domain }}
            </span>

            <span class="hidden xl:inline-flex px-4 py-2 rounded-full bg-white/10 border border-white/10 text-xs font-black max-w-[380px] truncate">
                {{ $projectRoot }}
            </span>

            @if($editorBackendUrl && !$isSelfIframe)
                <a href="{{ $editorBackendUrl }}"
                   target="_blank"
                   class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm transition">
                    <i class="fa-solid fa-up-right-from-square mr-2"></i>
                    Open Backend
                </a>
            @endif

            <a href="{{ $workspaceUrl }}"
               class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-black text-sm transition">
                <i class="fa-solid fa-arrow-left mr-2"></i>
                Workspace
            </a>

            <form method="POST" action="{{ $logoutUrl }}" class="hidden md:block">
                @csrf
                <button type="submit"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-sm transition">
                    <i class="fa-solid fa-right-from-bracket mr-2"></i>
                    Logout
                </button>
            </form>
        </div>
    </header>

    {{-- Info Bar --}}
    <section class="bg-slate-900 border-b border-white/10 px-4 lg:px-6 py-3 text-white shrink-0">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 rounded-full bg-blue-500/20 text-blue-200 border border-blue-400/30 text-xs font-black">
                    Domain: {{ $domain }}
                </span>

                <span class="px-3 py-1 rounded-full bg-green-500/20 text-green-200 border border-green-400/30 text-xs font-black">
                    User: {{ $cpanelUsername }}
                </span>

                <span class="px-3 py-1 rounded-full bg-purple-500/20 text-purple-200 border border-purple-400/30 text-xs font-black">
                    Framework: {{ $framework }}
                </span>
            </div>

            <div class="text-xs text-slate-400 font-bold break-all">
                VS Code Backend:
                <span class="text-slate-200">
                    {{ $editorBackendUrl ?: 'Not configured' }}
                </span>
            </div>
        </div>
    </section>

    {{-- Editor Area --}}
    <main class="flex-1 bg-slate-100 relative overflow-hidden">

        @if(!$editorBackendUrl)
            <div class="absolute inset-0 flex items-center justify-center p-6">
                <div class="max-w-xl w-full rounded-3xl bg-white border border-red-200 shadow-xl p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                    </div>

                    <h2 class="text-2xl font-black text-slate-900 mt-5">
                        VS Code Backend URL Missing
                    </h2>

                    <p class="text-slate-500 mt-2 font-bold">
                        Go to the admin cPanel Developer Logins page and press Setup VS Code + SSL for this developer account.
                    </p>

                    <div class="mt-5 rounded-2xl bg-slate-100 border border-slate-200 p-4 text-left text-sm font-bold text-slate-700">
                        Correct backend example:
                        <div class="mt-1 text-blue-700 break-all">
                            https://code-{{ strtolower($cpanelUsername) }}.webscepts.com
                        </div>
                    </div>

                    <a href="{{ $workspaceUrl }}"
                       class="inline-flex mt-6 px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                        Back to Workspace
                    </a>
                </div>
            </div>

        @elseif($isSelfIframe)
            <div class="absolute inset-0 flex items-center justify-center p-6">
                <div class="max-w-2xl w-full rounded-3xl bg-white border border-red-200 shadow-xl p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-rotate-left text-2xl"></i>
                    </div>

                    <h2 class="text-2xl font-black text-slate-900 mt-5">
                        Wrong VS Code Backend URL
                    </h2>

                    <p class="text-slate-500 mt-2 font-bold">
                        The backend URL is pointing back to the public Developer Codes page. It must point to the real code-server backend.
                    </p>

                    <div class="mt-5 rounded-2xl bg-red-50 border border-red-200 p-4 text-left text-sm font-bold text-red-700">
                        Wrong:
                        <div class="mt-1 break-all">
                            {{ $editorBackendUrl }}
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl bg-green-50 border border-green-200 p-4 text-left text-sm font-bold text-green-700">
                        Correct:
                        <div class="mt-1 break-all">
                            https://code-{{ strtolower($cpanelUsername) }}.webscepts.com
                        </div>
                    </div>

                    <a href="{{ $workspaceUrl }}"
                       class="inline-flex mt-6 px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black">
                        Back to Workspace
                    </a>
                </div>
            </div>

        @else
            <div id="loadingBox" class="absolute inset-0 z-10 flex items-center justify-center bg-slate-950 text-white">
                <div class="text-center">
                    <div class="loader mx-auto"></div>
                    <div class="mt-4 font-black">
                        Loading Web VS Code...
                    </div>
                    <div class="mt-2 text-xs text-slate-400 break-all max-w-xl">
                        {{ $editorBackendUrl }}
                    </div>
                </div>
            </div>

            <iframe
                id="codeEditorFrame"
                src="{{ $editorBackendUrl }}"
                class="editor-frame"
                allow="clipboard-read; clipboard-write; fullscreen"
                referrerpolicy="no-referrer-when-downgrade"
                onload="document.getElementById('loadingBox')?.remove();">
            </iframe>
        @endif

    </main>
</div>

</body>
</html>
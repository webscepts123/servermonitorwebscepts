<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visual Code Editor - Developer Codes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs/loader.min.js"></script>

    <style>
        html, body {
            margin: 0;
            height: 100%;
            overflow: hidden;
            background: #020617;
        }

        #editor {
            width: 100%;
            height: 100%;
        }

        .file-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            color: #cbd5e1;
        }

        .file-row:hover {
            background: #1e293b;
            color: #fff;
        }

        .file-row.active {
            background: #2563eb;
            color: #fff;
        }

        .sidebar-scroll::-webkit-scrollbar,
        .tabs-scroll::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb,
        .tabs-scroll::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 99px;
        }
    </style>
</head>

<body>

@php
    $developerName = $developer->name ?? $developer->cpanel_username ?? $developer->email ?? 'Developer';
    $cpanelUsername = $developer->cpanel_username ?? $developer->ssh_username ?? 'developer';
@endphp

<div class="h-screen w-screen flex flex-col bg-slate-950 text-white">

    <header class="h-14 bg-slate-950 border-b border-white/10 px-4 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center font-black">
                &lt;/&gt;
            </div>

            <div class="min-w-0">
                <div class="font-black leading-tight truncate">Developer Visual Code Editor</div>
                <div class="text-xs text-slate-400 truncate">
                    {{ $developerName }} • {{ $cpanelUsername }}
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="createNewFile()"
                    class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-black">
                + New File
            </button>

            <button onclick="saveCurrentFile()"
                    id="saveBtn"
                    class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-black disabled:opacity-50"
                    disabled>
                Save
            </button>

            <a href="{{ route('developer.domain.workspace') }}"
               class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-black">
                Workspace
            </a>

            <form method="POST" action="{{ route('developer.logout') }}">
                @csrf
                <button class="px-3 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-xs font-black">
                    Logout
                </button>
            </form>
        </div>
    </header>

    <section class="h-10 bg-slate-900 border-b border-white/10 px-4 flex items-center justify-between shrink-0">
        <div class="text-xs text-slate-400 truncate">
            Root:
            <span class="text-slate-200 font-bold" id="rootLabel">{{ $projectRoot }}</span>
        </div>

        <div class="text-xs font-bold" id="statusText">
            Ready
        </div>
    </section>

    <div class="flex-1 flex min-h-0">

        <aside class="w-80 bg-slate-950 border-r border-white/10 flex flex-col min-h-0">
            <div class="p-3 border-b border-white/10">
                <button onclick="loadTree(currentDir || '{{ $startDir }}')"
                        class="w-full px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-sm font-black">
                    Refresh Files
                </button>
            </div>

            <div class="px-3 py-2 border-b border-white/10">
                <button id="parentBtn"
                        onclick="goParent()"
                        class="w-full px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-black disabled:opacity-40"
                        disabled>
                    ↑ Parent Folder
                </button>
            </div>

            <div id="fileTree"
                 class="flex-1 overflow-auto sidebar-scroll p-2 text-sm">
                <div class="text-slate-500 p-3 text-xs font-bold">Loading files...</div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 min-h-0">

            <div class="h-11 bg-slate-900 border-b border-white/10 flex items-center overflow-x-auto tabs-scroll shrink-0"
                 id="tabsBar">
                <div class="px-4 text-xs text-slate-400 font-bold">
                    No file opened
                </div>
            </div>

            <div class="flex-1 min-h-0 relative">
                <div id="editor"></div>

                <div id="emptyState"
                     class="absolute inset-0 flex items-center justify-center bg-slate-900">
                    <div class="text-center max-w-xl p-8">
                        <div class="text-5xl mb-4">💻</div>
                        <h1 class="text-2xl font-black">Visual Code Editor</h1>
                        <p class="text-slate-400 mt-2">
                            This editor works through WHM/cPanel File Manager API.
                            No SSH, no root, no code-server backend needed.
                        </p>
                        <p class="text-slate-500 mt-4 text-sm">
                            Select a file from the left side to open it.
                        </p>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
    const routes = {
        tree: @json(route('developer.domain.codeditor.tree')),
        read: @json(route('developer.domain.codeditor.read')),
        save: @json(route('developer.domain.codeditor.save')),
        createFile: @json(route('developer.domain.codeditor.file.create')),
    };

    const csrfToken = @json(csrf_token());
    const startDir = @json($startDir);

    let monacoEditor = null;
    let currentDir = startDir;
    let parentDir = null;
    let currentFile = null;
    let currentLanguage = 'plaintext';
    let isDirty = false;

    function setStatus(message, type = 'normal') {
        const box = document.getElementById('statusText');

        box.textContent = message;
        box.className = 'text-xs font-bold ' + (
            type === 'error' ? 'text-red-300' :
            type === 'success' ? 'text-green-300' :
            type === 'warning' ? 'text-yellow-300' :
            'text-slate-300'
        );
    }

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function (m) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[m];
        });
    }

    function iconFor(item) {
        if (item.type === 'dir') {
            return '📁';
        }

        const name = (item.name || '').toLowerCase();

        if (name.endsWith('.php')) return '🐘';
        if (name.endsWith('.js')) return '🟨';
        if (name.endsWith('.css')) return '🎨';
        if (name.endsWith('.html')) return '🌐';
        if (name.endsWith('.json')) return '🔧';
        if (name.endsWith('.blade.php')) return '🧩';

        return '📄';
    }

    async function apiGet(url, params = {}) {
        const query = new URLSearchParams(params).toString();
        const response = await fetch(url + (query ? '?' + query : ''), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const json = await response.json();

        if (!json.ok) {
            throw new Error(json.message || 'API request failed.');
        }

        return json;
    }

    async function apiPost(url, data = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const json = await response.json();

        if (!json.ok) {
            throw new Error(json.message || 'API request failed.');
        }

        return json;
    }

    async function loadTree(dir) {
        try {
            setStatus('Loading files...');

            const json = await apiGet(routes.tree, { dir });

            currentDir = json.dir;
            parentDir = json.parent;

            document.getElementById('parentBtn').disabled = !parentDir;

            const tree = document.getElementById('fileTree');
            tree.innerHTML = '';

            if (!json.items.length) {
                tree.innerHTML = '<div class="text-slate-500 p-3 text-xs font-bold">This folder is empty.</div>';
                setStatus('Folder loaded', 'success');
                return;
            }

            json.items.forEach(item => {
                const row = document.createElement('div');
                row.className = 'file-row';
                row.dataset.path = item.path;

                row.innerHTML = `
                    <span>${iconFor(item)}</span>
                    <span class="truncate flex-1">${escapeHtml(item.name)}</span>
                    <span class="text-[10px] opacity-60">${escapeHtml(item.size || '')}</span>
                `;

                row.onclick = function () {
                    if (item.type === 'dir') {
                        loadTree(item.path);
                    } else {
                        openFile(item.path);
                    }
                };

                tree.appendChild(row);
            });

            setStatus('Folder loaded', 'success');
        } catch (error) {
            setStatus(error.message, 'error');
            document.getElementById('fileTree').innerHTML =
                '<div class="text-red-300 p-3 text-xs font-bold">' + escapeHtml(error.message) + '</div>';
        }
    }

    function goParent() {
        if (parentDir) {
            loadTree(parentDir);
        }
    }

    async function openFile(path) {
        if (isDirty) {
            const ok = confirm('You have unsaved changes. Open another file?');

            if (!ok) {
                return;
            }
        }

        try {
            setStatus('Opening file...');

            const json = await apiGet(routes.read, { path });

            currentFile = json.path;
            currentLanguage = json.language || 'plaintext';
            isDirty = false;

            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('saveBtn').disabled = false;

            monaco.editor.setModelLanguage(monacoEditor.getModel(), currentLanguage);
            monacoEditor.setValue(json.content || '');

            renderTab(currentFile);

            document.querySelectorAll('.file-row').forEach(row => {
                row.classList.toggle('active', row.dataset.path === currentFile);
            });

            setStatus('Opened: ' + json.filename, 'success');
        } catch (error) {
            setStatus(error.message, 'error');
            alert(error.message);
        }
    }

    function renderTab(path) {
        const name = path.split('/').pop();

        document.getElementById('tabsBar').innerHTML = `
            <div class="h-full flex items-center gap-2 px-4 bg-slate-800 border-r border-white/10 text-xs font-black">
                <span>${escapeHtml(name)}</span>
                <span id="dirtyDot" class="text-blue-300">${isDirty ? '●' : ''}</span>
            </div>
        `;
    }

    async function saveCurrentFile() {
        if (!currentFile) {
            return;
        }

        try {
            setStatus('Saving...');

            await apiPost(routes.save, {
                path: currentFile,
                content: monacoEditor.getValue()
            });

            isDirty = false;
            renderTab(currentFile);

            setStatus('Saved successfully', 'success');
        } catch (error) {
            setStatus(error.message, 'error');
            alert(error.message);
        }
    }

    async function createNewFile() {
        const name = prompt('New file name, example: test.php');

        if (!name) {
            return;
        }

        try {
            setStatus('Creating file...');

            const json = await apiPost(routes.createFile, {
                dir: currentDir,
                name: name
            });

            await loadTree(currentDir);
            await openFile(json.path);

            setStatus('File created', 'success');
        } catch (error) {
            setStatus(error.message, 'error');
            alert(error.message);
        }
    }

    require.config({
        paths: {
            vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs'
        }
    });

    require(['vs/editor/editor.main'], function () {
        monacoEditor = monaco.editor.create(document.getElementById('editor'), {
            value: '',
            language: 'plaintext',
            theme: 'vs-dark',
            automaticLayout: true,
            fontSize: 14,
            minimap: {
                enabled: true
            },
            wordWrap: 'on',
            scrollBeyondLastLine: false
        });

        monacoEditor.onDidChangeModelContent(function () {
            if (currentFile) {
                isDirty = true;
                renderTab(currentFile);
                setStatus('Unsaved changes', 'warning');
            }
        });

        loadTree(startDir);
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            saveCurrentFile();
        }
    });
</script>

</body>
</html>
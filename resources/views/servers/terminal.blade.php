@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto">
    <div class="bg-[#050816] rounded-2xl shadow overflow-hidden border border-slate-800">

        <div class="px-6 py-4 border-b border-white/10 flex justify-between items-center">
            <div>
                <h2 class="text-white text-xl font-bold">SSH Terminal</h2>
                <p class="text-slate-400 text-sm">
                    {{ $server->name }} ({{ $server->username }}@{{ $server->host }}:{{ $server->ssh_port }})
                </p>
            </div>

            <a href="{{ route('servers.show', $server) }}"
               class="px-4 py-2 bg-white text-slate-900 rounded-xl hover:bg-slate-200">
                Back
            </a>
        </div>

        <div id="terminal"
             class="h-[520px] overflow-y-auto bg-black text-green-400 font-mono text-sm p-5">
            <div>Connected to monitor terminal.</div>
            <div>Server: {{ $server->host }}</div>
            <div>Type command and press Enter.</div>
            <br>
        </div>

        <form id="terminalForm" class="flex border-t border-slate-800 bg-black">
            @csrf

            <div id="terminalPrompt" class="px-4 py-3 text-green-400 font-mono whitespace-nowrap">
                {{ $server->username }}@{{ $server->name }}:~#
            </div>

            <input id="commandInput"
                   type="text"
                   autocomplete="off"
                   class="flex-1 bg-black text-green-400 font-mono outline-none px-2"
                   placeholder="uptime, df -h, free -m, systemctl status cpanel">

            <button type="submit"
                    class="px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold">
                Run
            </button>
        </form>

        <div class="bg-slate-950 p-3 flex gap-2 flex-wrap">
            <button type="button" onclick="setCommand('uptime')" class="cmd-btn">uptime</button>
            <button type="button" onclick="setCommand('df -h')" class="cmd-btn">disk</button>
            <button type="button" onclick="setCommand('free -m')" class="cmd-btn">ram</button>
            <button type="button" onclick="setCommand('top -bn1 | head -20')" class="cmd-btn">cpu</button>
            <button type="button" onclick="setCommand('systemctl status cpanel')" class="cmd-btn">cpanel</button>
            <button type="button" onclick="setCommand('systemctl status httpd')" class="cmd-btn">apache</button>
            <button type="button" onclick="setCommand('systemctl status mariadb')" class="cmd-btn">mysql</button>
            <button type="button" onclick="setCommand('ss -tulpn | grep -E &quot;80|443|2087|8443&quot;')" class="cmd-btn">ports</button>
        </div>
    </div>
</div>

<style>
    .cmd-btn {
        padding: 6px 12px;
        background: #1e293b;
        color: white;
        border-radius: 8px;
        font-size: 12px;
    }

    .cmd-btn:hover {
        background: #334155;
    }
</style>

<script>
const terminal = document.getElementById('terminal');
const form = document.getElementById('terminalForm');
const input = document.getElementById('commandInput');

const serverName = @json($server->name);
const serverUser = @json($server->username);

function appendLine(text, className = '') {
    const line = document.createElement('div');
    line.className = className;
    line.textContent = text;
    terminal.appendChild(line);
    terminal.scrollTop = terminal.scrollHeight;
}

function appendOutput(text) {
    const pre = document.createElement('pre');
    pre.className = 'whitespace-pre-wrap text-green-400';
    pre.textContent = text;
    terminal.appendChild(pre);
    terminal.scrollTop = terminal.scrollHeight;
}

function setCommand(command) {
    input.value = command;
    input.focus();
}

form.addEventListener('submit', async function(e) {
    e.preventDefault();

    const command = input.value.trim();

    if (!command) {
        return;
    }

    appendLine(serverUser + '@' + serverName + ':~# ' + command, 'text-white');

    input.value = '';
    input.disabled = true;

    try {
        const response = await fetch(@json(route('servers.terminal.run', $server)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                command: command
            })
        });

        const data = await response.json();

        if (data.success) {
            appendOutput(data.output || 'Command completed with no output.');
        } else {
            appendLine(data.output || 'Command failed.', 'text-red-400');
        }

    } catch (error) {
        appendLine('Connection error: ' + error.message, 'text-red-400');
    }

    input.disabled = false;
    input.focus();
});

input.focus();
</script>

@endsection
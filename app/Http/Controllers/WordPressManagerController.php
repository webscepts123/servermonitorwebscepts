<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;

class WordPressManagerController extends Controller
{
    private function wpPath(string $user): string
    {
        return "/home/{$user}/public_html";
    }

    private function runWp(string $user, string $command): string
    {
        $path = escapeshellarg($this->wpPath($user));

        return trim(shell_exec("wp {$command} --path={$path} --allow-root 2>&1") ?? '');
    }

    public function show(Server $server, string $account)
    {
        $coreVersion = $this->runWp($account, 'core version');

        $plugins = json_decode($this->runWp($account, 'plugin list --format=json'), true) ?? [];
        $themes = json_decode($this->runWp($account, 'theme list --format=json'), true) ?? [];

        return view('servers.wordpress-show', [
            'server' => $server,
            'user' => $account,
            'coreVersion' => $coreVersion,
            'plugins' => $plugins,
            'themes' => $themes,
            'wpPath' => $this->wpPath($account),
        ]);
    }

    public function updateCore(Server $server, string $account)
    {
        return back()->with('success', $this->runWp($account, 'core update'));
    }

    public function updateAllPlugins(Server $server, string $account)
    {
        return back()->with('success', $this->runWp($account, 'plugin update --all'));
    }

    public function activatePlugin(Request $request, Server $server, string $account)
    {
        $request->validate([
            'plugin' => 'required|string',
        ]);

        return back()->with('success', $this->runWp($account, 'plugin activate ' . escapeshellarg($request->plugin)));
    }

    public function deactivatePlugin(Request $request, Server $server, string $account)
    {
        $request->validate([
            'plugin' => 'required|string',
        ]);

        return back()->with('success', $this->runWp($account, 'plugin deactivate ' . escapeshellarg($request->plugin)));
    }

    public function updatePlugin(Request $request, Server $server, string $account)
    {
        $request->validate([
            'plugin' => 'required|string',
        ]);

        return back()->with('success', $this->runWp($account, 'plugin update ' . escapeshellarg($request->plugin)));
    }

    public function activateTheme(Request $request, Server $server, string $account)
    {
        $request->validate([
            'theme' => 'required|string',
        ]);

        return back()->with('success', $this->runWp($account, 'theme activate ' . escapeshellarg($request->theme)));
    }
}
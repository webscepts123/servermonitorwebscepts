<?php

namespace App\Console\Commands;

use App\Http\Controllers\ServerController;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAllServersCommand extends Command
{
    protected $signature = 'servers:check-all 
                            {--server_id= : Check only one server ID}
                            {--sleep=2 : Seconds to wait between each server check}';

    protected $description = 'Run the same server check as the Check button for all servers every 30 minutes.';

    public function handle(): int
    {
        $serverId = $this->option('server_id');
        $sleepSeconds = (int) $this->option('sleep');

        $query = Server::query();

        if ($serverId) {
            $query->where('id', $serverId);
        }

        $servers = $query->orderBy('id')->get();

        if ($servers->isEmpty()) {
            $this->warn('No servers found to check.');
            return self::SUCCESS;
        }

        $this->info('Starting server checks. Total servers: ' . $servers->count());

        foreach ($servers as $server) {
            try {
                $this->line('Checking server #' . $server->id . ' - ' . ($server->name ?? 'Server') . ' - ' . ($server->host ?? ''));

                /*
                |--------------------------------------------------------------------------
                | Reuse existing Check button logic
                |--------------------------------------------------------------------------
                | Your button uses:
                | ServerController@checkNow
                |--------------------------------------------------------------------------
                */

                app(ServerController::class)->checkNow($server);

                $this->info('Checked successfully: server #' . $server->id);

                Log::info('Scheduled server check completed.', [
                    'server_id' => $server->id,
                    'server_name' => $server->name ?? null,
                    'server_host' => $server->host ?? null,
                ]);
            } catch (\Throwable $e) {
                $this->error('Check failed for server #' . $server->id . ': ' . $e->getMessage());

                Log::error('Scheduled server check failed.', [
                    'server_id' => $server->id,
                    'server_name' => $server->name ?? null,
                    'server_host' => $server->host ?? null,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
        }

        $this->info('All server checks completed.');

        return self::SUCCESS;
    }
}
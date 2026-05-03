<?php

namespace App\Mail;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServerRecoveryAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Server $server) {}

    public function build()
    {
        return $this->subject('✅ Server Recovered - ' . $this->server->name)
            ->view('emails.server-recovered');
    }
}
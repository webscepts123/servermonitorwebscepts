<?php

namespace App\Mail;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServerDownAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Server $server) {}

    public function build()
    {
        return $this->subject('🚨 Server Down Alert - ' . $this->server->name)
            ->view('emails.server-down');
    }
}
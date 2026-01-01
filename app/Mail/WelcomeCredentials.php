<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $generatedId;
    public $role;

    public function __construct($name, $generatedId, $role)
    {
        $this->name = $name;
        $this->generatedId = $generatedId;
        $this->role = $role;
    }

    public function build()
    {
        return $this->subject("Welcome to MATEM College of Education")
            ->view('emails.welcome_credentials');
    }
}

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
    public $password; // Optional: Only if you want to send the initial password

    public function __construct($name, $generatedId, $role)
    {
        $this->name = $name;
        $this->generatedId = $generatedId;
        $this->role = $role;
        // Since logic sets password = generatedID, we can just say that in the email
    }

    public function build()
    {
        return $this->subject("Welcome to College App - Registration Successful")
            ->view('emails.welcome_credentials');
    }
}

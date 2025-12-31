<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeCredentials;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send welcome email with credentials
     */
    public function sendWelcomeEmail($user, $generatedId, $role)
    {
        try {
            Mail::to($user->email)->send(new WelcomeCredentials(
                $user->name,
                $generatedId,
                $role
            ));
        } catch (\Exception $e) {
            Log::error("Failed to send welcome email to {$user->email}: " . $e->getMessage());
        }
    }
}

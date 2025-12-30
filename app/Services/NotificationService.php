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
            // We use 'queue' instead of 'send' to make it run in the background
            // so the API response is instant.
            Mail::to($user->email)->send(new WelcomeCredentials(
                $user->name,
                $generatedId,
                $role
            ));
        } catch (\Exception $e) {
            // Log error but don't crash the app if email fails
            Log::error("Failed to send welcome email to {$user->email}: " . $e->getMessage());
        }
    }
}

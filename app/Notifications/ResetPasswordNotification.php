<?php

namespace App\Notifications;

use App\Support\ResetLink;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset email in the store's own design (see resources/views/emails/reset-password.blade.php).
 * The token, link and expiry handling are Laravel's; only the message is customised.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $data = [
            'appName' => 'One Prompt Away',
            'name' => $notifiable->name,
            'url' => $this->resetUrl($notifiable),
            'minutes' => $minutes,
            'logoPath' => public_path('images/logo.png'),
        ];

        return (new MailMessage)
            ->subject('Reset your One Prompt Away password')
            ->view(['emails.reset-password', 'emails.reset-password-text'], $data);
    }

    /** The link carries one sealed code (email + token) instead of an editable ?email= parameter. */
    protected function resetUrl($notifiable): string
    {
        return url(route('password.reset', [
            'token' => ResetLink::make($notifiable->getEmailForPasswordReset(), $this->token),
        ], false));
    }
}

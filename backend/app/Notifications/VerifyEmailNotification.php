<?php

namespace App\Notifications;

use App\Services\WebsiteSettingsService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    /**
     * @param  string  $verifyId  Prefixed entity id: "pending:{uuid}" for signups, "user:{uuid}" for email changes.
     * @param  string  $email  Address the link is sent to (used for the hash).
     */
    public function __construct(
        public string $verifyId,
        public string $email,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            ['id' => $this->verifyId, 'hash' => sha1($this->email)]
        );

        $siteName = WebsiteSettingsService::getSiteName();

        return (new MailMessage)
            ->subject(__('auth.verify_email.mail_subject', ['site_name' => $siteName]))
            ->view('emails.verify-email', [
                'url' => $verificationUrl,
                'site_name' => $siteName,
                'support_email' => WebsiteSettingsService::getSiteEmail() ?: Config::get('mail.from.address'),
                'expire_minutes' => Config::get('auth.verification.expire', 60),
            ]);
    }
}
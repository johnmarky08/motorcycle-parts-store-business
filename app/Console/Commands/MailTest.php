<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Address that receives the test message}';

    protected $description = 'Send a test email to confirm the MAIL_* settings work (used by password reset)';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $this->line("Sending with mailer \"{$mailer}\" from ".config('mail.from.address').' ...');

        try {
            Mail::raw("This is a test message from ".config('app.name').".\nIf you can read this, password reset emails will work.", function ($message) {
                $message->to($this->argument('email'))->subject(config('app.name').' mail test');
            });
        } catch (\Throwable $exception) {
            $this->error('Sending failed: '.$exception->getMessage());
            $this->line('Check MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS in .env (see README).');

            return self::FAILURE;
        }

        $this->info('Message handed to the mail server. Check the inbox and the spam/junk folder.');

        return self::SUCCESS;
    }
}

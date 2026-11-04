<?php

namespace App\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends mail with PHP's built-in mail() function (MAIL_MAILER=phpmail).
 * It needs a working mail setup on the server (sendmail on Linux, or sendmail_path / SMTP in php.ini on XAMPP).
 */
class PhpMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $raw = $message->toString();
        [$headerBlock, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');

        // mail() adds To and Subject itself, so they are removed from the extra headers.
        $headers = [];
        $subject = '';
        foreach (preg_split("/\r?\n(?![ \t])/", $headerBlock) as $line) {
            $line = preg_replace("/\r?\n[ \t]+/", ' ', $line);
            if (stripos($line, 'Subject:') === 0) {
                $subject = trim(substr($line, 8));
            } elseif (stripos($line, 'To:') !== 0) {
                $headers[] = $line;
            }
        }

        $recipients = array_map(fn ($address) => $address->getAddress(), $message->getEnvelope()->getRecipients());
        $sent = @mail(implode(',', $recipients), $subject, $body, implode("\r\n", $headers));

        if (! $sent) {
            throw new TransportException('PHP mail() could not send the message. Check the mail settings of this server.');
        }
    }

    public function __toString(): string
    {
        return 'phpmail://default';
    }
}

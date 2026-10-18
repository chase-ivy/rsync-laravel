<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends Laravel's emails (verification links, password-reset codes) through Brevo's web API.
 *
 * Why not SMTP: Render's free plan blocks the SMTP ports (25, 465, 587). The API uses normal
 * HTTPS (port 443), which works everywhere. Used when MAIL_MAILER=brevo.
 * Docs: https://developers.brevo.com/reference/sendtransacemail
 */
class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $people = fn (array $addresses) => array_map(
            fn (Address $a) => array_filter(['email' => $a->getAddress(), 'name' => $a->getName()]),
            $addresses
        );

        $from = $email->getFrom()[0] ?? null;
        if (! $from) {
            throw new RuntimeException('Brevo: the email has no sender (set MAIL_FROM_ADDRESS).');
        }

        $payload = array_filter([
            'sender' => array_filter(['email' => $from->getAddress(), 'name' => $from->getName()]),
            'to' => $people($email->getTo()),
            'cc' => $people($email->getCc()),
            'bcc' => $people($email->getBcc()),
            'replyTo' => ($r = $email->getReplyTo()[0] ?? null) ? array_filter(['email' => $r->getAddress(), 'name' => $r->getName()]) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
        ]);

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(['api-key' => $this->apiKey])
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            // Brevo explains the problem in "message" (e.g. sender not verified, IP not authorised).
            throw new RuntimeException('Brevo could not send the email (HTTP ' . $response->status() . '): '
                . ($response->json('message') ?? 'no details'));
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
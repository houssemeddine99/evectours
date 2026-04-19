<?php

namespace App\Service;

use Resend;

class MailerService
{
    private \Resend\Client $resend;

    public function __construct(string $apiKey)
    {
        $this->resend = Resend::client($apiKey);
    }

    public function sendMailTo(string $mailAddress): void
    {
        $this->resend->emails->send([
            'from'    => 'Travigir Bot <onboarding@resend.dev>',
            'to'      => [$mailAddress],
            'subject' => 'Message from Travigir Bot',
            'html'    => '<p>Hello! This is a message from Travigir bot.</p>',
        ]);
    }
}
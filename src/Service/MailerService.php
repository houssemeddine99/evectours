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
            'html'    => '<p>Hello! you successfully registered in a voyage.</p>',
        ]);
    }

    /** @param array<string,string> $data */
    public function sendContactForm(array $data): void
    {
        $name        = htmlspecialchars($data['full_name'] ?? '', ENT_QUOTES);
        $email       = htmlspecialchars($data['email'] ?? '', ENT_QUOTES);
        $phone       = htmlspecialchars($data['phone'] ?: 'Not provided', ENT_QUOTES);
        $destination = htmlspecialchars($data['destination'] ?? '', ENT_QUOTES);
        $travelType  = htmlspecialchars($data['travel_type'] ?? '', ENT_QUOTES);
        $duration    = htmlspecialchars($data['duration'] ?? '', ENT_QUOTES);
        $budget      = htmlspecialchars($data['budget'] ?? '', ENT_QUOTES);
        $dates       = htmlspecialchars($data['travel_dates'] ?? '', ENT_QUOTES);
        $message     = nl2br(htmlspecialchars($data['message'] ?? '', ENT_QUOTES));

        $html = <<<HTML
        <div style="font-family:Inter,sans-serif;max-width:600px;margin:0 auto;background:#0d1528;color:#e5e7eb;border-radius:12px;overflow:hidden">
          <div style="background:linear-gradient(135deg,#f5c300,#ffd740);padding:24px 32px">
            <h1 style="margin:0;color:#0a0f1c;font-size:1.4rem">✈ New Travel Request — Evec Tours</h1>
          </div>
          <div style="padding:32px">
            <table style="width:100%;border-collapse:collapse">
              <tr><td style="padding:8px 0;color:#8fa3c0;width:140px;font-size:.9rem">Name</td><td style="padding:8px 0;font-weight:600">{$name}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Email</td><td style="padding:8px 0"><a href="mailto:{$email}" style="color:#4a9eff">{$email}</a></td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Phone</td><td style="padding:8px 0">{$phone}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Destination</td><td style="padding:8px 0;font-weight:700;color:#ffd740">{$destination}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Travel Type</td><td style="padding:8px 0">{$travelType}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Duration</td><td style="padding:8px 0">{$duration}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Budget</td><td style="padding:8px 0">{$budget}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.9rem">Travel Dates</td><td style="padding:8px 0">{$dates}</td></tr>
            </table>
            <div style="margin-top:24px;padding:16px;background:rgba(255,255,255,.05);border-left:3px solid #f5c300;border-radius:0 8px 8px 0">
              <p style="margin:0 0 6px;color:#8fa3c0;font-size:.85rem;text-transform:uppercase;letter-spacing:.5px">Message</p>
              <p style="margin:0;line-height:1.7">{$message}</p>
            </div>
            <p style="margin-top:28px;font-size:.85rem;color:#8fa3c0">Reply directly to this email to respond to {$name}.</p>
          </div>
        </div>
        HTML;

        $this->resend->emails->send([
            'from'     => 'Evec Tours Contact <onboarding@resend.dev>',
            'to'       => ['contact@evectours.site'],
            'reply_to' => [$email],
            'subject'  => "Travel Request from {$name} — {$destination}",
            'html'     => $html,
        ]);
    }
}
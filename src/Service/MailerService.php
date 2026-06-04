<?php

namespace App\Service;

use Resend;

class MailerService
{
    private \Resend\Client $resend;
    private string $fromAddress;

    public function __construct(string $apiKey, string $fromAddress = 'onboarding@resend.dev')
    {
        $this->resend = Resend::client($apiKey);
        // Fall back to Resend's sandbox sender if MAIL_FROM is not configured.
        $this->fromAddress = $fromAddress !== '' ? $fromAddress : 'onboarding@resend.dev';
    }

    public function sendMailTo(string $mailAddress): void
    {
        $this->sendBookingConfirmation($mailAddress, [], []);
    }

    /** @param array<string,mixed> $reservation @param array<string,mixed> $voyage */
    public function sendBookingConfirmation(string $mailAddress, array $reservation, array $voyage): void
    {
        $voyageTitle = htmlspecialchars($voyage['title'] ?? 'Your Voyage', ENT_QUOTES);
        $destination = htmlspecialchars($voyage['destination'] ?? '', ENT_QUOTES);
        $startDate   = isset($voyage['start_date']) ? date('d M Y', strtotime($voyage['start_date'])) : 'TBD';
        $endDate     = isset($voyage['end_date'])   ? date('d M Y', strtotime($voyage['end_date']))   : 'TBD';
        $people      = (int) ($reservation['number_of_people'] ?? 1);
        $total       = number_format((float)($reservation['total_price'] ?? 0), 2) . ' TND';
        $refId       = '#' . ($reservation['id'] ?? 'N/A');

        $html = <<<HTML
        <div style="font-family:Inter,sans-serif;max-width:600px;margin:0 auto;background:#060d1e;color:#e5e7eb;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,.08)">
          <div style="background:linear-gradient(135deg,#0a1628,#1a3050);padding:32px;text-align:center;border-bottom:1px solid rgba(255,153,0,.2)">
            <p style="margin:0 0 4px;color:#f5c300;font-size:.8rem;font-weight:700;letter-spacing:2px;text-transform:uppercase">Booking Confirmed</p>
            <h1 style="margin:0;color:#fff;font-size:1.6rem;font-weight:900">✈️ You're all set!</h1>
          </div>
          <div style="padding:32px">
            <div style="background:rgba(255,153,0,.07);border:1px solid rgba(255,153,0,.2);border-radius:12px;padding:20px 24px;margin-bottom:24px">
              <p style="margin:0 0 4px;color:#f5c300;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px">Voyage</p>
              <h2 style="margin:0 0 8px;color:#fff;font-size:1.25rem">{$voyageTitle}</h2>
              <p style="margin:0;color:#94a3b8;font-size:.9rem">📍 {$destination}</p>
            </div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:24px">
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.88rem;width:120px">Booking Ref</td><td style="padding:8px 0;font-weight:700;color:#fff">{$refId}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.88rem">Dates</td><td style="padding:8px 0;color:#fff">{$startDate} → {$endDate}</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.88rem">Travellers</td><td style="padding:8px 0;color:#fff">{$people} person(s)</td></tr>
              <tr><td style="padding:8px 0;color:#8fa3c0;font-size:.88rem">Total Paid</td><td style="padding:8px 0;font-weight:800;font-size:1.1rem;color:#f5c300">{$total}</td></tr>
            </table>
            <div style="text-align:center;margin-top:28px">
              <a href="https://evectours.com/account" style="display:inline-block;background:linear-gradient(135deg,#f5c300,#ffd740);color:#0a0f1c;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:800;font-size:1rem">View My Booking →</a>
            </div>
          </div>
          <div style="padding:20px 32px;background:rgba(0,0,0,.2);text-align:center;font-size:.8rem;color:#64748b">
            <p style="margin:0">© 2026 Evec Tours · <a href="https://evectours.com/contact" style="color:#64748b">Contact Support</a></p>
          </div>
        </div>
HTML;

        $this->resend->emails->send([
            'from'    => 'Evec Tours <' . $this->fromAddress . '>',
            'to'      => [$mailAddress],
            'subject' => '✈️ Booking Confirmed — ' . ($voyage['title'] ?? 'Your Voyage'),
            'html'    => $html,
        ]);
    }

    public function sendPasswordReset(string $mailAddress, string $resetUrl): void
    {
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES);

        $html = <<<HTML
        <div style="font-family:Inter,sans-serif;max-width:600px;margin:0 auto;background:#060d1e;color:#e5e7eb;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,.08)">
          <div style="background:linear-gradient(135deg,#0a1628,#1a3050);padding:32px;text-align:center;border-bottom:1px solid rgba(255,153,0,.2)">
            <p style="margin:0 0 4px;color:#f5c300;font-size:.8rem;font-weight:700;letter-spacing:2px;text-transform:uppercase">Password Reset</p>
            <h1 style="margin:0;color:#fff;font-size:1.6rem;font-weight:900">🔒 Reset your password</h1>
          </div>
          <div style="padding:32px">
            <p style="margin:0 0 20px;color:#cbd5e1;line-height:1.7">We received a request to reset your Evec Tours password. Click the button below to choose a new one. This link expires in 1 hour.</p>
            <div style="text-align:center;margin:28px 0">
              <a href="{$safeUrl}" style="display:inline-block;background:linear-gradient(135deg,#f5c300,#ffd740);color:#0a0f1c;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:800;font-size:1rem">Reset Password →</a>
            </div>
            <p style="margin:0;color:#64748b;font-size:.82rem;line-height:1.6">If the button doesn't work, copy and paste this link into your browser:<br><span style="color:#8fa3c0;word-break:break-all">{$safeUrl}</span></p>
            <p style="margin:20px 0 0;color:#64748b;font-size:.82rem">If you didn't request this, you can safely ignore this email — your password won't change.</p>
          </div>
          <div style="padding:20px 32px;background:rgba(0,0,0,.2);text-align:center;font-size:.8rem;color:#64748b">
            <p style="margin:0">© 2026 Evec Tours · <a href="https://evectours.com/contact" style="color:#64748b">Contact Support</a></p>
          </div>
        </div>
HTML;

        $this->resend->emails->send([
            'from'    => 'Evec Tours <' . $this->fromAddress . '>',
            'to'      => [$mailAddress],
            'subject' => '🔒 Reset your Evec Tours password',
            'html'    => $html,
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
            'from'     => 'Evec Tours Contact <' . $this->fromAddress . '>',
            'to'       => ['contact@evectours.com'],
            'reply_to' => [$email],
            'subject'  => "Travel Request from {$name} — {$destination}",
            'html'     => $html,
        ]);
    }
}
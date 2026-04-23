<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Twilio\Rest\Client;

class TwilioSmsService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $messagingServiceSid,
    ) {}

    public function isConfigured(): bool
    {
        return $this->accountSid !== ''
            && $this->authToken !== ''
            && $this->messagingServiceSid !== '';
    }

    public function send(string $toNumber, string $message): bool
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('Twilio SMS skipped: missing configuration.');
            return false;
        }

        if (trim($toNumber) === '') {
            $this->logger->warning('Twilio SMS skipped: recipient phone number is empty.');
            return false;
        }

        try {
            $client = new Client($this->accountSid, $this->authToken);
            $client->messages->create($toNumber, [
                'messagingServiceSid' => $this->messagingServiceSid,
                'body'                => $message,
            ]);

            $this->logger->info('Twilio SMS sent successfully', ['to' => $toNumber]);
            return true;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Twilio SMS', [
                'error' => $e->getMessage(),
                'to'    => $toNumber,
            ]);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Refund helpers
    // -------------------------------------------------------------------------

    public function sendRefundApproved(string $phone, string $username, float $amount): bool
    {
        return $this->send($phone, sprintf(
            'Hello %s, your refund of %.2f TND has been APPROVED. It will be processed in 3-5 days. – TravelAgency',
            $username,
            $amount
        ));
    }

    public function sendRefundRejected(string $phone, string $username): bool
    {
        return $this->send($phone, sprintf(
            'Hello %s, your refund request has been REJECTED. Contact support for more info. – TravelAgency',
            $username
        ));
    }

    // -------------------------------------------------------------------------
    // Reclamation helpers
    // -------------------------------------------------------------------------

    public function sendReclamationStatusChanged(string $phone, string $username, string $status): bool
    {
        $friendly = match (strtoupper($status)) {
            'IN_PROGRESS' => 'is now being reviewed',
            'RESOLVED'    => 'has been resolved',
            'CLOSED'      => 'has been closed',
            default       => 'has been updated to ' . $status,
        };

        return $this->send($phone, sprintf(
            'Hello %s, your reclamation %s. Log in to see details. – TravelAgency',
            $username,
            $friendly
        ));
    }

    public function sendReclamationResponse(string $phone, string $username): bool
    {
        return $this->send($phone, sprintf(
            'Hello %s, an admin has responded to your reclamation. Log in to read it. – TravelAgency',
            $username
        ));
    }

    // -------------------------------------------------------------------------
    // Waitlist helper
    // -------------------------------------------------------------------------

    public function sendWaitlistSpotAvailable(string $phone, string $username, string $voyageTitle): bool
    {
        return $this->send($phone, sprintf(
            'Good news, %s! A spot just opened up for "%s". Log in now to secure your reservation before it\'s gone! – TravelAgency',
            $username,
            $voyageTitle
        ));
    }

    // -------------------------------------------------------------------------
    // Loyalty points helper
    // -------------------------------------------------------------------------

    public function sendLoyaltyPointsEarned(string $phone, string $username, int $points, int $balance): bool
    {
        return $this->send($phone, sprintf(
            'Hi %s! You earned %d loyalty points for your reservation. Total balance: %d pts. Reach 100 pts for a 5%% discount! – TravelAgency',
            $username,
            $points,
            $balance
        ));
    }
}
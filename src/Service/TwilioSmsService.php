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
        private readonly string $fromNumber,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->accountSid !== '' && $this->authToken !== '' && $this->fromNumber !== '';
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
                'from' => $this->fromNumber,
                'body' => $message,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Twilio SMS', ['error' => $e->getMessage(), 'to' => $toNumber]);
            return false;
        }
    }
}
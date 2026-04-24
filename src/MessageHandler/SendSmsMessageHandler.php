<?php

namespace App\MessageHandler;

use App\Message\SendSmsMessage;
use App\Service\TwilioSmsService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendSmsMessageHandler
{
    public function __construct(private readonly TwilioSmsService $twilio) {}

    public function __invoke(SendSmsMessage $message): void
    {
        $this->twilio->send($message->to, $message->body);
    }
}

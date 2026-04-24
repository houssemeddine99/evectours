<?php

namespace App\Message;

final class SendSmsMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $body,
    ) {}
}

<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class CommunicationSendResult
{
    private const STATUSES = ['queued', 'accepted', 'delivered', 'failed'];

    public function __construct(
        public string $status,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported communication delivery status.');
        }
    }
}

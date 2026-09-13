<?php

namespace App\Modules\Notifications\DTOs;

final readonly class PushSubscriptionData
{
    public function __construct(
        public string $endpoint,
        public string $p256dhKey,
        public string $authKey,
        public ?string $userAgent = null,
    ) {}
}

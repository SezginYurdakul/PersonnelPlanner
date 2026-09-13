<?php

namespace App\Modules\Auth\DTOs;

final readonly class InviteUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?int $employeeId = null,
    ) {}
}

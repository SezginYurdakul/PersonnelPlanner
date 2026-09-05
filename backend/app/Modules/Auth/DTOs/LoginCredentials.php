<?php

namespace App\Modules\Auth\DTOs;

final readonly class LoginCredentials
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}

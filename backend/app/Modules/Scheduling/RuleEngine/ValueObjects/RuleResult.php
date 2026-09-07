<?php

namespace App\Modules\Scheduling\RuleEngine\ValueObjects;

final readonly class RuleResult
{
    public const STATUS_PASS = 'pass';

    public const STATUS_VIOLATION = 'violation';

    public function __construct(
        public string $ruleKey,
        public string $status,
        public ?string $message = null,
    ) {
    }

    public static function pass(string $ruleKey): self
    {
        return new self($ruleKey, self::STATUS_PASS);
    }

    public static function violation(string $ruleKey, string $message): self
    {
        return new self($ruleKey, self::STATUS_VIOLATION, $message);
    }

    public function isViolation(): bool
    {
        return $this->status === self::STATUS_VIOLATION;
    }
}

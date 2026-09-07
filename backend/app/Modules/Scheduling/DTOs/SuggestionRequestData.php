<?php

namespace App\Modules\Scheduling\DTOs;

final readonly class SuggestionRequestData
{
    public const RANKING_FAIR = 'fair';

    public const RANKING_COST = 'cost';

    /**
     * @param  array<int, int>  $lineIds
     * @param  array<int, int>  $shiftPatternIds
     * @param  ?array<int, int>  $roleIds  null means "all roles"
     */
    public function __construct(
        public string $weekStartDate,
        public array $lineIds,
        public array $shiftPatternIds,
        public ?array $roleIds,
        public string $rankingMode,
        public int $requestedBy,
    ) {
    }
}

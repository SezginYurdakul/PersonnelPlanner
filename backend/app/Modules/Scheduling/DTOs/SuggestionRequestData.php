<?php

namespace App\Modules\Scheduling\DTOs;

final readonly class SuggestionRequestData
{
    public const RANKING_FAIR = 'fair';

    public const RANKING_COST = 'cost';

    /**
     * @param  array<int, int>  $lineIds
     * @param  array<int, string>  $slotTypes  e.g. ['day', 'afternoon', 'night'] - the
     *                                         concrete ShiftPattern used for each is resolved per-line from that line's
     *                                         shift_pattern_group, since the same slot type has different hours on different lines.
     * @param  ?array<int, int>  $roleIds  null means "all roles"
     */
    public function __construct(
        public string $weekStartDate,
        public array $lineIds,
        public array $slotTypes,
        public ?array $roleIds,
        public string $rankingMode,
        public int $requestedBy,
        // Which draft to (re)generate into - since a week may now have more than one
        // draft/proposed Schedule (multiple scenarios), omitting this always starts a new
        // one rather than guessing which existing draft was meant.
        public ?int $scheduleId = null,
    ) {}
}

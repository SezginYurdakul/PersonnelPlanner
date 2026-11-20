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
     * @param  ?array<int, array{line_id: int, day_of_week: int, slot_type: string}>  $lineSlotExclusions
     *                                                                                                     Each entry means "do NOT generate this line on this ISO day-of-week (1=Monday..7=Sunday)
     *                                                                                                     for this slot_type", scoped to this one generate run only - never persisted. Null/empty
     *                                                                                                     means no exclusions (every selected line gets every selected slot_type, every day).
     * @param  ?array<int, array{role_id: int, day_of_week: int, slot_type: ?string}>  $roleSlotExclusions
     *                                                                                                      the same for one station or task on a shift its line runs - a null slot_type
     *                                                                                                      leaves it out the whole day (a flexible-hours role has no slot type).
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
        public ?array $lineSlotExclusions = null,
        public ?array $roleSlotExclusions = null,
    ) {}
}

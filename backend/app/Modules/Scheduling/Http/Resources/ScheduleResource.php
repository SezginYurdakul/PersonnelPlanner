<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Services\MandatoryCoverageChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Schedule */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week_start_date' => $this->week_start_date?->toDateString(),
            'label' => $this->label,
            'note' => $this->note,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'generated_scope' => $this->generated_scope,
            'was_approved_before' => $this->was_approved_before,
            'version' => $this->version,
            'rejection_note' => $this->rejection_note,
            'unfilled_slots' => $this->reconciledUnfilledSlots($request),
            'assignments' => ShiftAssignmentResource::collection($this->whenLoaded('assignments')),
        ];
    }

    /**
     * `unfilled_slots` used to be read straight from the last "Generate Suggestion" run's
     * frozen snapshot (still the column's origin - see the migration adding it), which goes
     * stale the moment an admin manually edits an assignment afterward, in two directions:
     *
     * - A slot someone just staffed by hand kept showing its old "Unfilled"/"Open" badge
     *   (which stayed clickable and let a second person double-book the same slot).
     * - The opposite and more serious case: removing the only assignment for a role turned it
     *   back into unfilled, but since it was never in the generate()-time snapshot to begin
     *   with (it was filled when generate() ran), no reconciliation of that snapshot could
     *   ever surface it again - the slot just vanished from the grid instead of reappearing
     *   as "Unfilled"/"Open". For a mandatory (requires_coverage) role this was the more
     *   visible half of that bug, since `ScheduleService::approve()` still correctly blocked
     *   approval via `MandatoryCoverageChecker` while the grid stayed silent about why.
     *
     * Both blocking and advisory entries are now recomputed live via `MandatoryCoverageChecker`
     * every time this resource is rendered (the exact same check approve() itself runs for
     * the blocking half), instead of ever trusting the stored snapshot - so the grid can
     * never disagree with the approval gate, and a slot that becomes unfilled after the fact
     * (by deleting OR by any other means) is always shown correctly.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reconciledUnfilledSlots(Request $request): array
    {
        $checker = app(MandatoryCoverageChecker::class);

        return [
            ...UnfilledSlotResource::collection($checker->unresolvedBlockingSlots($this->resource))->resolve($request),
            ...UnfilledSlotResource::collection($checker->unresolvedAdvisorySlots($this->resource))->resolve($request),
        ];
    }
}

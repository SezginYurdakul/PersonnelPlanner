<?php

namespace App\Modules\Scheduling\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Status of each queued generate (ProjectPlan.md §28.11), kept in the cache for the page
 * that started it to poll: the planner takes minutes, longer than a proxied request may stay
 * open.
 */
class ScheduleSuggestionRuns
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    private const TTL_SECONDS = 3600;

    public function create(): string
    {
        $id = (string) Str::uuid();
        $this->put($id, ['status' => self::STATUS_QUEUED, 'schedule_id' => null, 'message' => null]);

        return $id;
    }

    public function markRunning(string $id): void
    {
        $this->update($id, ['status' => self::STATUS_RUNNING]);
    }

    public function markCompleted(string $id, int $scheduleId): void
    {
        $this->update($id, ['status' => self::STATUS_COMPLETED, 'schedule_id' => $scheduleId]);
    }

    public function markFailed(string $id, string $message): void
    {
        $this->update($id, ['status' => self::STATUS_FAILED, 'message' => $message]);
    }

    /**
     * @return ?array{status: string, schedule_id: ?int, message: ?string}
     */
    public function find(string $id): ?array
    {
        return Cache::get($this->key($id));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(string $id, array $changes): void
    {
        $run = $this->find($id);

        if ($run !== null) {
            $this->put($id, [...$run, ...$changes]);
        }
    }

    /**
     * @param  array{status: string, schedule_id: ?int, message: ?string}  $run
     */
    private function put(string $id, array $run): void
    {
        Cache::put($this->key($id), $run, self::TTL_SECONDS);
    }

    private function key(string $id): string
    {
        return "schedule-suggestion-run:{$id}";
    }
}

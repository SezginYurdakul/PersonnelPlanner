<?php

namespace App\Modules\ShiftNotices\Contracts;

use App\Models\User;
use App\Modules\ShiftNotices\DTOs\ShiftNoticeData;
use App\Modules\ShiftNotices\Models\ShiftNotice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ShiftNoticeServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ShiftNotice>
     */
    public function list(array $filters): LengthAwarePaginator;

    public function create(ShiftNoticeData $data): ShiftNotice;

    public function acknowledge(ShiftNotice $notice, User $acknowledger): ShiftNotice;
}

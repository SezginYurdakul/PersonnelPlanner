<?php

namespace App\Modules\ShiftNotices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ShiftNotices\Contracts\ShiftNoticeServiceContract;
use App\Modules\ShiftNotices\Http\Resources\ShiftNoticeResource;
use App\Modules\ShiftNotices\Models\ShiftNotice;
use Illuminate\Http\Request;

class ShiftNoticeController extends Controller
{
    public function __construct(private readonly ShiftNoticeServiceContract $notices)
    {
    }

    public function index(Request $request)
    {
        $notices = $this->notices->list($request->only(['status', 'employee_id', 'type']));

        return ShiftNoticeResource::collection($notices);
    }

    public function acknowledge(Request $request, ShiftNotice $shiftNotice): ShiftNoticeResource
    {
        $notice = $this->notices->acknowledge($shiftNotice, $request->user());
        $notice->load(['employee', 'shiftAssignment.line', 'shiftAssignment.shiftPattern']);

        return new ShiftNoticeResource($notice);
    }
}

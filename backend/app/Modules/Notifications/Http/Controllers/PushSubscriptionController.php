<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Contracts\PushSubscriptionServiceContract;
use App\Modules\Notifications\Http\Requests\StorePushSubscriptionRequest;
use App\Modules\Notifications\Http\Resources\PushSubscriptionResource;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Staff\Support\ResolvesAuthenticatedEmployee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    use ResolvesAuthenticatedEmployee;

    public function __construct(private readonly PushSubscriptionServiceContract $subscriptions) {}

    public function store(StorePushSubscriptionRequest $request): PushSubscriptionResource
    {
        $employee = $this->employeeForUser($request->user());

        return new PushSubscriptionResource($this->subscriptions->register($employee, $request->toDto()));
    }

    public function destroy(Request $request, PushSubscription $pushSubscription): Response
    {
        $employee = $this->employeeForUser($request->user());

        abort_unless($pushSubscription->employee_id === $employee->id, 403);

        $this->subscriptions->deactivate($pushSubscription);

        return response()->noContent();
    }
}

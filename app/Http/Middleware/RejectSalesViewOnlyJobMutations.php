<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales / Delivery may only POST to jobs.deliver; all other job mutations are blocked.
 */
class RejectSalesViewOnlyJobMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isCounterStaffViewOnly()) {
            $message = $user->isDeliveryViewOnly()
                ? 'Delivery staff can only open completed jobs and mark them as delivered.'
                : 'Sales can view job status only. Mark delivery when the job is completed.';

            return redirect()->back()->with('error', $message);
        }

        return $next($request);
    }
}

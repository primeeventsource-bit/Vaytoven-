<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Services\Chargeback\ChargebackCertificateService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserCertificateController extends Controller
{
    public function __construct(private readonly ChargebackCertificateService $certificates)
    {
    }

    /**
     * GET /admin/users/{user}/login-history — admin-only view of any user's
     * full auth history with anomaly flags. Returns JSON for the SPA.
     */
    public function loginHistory(Request $request, User $user)
    {
        AdminAuditLogService::log(
            actor: $request->user(),
            action: 'user.login_history.viewed',
            subject: $user,
            ipAddress: $request->ip(),
        );

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'sessions' => $user->loginSessions()
                ->orderByDesc('occurred_at')
                ->limit(200)
                ->get(),
        ]);
    }

    /**
     * GET /admin/users/{user}/certificate.pdf — Service Usage Confirmation
     * Certificate (FR-10.13). Optional ?from=YYYY-MM-DD&to=YYYY-MM-DD window;
     * defaults to last 90 days.
     */
    public function certificate(Request $request, User $user): Response
    {
        $from = CarbonImmutable::parse($request->query('from', CarbonImmutable::now()->subDays(90)->toDateString()));
        $to = CarbonImmutable::parse($request->query('to', CarbonImmutable::now()->toDateString()));

        $pdf = $this->certificates->forUser($user, $from, $to);

        AdminAuditLogService::log(
            actor: $request->user(),
            action: 'user.certificate.downloaded',
            subject: $user,
            payload: ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ipAddress: $request->ip(),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->certificates->filenameFor(userId: $user->id).'"',
        ]);
    }
}

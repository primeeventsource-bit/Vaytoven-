<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AdminAuditLogResource;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminAuditLogController
{
    /**
     * GET /api/v1/admin/audit-logs
     * Filters: actor (admin_user_id), action (exact or prefix with trailing dot),
     *          target_type, target_id, target_uuid, from, to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AdminAuditLog::query()->with('admin');

        if ($request->filled('actor')) {
            $query->byAdmin($request->integer('actor'));
        }

        if ($action = $request->string('action')->toString()) {
            $query->action($action);
        }

        if ($type = $request->string('target_type')->toString()) {
            $query->where('target_type', $type);
            if ($request->filled('target_id'))   $query->where('target_id',   $request->integer('target_id'));
            if ($request->filled('target_uuid')) $query->where('target_uuid', $request->string('target_uuid')->toString());
        }

        if ($request->filled('from')) $query->where('created_at', '>=', $request->date('from'));
        if ($request->filled('to'))   $query->where('created_at', '<=', $request->date('to')->endOfDay());

        $query->orderByDesc('created_at');

        return AdminAuditLogResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * Convenience endpoint: full audit trail for one members enquiry.
     * GET /api/v1/admin/members-enquiries/{id}/audit
     */
    public function forMembersEnquiry(string $id): AnonymousResourceCollection
    {
        $logs = AdminAuditLog::query()
            ->with('admin')
            ->forTarget('members_enquiries', $id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return AdminAuditLogResource::collection($logs);
    }
}

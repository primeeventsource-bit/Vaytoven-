<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreMembersEnquiryRequest;
use App\Http\Requests\UpdateMembersEnquiryRequest;
use App\Http\Resources\MembersEnquiryResource;
use App\Models\MembersEnquiry;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Services\MembersEnquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MembersEnquiryController
{
    public function __construct(
        private readonly MembersEnquiryService $service,
    ) {}

    // ─── Public ───────────────────────────────────────────────────

    /**
     * POST /api/v1/members-enquiries
     * Public endpoint hit by the website + app modal.
     * Throttled at the route layer (FR-9.9: 10 / IP / hour).
     */
    public function store(StoreMembersEnquiryRequest $request): JsonResponse
    {
        $enquiry = $this->service->ingest($request->validated(), $request);

        // Don't echo back PII to a public endpoint; just confirm receipt.
        return response()->json([
            'message' => 'Thanks — we got it. A member specialist will reach out within one business day.',
            'id'      => $enquiry->id,
        ], 201);
    }

    // ─── Admin ────────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/members-enquiries
     * Lists enquiries with filters: status, assigned_to, program, source, q (search), date range.
     * Default view excludes flagged spam (FR-9.9).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MembersEnquiry::query()
            ->with(['assignee', 'convertedProperty']);

        // Default: hide flagged unless explicitly included
        if (! $request->boolean('include_flagged')) {
            $query->unflagged();
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }
        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_to');
        }

        if ($program = $request->string('program')->toString()) {
            $query->where('program', $program);
        }

        if ($source = $request->string('source')->toString()) {
            $query->where('source', $source);
        }

        // Free-text search across name, email, phone, property
        if ($q = trim($request->string('q')->toString())) {
            $query->where(function ($w) use ($q) {
                $w->where('first_name', 'ilike', "%$q%")
                    ->orWhere('last_name', 'ilike', "%$q%")
                    ->orWhere('email', 'ilike', "%$q%")
                    ->orWhere('phone', 'ilike', "%$q%")
                    ->orWhere('property', 'ilike', "%$q%");
            });
        }

        // Date range on created_at
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        // Sorting
        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        if (! in_array($field, ['created_at', 'updated_at', 'status', 'program'], true)) {
            $field = 'created_at';
        }
        $query->orderBy($field, $direction);

        return MembersEnquiryResource::collection(
            $query->paginate($request->integer('per_page', 25))
        );
    }

    /**
     * GET /api/v1/admin/members-enquiries/stats
     * Aggregate counts for the queue header (open, by status, by source, recent volume).
     */
    public function stats(): JsonResponse
    {
        $base = MembersEnquiry::query()->unflagged();

        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $bySource = (clone $base)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        $last7Days = (clone $base)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $last24Hours = (clone $base)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $unassigned = (clone $base)
            ->whereNull('assigned_to')
            ->whereIn('status', ['new', 'contacted'])
            ->count();

        $flagged = MembersEnquiry::where('flagged', true)->whereNull('deleted_at')->count();

        return response()->json([
            'by_status'    => $byStatus,
            'by_source'    => $bySource,
            'last_24h'     => $last24Hours,
            'last_7d'      => $last7Days,
            'unassigned'   => $unassigned,
            'flagged'      => $flagged,
        ]);
    }

    /**
     * GET /api/v1/admin/members-enquiries/{id}
     */
    public function show(MembersEnquiry $enquiry): MembersEnquiryResource
    {
        $enquiry->load(['assignee', 'convertedProperty']);
        return new MembersEnquiryResource($enquiry);
    }

    /**
     * PATCH /api/v1/admin/members-enquiries/{id}
     * Partial update — status, assignment, flagging, internal notes.
     * Every change is recorded in admin_audit_logs (FR-9.7).
     */
    public function update(UpdateMembersEnquiryRequest $request, MembersEnquiry $enquiry): MembersEnquiryResource
    {
        $data = $request->validated();
        $reason = $data['rejection_reason'] ?? null;
        $before = $enquiry->getOriginal();

        // Status transitions go through model helpers so the timestamp columns fire correctly
        $statusAction = null;
        if (array_key_exists('status', $data) && $data['status'] !== $enquiry->status) {
            $newStatus = $data['status'];
            $statusAction = "members_enquiry.{$newStatus}";   // e.g. 'members_enquiry.qualified'
            match ($newStatus) {
                'contacted' => $enquiry->markContacted(),
                'qualified' => $enquiry->markQualified(),
                'rejected'  => $enquiry->markRejected($reason ?? 'No reason provided'),
                default     => $enquiry->update(['status' => $newStatus]),
            };
            unset($data['status'], $data['rejection_reason']);
        }

        // Other simple updates (assigned_to, flagged, notes…)
        if (! empty($data)) {
            $enquiry->update($data);
        }

        // Audit
        AdminAuditLogService::log(
            admin:   $request->user(),
            action:  $statusAction ?? 'members_enquiry.updated',
            target:  $enquiry,
            before:  $before,
            after:   $enquiry->fresh()->getAttributes(),
            request: $request,
            reason:  $reason,
        );

        $enquiry->load(['assignee', 'convertedProperty']);
        return new MembersEnquiryResource($enquiry);
    }

    /**
     * POST /api/v1/admin/members-enquiries/{id}/assign
     * Assign to the current admin (the typical "claim" action) or to a named user.
     */
    public function assign(Request $request, MembersEnquiry $enquiry): MembersEnquiryResource
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $assignee = $request->filled('user_id')
            ? User::findOrFail($request->integer('user_id'))
            : $request->user();

        $before = $enquiry->getOriginal();
        $this->service->assign($enquiry, $assignee);

        AdminAuditLogService::log(
            admin:   $request->user(),
            action:  'members_enquiry.assigned',
            target:  $enquiry,
            before:  $before,
            after:   $enquiry->fresh()->getAttributes(),
            request: $request,
            reason:  $assignee->id === $request->user()->id ? 'self-assigned' : "reassigned to {$assignee->display_name}",
        );

        $enquiry->load(['assignee', 'convertedProperty']);
        return new MembersEnquiryResource($enquiry);
    }

    /**
     * GET /api/v1/admin/members-enquiries/export
     * CSV export of the current filter set (FR-9.10, Phase 2; included now for completeness).
     */
    public function export(Request $request)
    {
        // Reuse the index() filtering by extracting it; for brevity, simple CSV stream.
        $query = MembersEnquiry::query()->unflagged();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $rows = $query->orderByDesc('created_at')->limit(5000)->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="members_enquiries_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Created', 'Status', 'Source',
                'First Name', 'Last Name', 'Email', 'Phone',
                'Program', 'Property', 'Annual Points', 'Best Time',
                'Notes', 'Assigned To',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->id,
                    $r->created_at->toIso8601String(),
                    $r->status,
                    $r->source,
                    $r->first_name,
                    $r->last_name,
                    $r->email,
                    $r->phone,
                    $r->program,
                    $r->property,
                    $r->annual_points,
                    $r->best_time_to_call,
                    $r->notes,
                    optional($r->assignee)->display_name,
                ]);
            }
            fclose($out);
        };

        AdminAuditLogService::log(
            admin:   $request->user(),
            action:  'members_enquiry.exported',
            request: $request,
            reason:  "exported {$rows->count()} rows" . ($status ? " filtered by status=$status" : ''),
        );

        return response()->streamDownload($callback, 'members_enquiries.csv', $headers);
    }
}

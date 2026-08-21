<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Property;
use App\Services\Listings\PublicPropertyRef;
use App\Models\User;
use App\Services\AdminAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin user management. All endpoints behind the `admin` middleware alias.
 *
 * Every state-changing action writes to admin_audit_logs via
 * AdminAuditLogService::log() — the rule from AGENTS.md.
 *
 * Deactivation is soft (sets deactivated_at + deactivated_by_user_id). We
 * never hard-delete user rows because chargeback / login / booking history
 * must survive the user.
 */
class UserController extends Controller
{
    /** Per-page cap for the user table. */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $status = $request->query('status', 'all'); // active | deactivated | all

        $users = User::query()
            ->when($q !== '', fn ($w) => $w->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($role, fn ($w) => $w->where('role', $role))
            ->when($status === 'active',      fn ($w) => $w->whereNull('deactivated_at'))
            ->when($status === 'deactivated', fn ($w) => $w->whereNotNull('deactivated_at'))
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = [
            'total'       => User::count(),
            'active'      => User::whereNull('deactivated_at')->count(),
            'deactivated' => User::whereNotNull('deactivated_at')->count(),
        ];

        return view('admin.users.index', [
            'users'   => $users,
            'q'       => $q,
            'role'    => $role,
            'status'  => $status,
            'roles'   => UserRole::cases(),
            'counts'  => $counts,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.create', [
            'roles'      => UserRole::cases(),
            'canGrantAdmin' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $data  = $request->validated();

        $user = User::create([
            'first_name'          => $data['first_name'],
            'last_name'           => $data['last_name'] ?? null,
            // `name` is composed, never asked for. Plenty of the app still
            // reads it, and letting it drift from the parts would give one
            // person two different names depending on the screen.
            'name'                => User::composeName($data['first_name'], $data['last_name'] ?? null),
            'email'               => $data['email'],
            'password'            => $data['password'],   // hashed by cast
            'role'                => UserRole::from($data['role']),
            'email_verified_at'   => now(),
            'created_by_user_id'  => $actor->id,
            // Typed by staff. Blank for anyone who is not a member.
            'member_id'           => $data['member_id'] ?? null,
        ]);

        // A brand new account has no listings yet, but assigning here means
        // the rule lives in one place rather than only on the edit path.
        app(PublicPropertyRef::class)->assignFor($user);

        AdminAuditLogService::log(
            actor:   $actor,
            action:  'user.create',
            subject: $user,
            payload: ['email' => $user->email, 'role' => $user->role->value],
            ipAddress: $request->ip(),
        );

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Created {$user->email} as {$user->role->value}.");
    }

    public function show(User $user): View
    {
        return view('admin.users.show', ['user' => $user]);
    }

    public function edit(Request $request, User $user): View
    {
        // Block opening the edit form for a target the actor isn't allowed to touch.
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403);

        return view('admin.users.edit', [
            'user'           => $user,
            'roles'          => UserRole::cases(),
            'canGrantAdmin'  => $request->user()->isSuperAdmin(),
            'isSelf'         => $request->user()->id === $user->id,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $actor  = $request->user();
        $data   = $request->validated();
        $before = ['email' => $user->email, 'name' => $user->name, 'role' => $user->role->value];

        $user->first_name = $data['first_name'];
        $user->last_name  = $data['last_name'] ?? null;
        $user->name       = User::composeName($data['first_name'], $data['last_name'] ?? null);
        $user->email = $data['email'];
        $user->role  = UserRole::from($data['role']);
        $user->member_id = $data['member_id'] ?? null;
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        // Adding a member number to an existing account is what gives their
        // listings a readable address. Listings that already have one keep
        // it — renumbering would break a URL somebody has already been sent.
        $addressed = app(PublicPropertyRef::class)->assignFor($user);

        AdminAuditLogService::log(
            actor:    $actor,
            action:   'user.update',
            subject:  $user,
            payload:  ['before' => $before, 'after' => ['email' => $user->email, 'name' => $user->name, 'role' => $user->role->value]],
            ipAddress: $request->ip(),
        );

        return redirect()->route('admin.users.show', $user)
            ->with('success', $addressed > 0
                ? "Updated {$user->email}. {$addressed} listing(s) now published under member ID {$user->member_id}."
                : "Updated {$user->email}.");
    }

    /**
     * Issue a temporary password so staff can get someone back into their
     * account.
     *
     * This is the supported answer to "a member is locked out and needs help".
     * The alternative — keeping members' real passwords readable so staff can
     * look one up — would mean storing them in a form that can be reversed,
     * which defeats hashing entirely: one database breach would hand an
     * attacker every member's actual password, and because people reuse
     * passwords, their email and bank logins with it.
     *
     * So nobody, including a super admin, can read an existing password. Staff
     * can REPLACE one, which is auditable, and the new password is single-use:
     * must_change_password forces the member to set their own on next sign-in,
     * so the staff-known credential stops working the moment it is used.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        $temporary = Str::password(14, symbols: false);

        $user->forceFill([
            'password'             => $temporary,   // hashed by cast
            'must_change_password' => true,
            'password_changed_at'  => null,
        ])->save();

        // Kill every existing session on the account. A lockout is often a
        // "someone else has my account" report, and leaving their session live
        // would make the reset pointless.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AdminAuditLogService::log(
            actor:    $actor,
            action:   'user.reset_password',
            subject:  $user,
            // The password itself is never logged. Recording it here would
            // recreate the readable-password problem in the audit trail.
            payload:  ['email' => $user->email],
            ipAddress: $request->ip(),
        );

        return back()->with('temporary_password', [
            'email'    => $user->email,
            'password' => $temporary,
        ])->with('success', "Temporary password issued for {$user->email}. It is shown once — copy it now.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // Belt-and-braces: cannot deactivate yourself.
        abort_if($actor->id === $user->id, 422, 'You cannot deactivate your own account.');
        // Cannot deactivate super_admin unless you are super_admin yourself.
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
        // No-op if already deactivated.
        abort_if($user->deactivated_at !== null, 422, 'User is already deactivated.');

        $user->forceFill([
            'deactivated_at'         => now(),
            'deactivated_by_user_id' => $actor->id,
        ])->save();

        AdminAuditLogService::log(
            actor:    $actor,
            action:   'user.deactivate',
            subject:  $user,
            payload:  ['email' => $user->email, 'reason' => $request->string('reason')->toString() ?: null],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Deactivated {$user->email}. Their bookings and history are preserved.");
    }

    /**
     * Permanently remove an account.
     *
     * Distinct from deactivation, which is the right answer almost every time:
     * it revokes access, is reversible, and keeps the record. This is for the
     * cases deactivation does not cover — a duplicate account, a test account,
     * a signup made in error, someone exercising a deletion request.
     *
     * It refuses when the account carries records the business would need
     * later. A member who paid for advertising and disputes the charge is
     * answered from their order, the terms version they accepted, the contract
     * they signed and the sign-in history behind it; deleting the account
     * removes the thread those hang from, and the first time anybody notices is
     * the moment it is needed and gone. Those accounts deactivate instead, and
     * the refusal says which records are holding it.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        // Same guards as deactivation, for the same reasons: locking yourself
        // out is worse when it is irreversible, and a lower-privileged admin
        // must not be able to remove a super admin.
        abort_if($actor->id === $user->id, 422, 'You cannot delete your own account.');
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        if ($blockers = $this->deletionBlockers($user)) {
            return back()->withErrors([
                'delete' => 'This account cannot be deleted because it holds records the business may need: '
                    .implode(', ', $blockers)
                    .'. Deactivate it instead — that revokes access immediately and keeps the record.',
            ]);
        }

        // Logged BEFORE the delete, so the record of what was removed outlives
        // the thing it describes.
        AdminAuditLogService::log(
            actor:     $actor,
            action:    'user.deleted',
            subject:   $user,
            payload:   [
                'user_id' => $user->id,
                'email'   => $user->email,
                'role'    => $user->role?->value,
                'reason'  => $request->string('reason')->toString() ?: null,
            ],
            ipAddress: $request->ip(),
        );

        $email = $user->email;

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Deleted {$email}.");
    }

    /**
     * What is standing in the way of deleting this account.
     *
     * Named individually rather than as one refusal, because "you cannot delete
     * this" with no reason invites someone to go looking for a way around it.
     *
     * Member Services orders match on email rather than user id: activation is
     * a public flow that does not require an account, so the order and the
     * person are linked by the address they typed.
     *
     * @return array<int, string>
     */
    private function deletionBlockers(User $user): array
    {
        $blockers = [];

        $orders = \App\Models\MemberServiceOrder::where('email', $user->email)->count();
        if ($orders) {
            $blockers[] = "{$orders} Member Services order(s)";
        }

        $contracts = \App\Models\Contract::where('user_id', $user->id)->count();
        if ($contracts) {
            $blockers[] = "{$contracts} contract(s)";
        }

        $acceptances = \App\Models\TermsAcceptance::where('user_id', $user->id)->count();
        if ($acceptances) {
            $blockers[] = "{$acceptances} terms acceptance(s)";
        }

        $properties = Property::where('host_id', $user->id)->count();
        if ($properties) {
            $blockers[] = "{$properties} listing(s) still attached";
        }

        return $blockers;
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
        abort_if($user->deactivated_at === null, 422, 'User is already active.');

        $user->forceFill([
            'deactivated_at'         => null,
            'deactivated_by_user_id' => null,
        ])->save();

        AdminAuditLogService::log(
            actor:     $actor,
            action:    'user.reactivate',
            subject:   $user,
            payload:   ['email' => $user->email],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Reactivated {$user->email}.");
    }
}

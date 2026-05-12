# Vaytoven Test Logins

For local development and demos. **Not for production.** Universal password: `Vaytoven$2026`

| Role | Email | Notes |
|---|---|---|
| **Master Admin** | admin@demo.vaytoven.local | Full operations console; `role = super_admin` |
| **Member Specialist** | specialist@demo.vaytoven.local | Members Enquiries queue + support tickets. **NOT a global admin** — `isAdmin()` returns false. |
| **Host** | host@demo.vaytoven.local | Three active listings: Uluwatu, Lake Tahoe, Paris. |
| **Member** (points-program owner) | member@demo.vaytoven.local | A Marriott Vacation Club enquiry sits in the specialist's queue under this email. |
| **New Client** | newclient@demo.vaytoven.local | Empty Trips page — for demoing the first-booking flow. |
| **Returning Guest** | guest@demo.vaytoven.local | 90 days of `login_sessions` from Trenton NJ + Orlando FL, with one San Juan PR login flagged `new_country`. |

## How to seed

```powershell
php artisan db:seed --class=DemoUsersSeeder
```

The seeder is **not** wired into [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) — you call it explicitly. It refuses to run when `APP_ENV=production`, and it's keyed on email via `updateOrCreate`, so re-running is a no-op.

## Schema reality (what this repo actually has)

Important — the conventions differ from the original draft of this doc that floated around:

- The `users` table has a single `name` column. No `first_name`, `last_name`, `display_name`, or `phone`. If demo flows need a phone number, that's a separate migration.
- Role lives in **one `role` enum column** (varchar(32)), not in an M2M `roles`+`user_roles` pair. Valid values come from [app/Enums/UserRole.php](../app/Enums/UserRole.php): `traveler`, `host`, `member`, `member_specialist`, `admin`, `super_admin`.
- The password column is `password` (Laravel default). The model casts it via `'password' => 'hashed'` so the seeder passes plaintext and the cast bcrypts on save.
- `member_specialist_assignments` is a separate table for routing specialists to specific enquiries — it does **not** define role membership.

## What the seeder creates beyond the user rows

- **Maya's listings** — three rows in `properties` with `status='active'`, tied to her `host_id`: Cliffside Villa Uluwatu, Modern Cabin Lake Tahoe, Historic Pied-à-Terre Paris.
- **Margaret's enquiry** — one row in `members_enquiries`, `status='new'`, with a `VYT-` reference code. Club: Marriott Vacation Club. Property: Marriott Grande Vista. Lands in the specialist's queue immediately.
- **Daniel's login history** — ~16 rows in `login_sessions` over 90 days. Mostly desktop logins from Trenton NJ, a two-day Orlando FL mobile trip mid-window, and one San Juan PR login with `is_suspicious=true` and `suspicious_reasons=["new_country"]`. All IPs are RFC 5737 documentation ranges (`198.51.100.x`, `203.0.113.x`) — never real addresses.

What's still NOT seeded:
- Jordan (specialist) has no `member_specialist_assignments` rows yet — the assignment routing layer isn't built.
- No bookings on Maya's listings yet (would need a `bookings` row plus the payment-intent chain).

## Role gating

- `isAdmin()` returns true for `Admin` and `SuperAdmin` only.
- `isMemberSpecialist()` returns true for `MemberSpecialist` only.
- `EnsureAdmin` middleware (alias `admin`) gates strictly-admin routes.
- `EnsureAdminOrMemberSpecialist` middleware (alias `admin.or.specialist`) gates routes that specialists also need — use this on the Members Enquiries queue and support-ticket endpoints. Both aliases registered in [bootstrap/app.php](../bootstrap/app.php).

## Security

- The fake `.local` TLD means these addresses can never receive email and can never be confused with real customers. If one ever appears in production logs, something is wrong.
- Before MVP launch: `DELETE FROM users WHERE email LIKE '%@demo.vaytoven.local';`
- Don't paste these credentials into GitHub issues, Slack threads, or anywhere outside the internal team. They're demo-grade by design, not because rotation is easy.
- The universal password is deliberately obviously-demo. Don't reuse the pattern for any real account.

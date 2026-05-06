<?php

namespace Tests\Feature\Chargeback;

use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryAndCertificateRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function actAs(User $user): User
    {
        $token = $user->createToken('phpunit')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}");
        return $user;
    }

    public function test_me_login_history_returns_only_current_users_sessions(): void
    {
        $me = $this->actAs(User::factory()->create());
        $other = User::factory()->create();

        LoginSession::factory()->count(3)->create(['user_id' => $me->id]);
        LoginSession::factory()->count(5)->create(['user_id' => $other->id]);

        $resp = $this->getJson('/api/v1/me/login-history');

        $resp->assertOk();
        $this->assertCount(3, $resp->json('data'));
    }

    public function test_me_login_history_requires_auth(): void
    {
        $this->getJson('/api/v1/me/login-history')->assertStatus(401);
    }

    public function test_me_activity_map_clusters_nearby_points(): void
    {
        $me = $this->actAs(User::factory()->create());

        // 3 logins from approximately the same location → one cluster.
        LoginSession::factory()->count(3)->create([
            'user_id' => $me->id,
            'latitude' => 37.7749, 'longitude' => -122.4194,
        ]);
        // 1 login from London → distinct cluster.
        LoginSession::factory()->create([
            'user_id' => $me->id,
            'latitude' => 51.5074, 'longitude' => -0.1278,
        ]);

        $resp = $this->getJson('/api/v1/me/activity-map');

        $resp->assertOk();
        $points = $resp->json('points');
        $this->assertCount(2, $points);

        // The cluster with count=3 should be present.
        $counts = collect($points)->pluck('count')->sort()->values()->all();
        $this->assertSame([1, 3], $counts);
    }

    public function test_me_activity_map_omits_logins_without_lat_lng(): void
    {
        $me = $this->actAs(User::factory()->create());
        LoginSession::factory()->create([
            'user_id' => $me->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $resp = $this->getJson('/api/v1/me/activity-map');

        $resp->assertOk();
        $this->assertCount(0, $resp->json('points'));
    }

    public function test_admin_login_history_endpoint_requires_admin_role(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);
        $target = User::factory()->create();

        $this->actingAs($traveler)
            ->getJson("/admin/users/{$target->id}/login-history")
            ->assertForbidden();
    }

    public function test_admin_login_history_returns_target_users_sessions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $target = User::factory()->create();
        LoginSession::factory()->count(4)->create(['user_id' => $target->id]);

        $resp = $this->actingAs($admin)->getJson("/admin/users/{$target->id}/login-history");

        $resp->assertOk();
        $this->assertCount(4, $resp->json('sessions'));
        $this->assertSame($target->id, $resp->json('user.id'));

        // Audit log row written.
        $this->assertSame(
            1,
            AdminAuditLog::where('action', 'user.login_history.viewed')
                ->where('subject_id', $target->id)
                ->count()
        );
    }

    public function test_admin_certificate_pdf_download_records_audit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create();
        LoginSession::factory()->count(2)->create(['user_id' => $target->id]);

        $resp = $this->actingAs($admin)->get("/admin/users/{$target->id}/certificate.pdf");

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $resp->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $resp->getContent());

        // Audit row exists with action + payload window
        $audit = AdminAuditLog::where('action', 'user.certificate.downloaded')
            ->where('subject_id', $target->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertArrayHasKey('from', $audit->payload);
        $this->assertArrayHasKey('to', $audit->payload);
    }

    public function test_admin_certificate_respects_from_to_query_window(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create();

        $resp = $this->actingAs($admin)
            ->get("/admin/users/{$target->id}/certificate.pdf?from=2027-01-01&to=2027-03-31");

        $resp->assertOk();

        $audit = AdminAuditLog::where('action', 'user.certificate.downloaded')->first();
        $this->assertSame('2027-01-01', $audit->payload['from']);
        $this->assertSame('2027-03-31', $audit->payload['to']);
    }
}

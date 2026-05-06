<?php

namespace Tests\Feature;

use App\Models\LoginSession;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAndTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_session_records_geo_and_device_fields(): void
    {
        $user = User::factory()->create();
        $session = LoginSession::factory()->create([
            'user_id' => $user->id,
            'country' => 'CA',
            'city' => 'Toronto',
            'device_type' => 'mobile',
        ]);

        $this->assertSame('CA', $session->country);
        $this->assertSame('Toronto', $session->city);
        $this->assertSame('mobile', $session->device_type);
        $this->assertTrue($session->user->is($user));
    }

    public function test_suspicious_login_carries_reason_array(): void
    {
        $session = LoginSession::factory()->suspicious(['new_country', 'new_device'])->create();

        $this->assertTrue($session->is_suspicious);
        $this->assertSame(['new_country', 'new_device'], $session->suspicious_reasons);
    }

    public function test_terms_version_for_content_is_idempotent(): void
    {
        $content = 'Vaytoven Terms of Service v1: be cool, pay on time, no scams.';

        $first = TermsVersion::forContent('tos', $content, 'https://example/tos', 'v1');
        $second = TermsVersion::forContent('tos', $content, 'https://example/tos', 'v1');

        $this->assertSame($first->id, $second->id);
    }

    public function test_terms_version_for_content_creates_new_row_when_text_differs(): void
    {
        $a = TermsVersion::forContent('tos', 'original text', 'https://example/tos', 'v1');
        $b = TermsVersion::forContent('tos', 'amended text', 'https://example/tos', 'v2');

        $this->assertNotSame($a->id, $b->id);
        $this->assertNotSame($a->content_hash, $b->content_hash);
    }

    public function test_current_for_returns_most_recent_unsuperseded(): void
    {
        $old = TermsVersion::forContent('tos', 'old', 'https://example/tos', 'v1');
        $old->update(['effective_at' => now()->subDays(30), 'superseded_at' => now()]);
        $current = TermsVersion::forContent('tos', 'new', 'https://example/tos', 'v2');

        $this->assertSame($current->id, TermsVersion::currentFor('tos')?->id);
    }

    public function test_user_can_accept_terms_only_once_per_version(): void
    {
        $user = User::factory()->create();
        $version = TermsVersion::factory()->create();

        TermsAcceptance::create([
            'user_id' => $user->id,
            'terms_version_id' => $version->id,
            'accepted_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        TermsAcceptance::create([
            'user_id' => $user->id,
            'terms_version_id' => $version->id,
            'accepted_at' => now(),
        ]);
    }
}

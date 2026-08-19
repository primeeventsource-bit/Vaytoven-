<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The site displays Eastern time. It still STORES UTC.
 *
 * Switching config('app.timezone') would have been one line and wrong twice:
 * every existing row was written as UTC and would suddenly be read as Eastern,
 * moving history four hours later than it happened; and new rows would be
 * written in local time, leaving one column holding two conventions with
 * nothing to tell them apart.
 */
class DisplayTimezoneTest extends TestCase
{
    use RefreshDatabase;

    /** The guard against someone "simplifying" this later. */
    public function test_storage_is_still_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('America/New_York', config('app.display_timezone'));
    }

    public function test_a_utc_timestamp_displays_in_eastern(): void
    {
        $utc = Carbon::parse('2026-08-19 09:23:19', 'UTC');

        $this->assertSame('Aug 19, 2026 5:23am EDT', et($utc, 'M j, Y g:ia'));
    }

    /** EDT in summer, EST in winter — not one label all year. */
    public function test_it_follows_daylight_saving(): void
    {
        $summer = Carbon::parse('2026-08-19 09:00:00', 'UTC');
        $winter = Carbon::parse('2026-01-15 09:00:00', 'UTC');

        $this->assertStringContainsString('EDT', et($summer, 'g:ia'));
        $this->assertStringContainsString('EST', et($winter, 'g:ia'));
    }

    /** A date carries no zone label: "Aug 19, 2026 EDT" is noise. */
    public function test_a_date_only_format_gets_no_zone_suffix(): void
    {
        $this->assertSame('Aug 19, 2026', et(Carbon::parse('2026-08-19 09:23:19', 'UTC'), 'M j, Y'));
    }

    /**
     * A format like 'F j, Y \a\t H:i' contains an escaped "a" and "t" that are
     * text, not placeholders. Treating them as time markers would put a zone
     * on the wrong strings.
     */
    public function test_escaped_literals_are_not_mistaken_for_time_markers(): void
    {
        $out = et(Carbon::parse('2026-08-19 09:23:19', 'UTC'), 'F j, Y \a\t H:i');

        $this->assertStringContainsString('at', $out);
        $this->assertStringContainsString('EDT', $out, 'this format does have a time');
    }

    public function test_a_missing_date_renders_a_dash_rather_than_an_error(): void
    {
        $this->assertSame('—', et(null));
    }

    /** A date crossing midnight in Eastern must show the previous day. */
    public function test_a_late_utc_timestamp_shows_the_previous_eastern_day(): void
    {
        // 02:30 UTC on the 19th is 22:30 on the 18th in Eastern.
        $this->assertSame('Aug 18, 2026', et(Carbon::parse('2026-08-19 02:30:00', 'UTC'), 'M j, Y'));
    }

    public function test_the_activity_log_renders_eastern_times(): void
    {
        $user = \App\Models\User::factory()->create([
            'role' => \App\Enums\UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $user->roles()->sync([\App\Models\Role::where('key', 'super_admin')->firstOrFail()->id]);

        \App\Models\TrackingEvent::create([
            'event_type'  => \App\Enums\ActivityType::PropertyViewed->value,
            'surface'     => 'web',
            'occurred_at' => Carbon::parse('2026-08-19 09:23:19', 'UTC'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.activity.log'))
            ->assertOk()
            ->assertSee('5:23:19 AM EDT');
    }
}

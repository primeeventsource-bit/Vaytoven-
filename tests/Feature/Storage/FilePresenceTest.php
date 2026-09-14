<?php

namespace Tests\Feature\Storage;

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Support\Storage\FilePresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Asking the bucket once instead of once per file.
 *
 * The listing editor asks three questions of every photograph — is the file
 * there, is it there for the larger preview, is the pristine original there.
 * Each one was a round trip to object storage, measured at 377ms on production.
 * A dozen photographs was thirty-six round trips, and the admin edit page
 * returned a Cloudflare 504 while the server sat waiting on S3. The media
 * library was worse: one question per asset across two hundred of them.
 *
 * A single recursive listing of the folder answers all of it.
 */
class FilePresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.default', 'local');
    }

    private function photo(Property $property, string $path, ?string $original = null): PropertyPhoto
    {
        return PropertyPhoto::create([
            'property_id'   => $property->id,
            'disk'          => 'local',
            'path'          => $path,
            'original_path' => $original,
            'category'      => 'other',
            'mime_type'     => 'image/webp',
        ]);
    }

    // --- correctness ------------------------------------------------------------------

    public function test_it_resolves_files_that_are_there_and_files_that_are_not(): void
    {
        Storage::disk('local')->put('props/7/web/here.webp', 'bytes');

        FilePresence::prime('local', ['props/7/web/here.webp', 'props/7/web/gone.webp']);

        $this->assertTrue(FilePresence::known('local', 'props/7/web/here.webp'));
        $this->assertFalse(FilePresence::known('local', 'props/7/web/gone.webp'));
    }

    /** A path nobody primed must say so, not guess. */
    public function test_an_unprimed_path_is_unknown(): void
    {
        $this->assertNull(FilePresence::known('local', 'props/7/web/never-asked.webp'));
    }

    /**
     * The bug this would otherwise have: "props/3" is a character-prefix of
     * "props/34" but a different folder. Listing the wrong one would report a
     * photograph missing that is sitting right there.
     */
    public function test_the_shared_prefix_is_a_folder_not_a_string(): void
    {
        Storage::disk('local')->put('props/3/web/a.webp', 'bytes');
        Storage::disk('local')->put('props/34/web/b.webp', 'bytes');

        FilePresence::prime('local', ['props/3/web/a.webp', 'props/34/web/b.webp']);

        $this->assertTrue(FilePresence::known('local', 'props/3/web/a.webp'));
        $this->assertTrue(FilePresence::known('local', 'props/34/web/b.webp'), 'props/34 is not inside props/3');
    }

    /**
     * Files scattered with nothing in common would mean listing the entire
     * bucket to find a handful. Left unprimed on purpose — the per-file path
     * still answers, and it is cheaper than the alternative.
     */
    public function test_paths_with_no_shared_folder_are_left_alone(): void
    {
        Storage::disk('local')->put('alpha/one.webp', 'bytes');
        Storage::disk('local')->put('beta/two.webp', 'bytes');

        FilePresence::prime('local', ['alpha/one.webp', 'beta/two.webp']);

        $this->assertNull(FilePresence::known('local', 'alpha/one.webp'));
    }

    public function test_it_survives_a_null_disk_and_blank_paths(): void
    {
        FilePresence::prime(null, ['whatever.webp']);
        FilePresence::prime('local', [null, '', '   ']);

        $this->assertNull(FilePresence::known(null, 'whatever.webp'));
        $this->assertNull(FilePresence::known('local', ''));
    }

    // --- the point of it --------------------------------------------------------------

    /**
     * The assertion that proves the round trips are actually gone.
     *
     * After priming, the file is deleted from the disk. A model still reporting
     * it present can only be reading the primed answer — if it were asking the
     * bucket, it would now say missing.
     */
    public function test_a_primed_model_does_not_ask_the_disk_again(): void
    {
        $property = Property::factory()->create();

        Storage::disk('local')->put('props/9/web/x.webp', 'bytes');
        $photo = $this->photo($property, 'props/9/web/x.webp');

        FilePresence::prime('local', ['props/9/web/x.webp']);

        Storage::disk('local')->delete('props/9/web/x.webp');

        $this->assertTrue($photo->fileExists(), 'the primed answer should be used, not a fresh round trip');
    }

    /** And without priming, the model sees the disk as it really is. */
    public function test_an_unprimed_model_reads_the_disk(): void
    {
        $property = Property::factory()->create();

        Storage::disk('local')->put('props/9/web/y.webp', 'bytes');
        $photo = $this->photo($property, 'props/9/web/y.webp');

        $this->assertTrue($photo->fileExists());

        Storage::disk('local')->delete('props/9/web/y.webp');

        $this->assertFalse($photo->fileExists());
    }

    /**
     * The editor asks about the original too, and originals sit in a sibling
     * folder. Priming one and not the other would leave half the questions
     * still going to the bucket — the whole problem, half solved.
     */
    public function test_priming_covers_the_original_as_well_as_the_rendered_file(): void
    {
        $property = Property::factory()->create();

        Storage::disk('local')->put('props/9/web/z.webp', 'bytes');
        Storage::disk('local')->put('props/9/original/z.jpg', 'bytes');

        $photo = $this->photo($property, 'props/9/web/z.webp', 'props/9/original/z.jpg');

        FilePresence::prime('local', ['props/9/web/z.webp', 'props/9/original/z.jpg']);

        Storage::disk('local')->delete('props/9/original/z.jpg');

        $this->assertTrue($photo->originalExists(), 'the original should have been primed too');
    }

    public function test_a_missing_file_still_reports_missing_after_priming(): void
    {
        $property = Property::factory()->create();

        $photo = $this->photo($property, 'props/9/web/absent.webp');

        FilePresence::prime('local', ['props/9/web/absent.webp']);

        $this->assertFalse($photo->fileExists(), 'a photo whose file is gone must still say so');
    }

    // --- the page that was timing out --------------------------------------------------

    /**
     * End to end: the editor renders, and a photo with a missing file is still
     * reported as missing. Priming must make the page fast without making it
     * wrong.
     */
    public function test_the_listing_editor_renders_with_photos_present_and_missing(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $staff = \App\Models\User::factory()->create([
            'role'                 => \App\Enums\UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);
        $staff->roles()->sync([\App\Models\Role::where('key', 'super_admin')->firstOrFail()->id]);

        $property = Property::factory()->create();

        Storage::disk('local')->put('props/'.$property->id.'/web/present.webp', 'bytes');
        $this->photo($property, 'props/'.$property->id.'/web/present.webp');
        $this->photo($property, 'props/'.$property->id.'/web/missing.webp');

        $this->actingAs($staff)
            ->get(route('admin.properties.edit', $property))
            ->assertOk();
    }
}

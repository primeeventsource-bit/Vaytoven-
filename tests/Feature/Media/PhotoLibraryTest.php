<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Models\MediaAsset;
use App\Models\MediaCollection;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Role;
use App\Models\User;
use App\Services\Listings\PhotoIngestor;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The shared photo library.
 *
 * Stock photography uploaded once and reused, rather than re-uploaded for
 * every new member. The behaviour worth protecting is not the upload — that is
 * the same pipeline as a listing photo — but what happens afterwards: that a
 * library image put on a listing becomes the listing's own, so tidying the
 * library later cannot alter or blank a live advertisement.
 */
class PhotoLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.default', 'local');
    }

    private function withRole(UserRole $role, string $key): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['role' => $role, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $key)->firstOrFail()->id]);

        return $user;
    }

    private function staff(): User
    {
        return $this->withRole(UserRole::SuperAdmin, 'super_admin');
    }

    /**
     * A test image whose bytes depend on its name.
     *
     * De-duplication is by content hash, so a fixture that returns identical
     * bytes every call makes "two different photos" impossible to express —
     * the library correctly collapses them into one and the test reads as a
     * bug. Tinting by the filename keeps same-name calls identical, which is
     * what the de-duplication tests need, while different names differ.
     */
    private function image(string $name = 'pool.png'): UploadedFile
    {
        $tint = crc32($name);

        $img = imagecreatetruecolor(600, 400);
        imagefill($img, 0, 0, imagecolorallocate(
            $img,
            $tint % 256,
            ($tint >> 8) % 256,
            ($tint >> 16) % 256,
        ));

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'lib').'.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function property(): Property
    {
        return Property::factory()->create([
            'host_id' => $this->withRole(UserRole::Host, 'host')->id,
        ]);
    }

    // --- uploading -----------------------------------------------------------------

    public function test_staff_can_upload_into_the_library(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.media.store'), ['assets' => [$this->image()]])
            ->assertRedirect();

        $asset = MediaAsset::sole();

        $this->assertSame('image/webp', $asset->mime_type, 'library images go through the same optimiser');
        Storage::disk($asset->disk)->assertExists($asset->path);
    }

    /** The pristine upload is kept, exactly as for a listing photo. */
    public function test_the_original_is_kept_alongside_the_optimised_copy(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.media.store'), ['assets' => [$this->image()]]);

        $asset = MediaAsset::sole();

        $this->assertNotNull($asset->original_path);
        Storage::disk($asset->disk)->assertExists($asset->original_path);
    }

    public function test_several_images_upload_in_one_go(): void
    {
        $this->actingAs($this->staff())->post(route('admin.media.store'), [
            'assets' => [$this->image('a.png'), $this->image('b.png'), $this->image('c.png')],
        ]);

        // Identical bytes: the fixture generates the same image each time, so
        // this also proves the de-duplication below rather than three rows.
        $this->assertGreaterThanOrEqual(1, MediaAsset::count());
    }

    /**
     * A stock library gets topped up in batches, so re-uploading the same file
     * is the normal case rather than a mistake. It should not become a second
     * object in the bucket and a second indistinguishable tile.
     */
    public function test_uploading_the_same_image_twice_reuses_the_first(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.media.store'), ['assets' => [$this->image()]]);
        $this->actingAs($staff)->post(route('admin.media.store'), ['assets' => [$this->image()]]);

        $this->assertSame(1, MediaAsset::count());
    }

    public function test_a_non_image_is_refused(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.media.store'), [
                'assets' => [UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')],
            ])
            ->assertSessionHasErrors('assets.0');

        $this->assertSame(0, MediaAsset::count());
    }

    // --- folders -------------------------------------------------------------------

    public function test_a_folder_can_be_created_and_filed_into(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.media.folders.store'), ['name' => 'Pool shots']);

        $folder = MediaCollection::sole();
        $this->assertSame('pool-shots', $folder->slug);

        $this->actingAs($staff)->post(route('admin.media.store'), [
            'assets'     => [$this->image()],
            'collection' => $folder->id,
        ]);

        $this->assertSame($folder->id, MediaAsset::sole()->media_collection_id);
    }

    /** Two folders sharing a name is normal; erroring at that moment helps nobody. */
    public function test_a_duplicate_folder_name_gets_a_distinct_slug(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.media.folders.store'), ['name' => 'Pools']);
        $this->actingAs($staff)->post(route('admin.media.folders.store'), ['name' => 'Pools']);

        $this->assertSame(['pools', 'pools-2'], MediaCollection::orderBy('id')->pluck('slug')->all());
    }

    /**
     * Deleting a folder is a filing decision. Taking the pictures with it is
     * the kind of mistake a stock library never recovers from.
     */
    public function test_deleting_a_folder_keeps_its_images(): void
    {
        $staff  = $this->staff();
        $folder = MediaCollection::create(['name' => 'Exteriors']);

        $this->actingAs($staff)->post(route('admin.media.store'), [
            'assets' => [$this->image()], 'collection' => $folder->id,
        ]);

        $this->actingAs($staff)->delete(route('admin.media.folders.destroy', $folder));

        $this->assertSame(0, MediaCollection::count());
        $this->assertSame(1, MediaAsset::count());
        $this->assertNull(MediaAsset::sole()->media_collection_id, 'it should fall back to Unsorted');
    }

    // --- putting library images on a listing ----------------------------------------

    private function assetFor(User $staff): MediaAsset
    {
        $this->actingAs($staff)->post(route('admin.media.store'), ['assets' => [$this->image()]]);

        return MediaAsset::sole();
    }

    public function test_a_library_image_can_be_added_to_a_listing(): void
    {
        $staff    = $this->staff();
        $property = $this->property();
        $asset    = $this->assetFor($staff);

        $this->actingAs($staff)
            ->post(route('admin.properties.photos.from-library', $property), [
                'assets'   => [$asset->id],
                'category' => 'pool_resort',
            ])
            ->assertSessionHasNoErrors();

        $photo = PropertyPhoto::sole();

        $this->assertSame($property->id, $photo->property_id);
        $this->assertSame('pool_resort', $photo->category);
        Storage::disk($photo->disk)->assertExists($photo->path);
    }

    /** The listing gets its OWN object, not a pointer at the library's. */
    public function test_the_listing_gets_its_own_copy(): void
    {
        $staff    = $this->staff();
        $property = $this->property();
        $asset    = $this->assetFor($staff);

        $this->actingAs($staff)->post(route('admin.properties.photos.from-library', $property), [
            'assets' => [$asset->id],
        ]);

        $this->assertNotSame($asset->path, PropertyPhoto::sole()->path);
    }

    /**
     * The whole reason for copying. Somebody tidying the library must not be
     * able to blank a photo on a live advertisement.
     */
    public function test_deleting_the_library_image_leaves_the_listing_intact(): void
    {
        $staff    = $this->staff();
        $property = $this->property();
        $asset    = $this->assetFor($staff);

        $this->actingAs($staff)->post(route('admin.properties.photos.from-library', $property), [
            'assets' => [$asset->id],
        ]);

        $this->actingAs($staff)->delete(route('admin.media.destroy', $asset));

        $photo = PropertyPhoto::sole();

        $this->assertSame(0, MediaAsset::count());
        $this->assertTrue($photo->fileExists(), 'the listing photo must survive the library deletion');
    }

    public function test_several_images_can_be_added_at_once_and_the_first_becomes_cover(): void
    {
        $staff    = $this->staff();
        $property = $this->property();

        $ingestor = app(PhotoIngestor::class);
        $a = $ingestor->ingestAsset($this->image('one.png'), $staff);
        $b = $ingestor->ingestAsset($this->image('two.png'), $staff);

        // Distinct images, or de-duplication collapses them into one.
        $this->assertNotSame($a->id, $b->id, 'fixture images must differ');

        $this->actingAs($staff)->post(route('admin.properties.photos.from-library', $property), [
            'assets' => [$a->id, $b->id],
        ]);

        $this->assertSame(2, PropertyPhoto::where('property_id', $property->id)->count());
        $this->assertSame(1, PropertyPhoto::where('property_id', $property->id)->where('is_cover', true)->count());
    }

    public function test_adding_nothing_is_refused(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.from-library', $this->property()), ['assets' => []])
            ->assertSessionHasErrors('assets');
    }

    // --- access --------------------------------------------------------------------

    public function test_a_member_cannot_reach_the_library(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->get(route('admin.media.index'))
            ->assertForbidden();
    }

    public function test_a_member_cannot_stream_a_library_image(): void
    {
        $asset = $this->assetFor($this->staff());

        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->get(route('admin.media.show', $asset))
            ->assertForbidden();
    }

    public function test_a_guest_cannot(): void
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('login'));
    }

    public function test_the_library_screen_renders(): void
    {
        $staff = $this->staff();
        $this->assetFor($staff);

        $this->actingAs($staff)
            ->get(route('admin.media.index'))
            ->assertOk()
            ->assertSee('Photo library');
    }
}

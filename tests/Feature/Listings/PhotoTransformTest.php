<?php

namespace Tests\Feature\Listings;

use App\Enums\UserRole;
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
 * Rotating and cropping a listing photo.
 *
 * These assert on decoded pixels rather than on a 302, because every failure
 * worth catching here produces a perfectly successful redirect: a rotation
 * applied twice, a crop taken from the wrong corner, a box measured against the
 * derivative instead of the original. The response says nothing about any of
 * them; the image does.
 */
class PhotoTransformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.default', 'local');
    }

    private function staff(): User
    {
        return $this->withRole(UserRole::SuperAdmin, 'super_admin');
    }

    /**
     * A user holding a role by BOTH paths.
     *
     * The role column and the RBAC pivot are separate gates — the column gets
     * an admin past the nav, the pivot gets them past permission middleware —
     * and a user given only one of them fails for a reason that has nothing to
     * do with what the test is about. That is exactly how the ownership
     * assertions below first passed while checking nothing.
     */
    private function withRole(UserRole $role, string $key): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role'                 => $role,
            'must_change_password' => false,
        ]);

        $user->roles()->sync([Role::where('key', $key)->firstOrFail()->id]);

        return $user;
    }

    /**
     * A test image with a known landmark: a red block in the TOP-LEFT quadrant
     * of an otherwise white 400x200 canvas. Every assertion below is "where did
     * the red end up", which is the only thing that distinguishes a correct
     * transform from a plausible one.
     */
    private function upload(): UploadedFile
    {
        $image = imagecreatetruecolor(400, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 0, 0, 199, 99, imagecolorallocate($image, 255, 0, 0));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'vyt').'.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'landmark.png', 'image/png', null, true);
    }

    private function photo(Property $property): PropertyPhoto
    {
        return app(PhotoIngestor::class)->ingest($property, $this->upload(), null, 'exterior');
    }

    /** Owned by a host, which is how listings actually exist. */
    private function property(): Property
    {
        return Property::factory()->create([
            'host_id' => $this->withRole(UserRole::Host, 'host')->id,
        ]);
    }

    /** @return array{0: int, 1: int, 2: int} RGB at a fractional position of the served image. */
    private function pixelAt(PropertyPhoto $photo, float $fx, float $fy): array
    {
        $image = imagecreatefromstring((string) Storage::disk($photo->disk)->get($photo->path));

        $rgb = imagecolorat(
            $image,
            (int) min(imagesx($image) - 1, floor($fx * imagesx($image))),
            (int) min(imagesy($image) - 1, floor($fy * imagesy($image))),
        );

        return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
    }

    private function assertRedAt(PropertyPhoto $photo, float $fx, float $fy, string $because): void
    {
        [$r, $g, $b] = $this->pixelAt($photo->refresh(), $fx, $fy);

        // WebP is lossy, so exact 255/0/0 is not available. "Much more red than
        // green" separates the block from the white field without being brittle.
        $this->assertTrue($r > 150 && $g < 110, $because." — got rgb({$r},{$g},{$b})");
    }

    private function assertWhiteAt(PropertyPhoto $photo, float $fx, float $fy, string $because): void
    {
        [$r, $g, $b] = $this->pixelAt($photo->refresh(), $fx, $fy);

        $this->assertTrue($g > 150, $because." — got rgb({$r},{$g},{$b})");
    }

    // --- rotation ----------------------------------------------------------------

    public function test_the_uploaded_image_starts_with_the_block_top_left(): void
    {
        $photo = $this->photo($this->property());

        $this->assertRedAt($photo, 0.25, 0.25, 'the fixture should start top-left');
        $this->assertWhiteAt($photo, 0.75, 0.75, 'the opposite corner should be white');
    }

    /** A quarter turn right sends the top-left block to the top-right. */
    public function test_rotating_right_moves_the_block(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90])
            ->assertSessionHasNoErrors();

        $this->assertSame(90, (int) $photo->refresh()->rotation);
        $this->assertRedAt($photo, 0.75, 0.25, 'a right turn should carry the block to the top-right');
        $this->assertWhiteAt($photo, 0.25, 0.75, 'the bottom-left should now be white');
    }

    /** The stored dimensions have to follow the pixels, or the gallery reserves the wrong box. */
    public function test_rotating_swaps_the_recorded_dimensions(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        [$width, $height] = [$photo->width, $photo->height];

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90]);

        $photo->refresh();

        $this->assertSame($height, $photo->width);
        $this->assertSame($width, $photo->height);
    }

    /**
     * The posted rotation is where the image should END UP, so sending it twice
     * is idempotent. This is the difference between a resubmitted form being
     * harmless and it turning the photo onto its head.
     */
    public function test_posting_the_same_rotation_twice_does_not_turn_it_twice(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);
        $staff    = $this->staff();

        foreach ([1, 2] as $ignored) {
            $this->actingAs($staff)
                ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90]);
        }

        $this->assertSame(90, (int) $photo->refresh()->rotation);
        $this->assertRedAt($photo, 0.75, 0.25, 'it should still be a single quarter turn');
    }

    /** Four turns is where it started, because every edit replays the original. */
    public function test_a_full_circle_returns_to_the_original(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);
        $staff    = $this->staff();

        foreach ([90, 180, 270, 0] as $rotation) {
            $this->actingAs($staff)
                ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => $rotation]);
        }

        $this->assertSame(0, (int) $photo->refresh()->rotation);
        $this->assertRedAt($photo, 0.25, 0.25, 'a full circle should be indistinguishable from no edit');
    }

    // --- cropping ----------------------------------------------------------------

    /** Cropping to the top-left quadrant should leave nothing but the block. */
    public function test_cropping_keeps_only_the_selected_region(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), [
                'rotation' => 0,
                'crop_x'   => 0, 'crop_y' => 0, 'crop_w' => 0.5, 'crop_h' => 0.5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertRedAt($photo, 0.1, 0.1, 'the crop should be all block');
        $this->assertRedAt($photo, 0.9, 0.9, 'including its far corner');
        $this->assertSame(0.5, (float) $photo->refresh()->crop_w);
    }

    /** And cropping the other half should leave none of it. */
    public function test_cropping_the_far_half_excludes_the_block(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), [
                'rotation' => 0,
                'crop_x'   => 0.5, 'crop_y' => 0.5, 'crop_w' => 0.5, 'crop_h' => 0.5,
            ]);

        $this->assertWhiteAt($photo, 0.5, 0.5, 'the block is outside this crop');
    }

    /**
     * A second crop must widen back out, not cut into what the first one left.
     * This is the whole reason the original is kept and replayed.
     */
    public function test_a_second_crop_is_measured_against_the_original(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);
        $staff    = $this->staff();

        $this->actingAs($staff)->post(route('admin.properties.photos.transform', [$property, $photo]), [
            'rotation' => 0, 'crop_x' => 0.5, 'crop_y' => 0.5, 'crop_w' => 0.5, 'crop_h' => 0.5,
        ]);

        // Now ask for the top-left quadrant. Compounding would take a quadrant
        // of the white half and stay white.
        $this->actingAs($staff)->post(route('admin.properties.photos.transform', [$property, $photo]), [
            'rotation' => 0, 'crop_x' => 0, 'crop_y' => 0, 'crop_w' => 0.5, 'crop_h' => 0.5,
        ]);

        $this->assertRedAt($photo, 0.5, 0.5, 'the second crop should come from the untouched original');
    }

    public function test_the_crop_is_applied_after_the_rotation(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        // Turned right, the block sits top-right; that is the half the person
        // sees and therefore the half they would draw a box around.
        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), [
                'rotation' => 90,
                'crop_x'   => 0.5, 'crop_y' => 0, 'crop_w' => 0.5, 'crop_h' => 0.5,
            ]);

        $this->assertRedAt($photo, 0.5, 0.5, 'the box should select what the rotated image shows');
    }

    public function test_the_original_file_is_never_modified(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $before = md5((string) Storage::disk($photo->disk)->get($photo->original_path));

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), [
                'rotation' => 180, 'crop_x' => 0.1, 'crop_y' => 0.1, 'crop_w' => 0.4, 'crop_h' => 0.4,
            ]);

        $this->assertSame($before, md5((string) Storage::disk($photo->disk)->get($photo->refresh()->original_path)));
    }

    /**
     * The served key must change, or every cache in front of the site keeps
     * handing out the pre-edit image and the edit looks like it did not save.
     */
    public function test_the_served_key_changes_and_the_old_object_is_removed(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);
        $old      = $photo->path;

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90]);

        $this->assertNotSame($old, $photo->refresh()->path);
        Storage::disk($photo->disk)->assertMissing($old);
        Storage::disk($photo->disk)->assertExists($photo->path);
    }

    // --- refusals ----------------------------------------------------------------

    public function test_a_partial_crop_box_is_refused(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), [
                'rotation' => 0, 'crop_w' => 0.5,
            ])
            ->assertSessionHasErrors('crop_x');
    }

    public function test_a_crop_too_small_to_be_deliberate_is_refused(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), [
                'rotation' => 0,
                'crop_x'   => 0.1, 'crop_y' => 0.1, 'crop_w' => 0.001, 'crop_h' => 0.001,
            ])
            ->assertSessionHasErrors('crop_w');
    }

    public function test_an_arbitrary_angle_is_refused(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 45])
            ->assertSessionHasErrors('rotation');
    }

    /**
     * properties.edit is granted to the RBAC host role, so the permission
     * middleware alone lets any host reach any listing's photos.
     */
    public function test_another_host_cannot_transform_someone_elses_photo(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $intruder = $this->withRole(UserRole::Host, 'host');

        $this->actingAs($intruder)
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90])
            ->assertForbidden();

        $this->assertSame(0, (int) $photo->refresh()->rotation);
    }

    public function test_the_owner_can_transform_their_own_photo(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        $owner = User::findOrFail($property->host_id);

        $this->actingAs($owner)
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90])
            ->assertSessionHasNoErrors();

        $this->assertSame(90, (int) $photo->refresh()->rotation);
    }

    /**
     * Losing the original makes the photo uneditable. It must say so rather
     * than fall back to editing the derivative, which would compound whatever
     * crop is already baked in.
     */
    public function test_a_photo_whose_original_is_gone_refuses_the_edit(): void
    {
        $property = $this->property();
        $photo    = $this->photo($property);

        Storage::disk($photo->disk)->delete($photo->original_path);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.transform', [$property, $photo]), ['rotation' => 90])
            ->assertSessionHasErrors('photo_transform');

        $this->assertSame(0, (int) $photo->refresh()->rotation);
    }
}

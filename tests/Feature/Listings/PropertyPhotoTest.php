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

class PropertyPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to exercise the image pipeline.');
        }

        Storage::fake('local');
    }

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function property(array $attributes = []): Property
    {
        return Property::factory()->create(array_merge([
            'host_id' => User::factory()->create()->id,
        ], $attributes));
    }

    private function image(string $name = 'room.jpg', int $width = 1200, int $height = 800): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    // --- ingestion -------------------------------------------------------------

    public function test_an_upload_produces_a_served_copy_and_keeps_the_original(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos'   => [$this->image()],
                'category' => 'bedroom',
            ])
            ->assertRedirect();

        $photo = PropertyPhoto::sole();

        $this->assertSame('bedroom', $photo->category);
        $this->assertSame('image/webp', $photo->mime_type);
        $this->assertNotNull($photo->sha256);
        $this->assertSame('room.jpg', $photo->original_name);

        Storage::disk($photo->disk)->assertExists($photo->path);
        Storage::disk($photo->disk)->assertExists($photo->original_path);
        $this->assertNotSame($photo->path, $photo->original_path);
    }

    /** Serving a 6000px photo to a phone wastes the visitor's data, not ours. */
    public function test_an_oversized_image_is_scaled_down_for_serving(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image('huge.jpg', 6000, 4000)]]);

        $photo = PropertyPhoto::sole();

        $this->assertSame(2400, $photo->width);
        $this->assertSame(1600, $photo->height, 'aspect ratio must be preserved');
    }

    public function test_a_small_image_is_not_upscaled(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image('small.jpg', 800, 600)]]);

        $photo = PropertyPhoto::sole();

        $this->assertSame(800, $photo->width);
        $this->assertSame(600, $photo->height);
    }

    /**
     * Keys are namespaced per environment, derived from the app HOST.
     *
     * Not from app()->environment(): every Laravel Cloud environment on this
     * app runs APP_ENV=production, main included, so environment() gives the
     * same answer everywhere and the namespacing would be imaginary. A live
     * probe caught photos uploaded from main landing under "production/".
     */
    public function test_stored_keys_are_namespaced_by_host(): void
    {
        config(['app.url' => 'https://vaytoven.com']);
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $photo = PropertyPhoto::sole();

        $this->assertStringStartsWith('vaytoven-com/properties/'.$property->id.'/', $photo->path);
        $this->assertStringNotContainsString('room.jpg', $photo->path, 'the uploaded name must not build the key');
    }

    /** Two environments must not share a key space. */
    public function test_two_hosts_produce_different_key_spaces(): void
    {
        config(['app.url' => 'https://vaytoven.com']);
        $first = $this->property();
        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $first), ['photos' => [$this->image()]]);
        $a = PropertyPhoto::sole()->path;

        config(['app.url' => 'https://v-app-dev-production-iddl1a.laravel.cloud']);
        $second = $this->property();
        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $second), ['photos' => [$this->image()]]);
        $b = PropertyPhoto::orderByDesc('id')->first()->path;

        $this->assertNotSame(explode('/', $a)[0], explode('/', $b)[0]);
    }

    public function test_several_photos_upload_in_one_go(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos' => [$this->image('a.jpg'), $this->image('b.jpg'), $this->image('c.jpg')],
            ]);

        $this->assertSame(3, PropertyPhoto::count());
        $this->assertSame([1, 2, 3], PropertyPhoto::orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_a_non_image_is_refused(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos' => [UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf')],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, PropertyPhoto::count());
    }

    /** Refusing to write onto a disk that loses the file, before touching it. */
    public function test_uploads_are_refused_when_storage_is_not_durable(): void
    {
        // Seeded BEFORE the environment is switched: seeders prompt for
        // confirmation once the app believes it is in production, and a
        // prompt in a test run is a hang, not a failure.
        $staff    = $this->staff();
        $property = $this->property();

        config(['filesystems.default' => 'local', 'filesystems.disks.local.driver' => 'local']);
        app()->detectEnvironment(fn () => 'production');

        // Leaving the testing environment also switches CSRF validation on,
        // so without this the request is rejected with 419 and the refusal
        // being tested never runs.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($staff)
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]])
            ->assertSessionHasErrors('photos');

        $this->assertSame(0, PropertyPhoto::count());
    }

    // --- cover -----------------------------------------------------------------

    public function test_the_first_upload_becomes_the_cover(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $this->assertTrue(PropertyPhoto::sole()->is_cover);
    }

    /** Two covers give the search card a coin flip rather than a choice. */
    public function test_only_one_photo_can_be_the_cover(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos' => [$this->image('a.jpg'), $this->image('b.jpg')],
            ]);

        $second = PropertyPhoto::orderBy('sort_order')->skip(1)->first();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.cover', [$property, $second]));

        $this->assertSame(1, PropertyPhoto::where('is_cover', true)->count());
        $this->assertTrue($second->refresh()->is_cover);
    }

    public function test_deleting_the_cover_promotes_another_photo(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos' => [$this->image('a.jpg'), $this->image('b.jpg')],
            ]);

        $cover = PropertyPhoto::where('is_cover', true)->sole();

        $this->actingAs($this->staff())
            ->delete(route('admin.properties.photos.destroy', [$property, $cover]));

        $this->assertSame(1, PropertyPhoto::count());
        $this->assertTrue(PropertyPhoto::sole()->is_cover);
    }

    // --- editing ----------------------------------------------------------------

    public function test_reordering_is_saved(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos' => [$this->image('a.jpg'), $this->image('b.jpg'), $this->image('c.jpg')],
            ]);

        $ids = PropertyPhoto::orderBy('sort_order')->pluck('id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.reorder', $property), ['order' => $reversed]);

        $this->assertSame($reversed, PropertyPhoto::orderBy('sort_order')->pluck('id')->all());
    }

    /** A posted id from another listing must not be reordered by proxy. */
    public function test_reordering_ignores_photos_from_another_property(): void
    {
        $mine  = $this->property();
        $other = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $mine), ['photos' => [$this->image()]]);
        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $other), ['photos' => [$this->image()]]);

        $foreign = PropertyPhoto::where('property_id', $other->id)->sole();
        $before  = $foreign->sort_order;

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.reorder', $mine), ['order' => [$foreign->id]]);

        $this->assertSame($before, $foreign->refresh()->sort_order);
    }

    public function test_caption_alt_text_and_category_are_editable(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $photo = PropertyPhoto::sole();

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.photos.update', [$property, $photo]), [
                'caption'  => 'Main bedroom at sunrise',
                'alt_text' => 'Bedroom with a king bed facing a window',
                'category' => 'bedroom',
            ]);

        $photo->refresh();

        $this->assertSame('Main bedroom at sunrise', $photo->caption);
        $this->assertSame('Bedroom with a king bed facing a window', $photo->alt_text);
        $this->assertSame('bedroom', $photo->category);
    }

    /** Never empty: an unlabelled image is unusable with a screen reader. */
    public function test_alt_text_falls_back_rather_than_being_blank(): void
    {
        $property = $this->property(['title' => 'Ko Olina Suite']);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), [
                'photos' => [$this->image()], 'category' => 'kitchen',
            ]);

        $this->assertStringContainsString('Kitchen', PropertyPhoto::sole()->altText());
    }

    // --- serving -----------------------------------------------------------------

    public function test_an_uploaded_photo_is_served_publicly_with_cache_headers(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $photo = PropertyPhoto::sole();

        auth()->logout();

        $response = $this->get(route('properties.photo', $photo))->assertOk();

        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_a_missing_file_404s_rather_than_serving_nothing(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $photo = PropertyPhoto::sole();
        Storage::disk($photo->disk)->delete($photo->path);

        $this->get(route('properties.photo', $photo))->assertNotFound();
    }

    /** Legacy rows point at an external image and must keep working. */
    public function test_a_legacy_url_row_still_resolves(): void
    {
        $photo = PropertyPhoto::create([
            'property_id' => $this->property()->id,
            'url'         => 'https://images.example.com/a.jpg',
            'sort_order'  => 1,
        ]);

        $this->assertFalse($photo->isUploaded());
        $this->assertSame('https://images.example.com/a.jpg', $photo->displayUrl());
    }

    // --- access -------------------------------------------------------------------

    public function test_a_host_cannot_upload_to_someone_elses_listing(): void
    {
        $this->seed(RbacSeeder::class);

        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $this->actingAs($host)
            ->post(route('admin.properties.photos.store', $this->property()), ['photos' => [$this->image()]])
            ->assertForbidden();

        $this->assertSame(0, PropertyPhoto::count());
    }

    public function test_uploads_are_audited(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action'     => 'property_photo.uploaded',
            'subject_id' => $property->id,
        ]);
    }

    public function test_deleting_removes_both_copies_from_storage(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.photos.store', $property), ['photos' => [$this->image()]]);

        $photo = PropertyPhoto::sole();
        $served = $photo->path;
        $original = $photo->original_path;

        $this->actingAs($this->staff())
            ->delete(route('admin.properties.photos.destroy', [$property, $photo]));

        Storage::disk('local')->assertMissing($served);
        Storage::disk('local')->assertMissing($original);
        $this->assertSame(0, PropertyPhoto::count());
    }
}

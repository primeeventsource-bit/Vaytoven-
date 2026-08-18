<?php

namespace Tests\Feature\Listings;

use App\Enums\UserRole;
use App\Models\MemberDocument;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents filed against one property.
 *
 * One table, not two. The only real difference from a member document is
 * whether it is about the member or about one property they own — and a
 * parallel implementation would duplicate the storage guard, the hashing and
 * the audit trail, then drift from them.
 */
class PropertyDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function upload(Property $property, string $name = 'agreement.pdf'): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.documents.store', $property), [
                'file'     => UploadedFile::fake()->create($name, 100, 'application/pdf'),
                'category' => 'advertising_agreement',
            ]);
    }

    public function test_a_document_is_filed_against_the_property_and_its_owner(): void
    {
        $property = $this->property();

        $this->upload($property);

        $document = MemberDocument::sole();

        $this->assertSame($property->id, $document->property_id);
        // Also the member's, because a document about a listing is still a
        // document about the person who owns it.
        $this->assertSame($property->host_id, $document->user_id);
        $this->assertSame(64, strlen($document->sha256));

        Storage::disk($document->disk)->assertExists($document->path);
    }

    public function test_the_stored_path_does_not_use_the_uploaded_filename(): void
    {
        $property = $this->property();

        $this->upload($property, '../../etc/passwd.pdf');

        $document = MemberDocument::sole();

        $this->assertStringNotContainsString('passwd', $document->path);
        $this->assertStringNotContainsString('..', $document->path);
        $this->assertStringStartsWith("property-documents/{$property->id}/", $document->path);
    }

    /** A member agreement belongs to the member, not to any one property. */
    public function test_member_level_documents_stay_unattached(): void
    {
        $property = $this->property();
        $member   = User::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.members.documents.store', $member), [
                'file'     => UploadedFile::fake()->create('member.pdf', 40, 'application/pdf'),
                'category' => 'member_agreement',
            ]);

        $this->upload($property);

        $this->assertSame(1, MemberDocument::memberLevel()->count());
        $this->assertSame(1, MemberDocument::forProperty($property->id)->count());
    }

    public function test_it_appears_on_the_property_hub(): void
    {
        $property = $this->property();
        $this->upload($property);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertSee('agreement.pdf');
    }

    public function test_it_can_be_downloaded(): void
    {
        $property = $this->property();
        $this->upload($property);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.documents.download', [$property, MemberDocument::sole()]))
            ->assertOk();
    }

    /** The property id in the URL has to mean something. */
    public function test_a_document_cannot_be_fetched_through_another_propertys_url(): void
    {
        $owner     = $this->property();
        $unrelated = $this->property();

        $this->upload($owner);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.documents.download', [$unrelated, MemberDocument::sole()]))
            ->assertNotFound();
    }

    public function test_deleting_records_what_was_removed_before_removing_it(): void
    {
        $property = $this->property();
        $this->upload($property);

        $document = MemberDocument::sole();
        $path     = $document->path;

        $this->actingAs($this->staff())
            ->delete(route('admin.properties.documents.destroy', [$property, $document]))
            ->assertRedirect();

        $this->assertSame(0, MemberDocument::count());
        Storage::disk('local')->assertMissing($path);

        $log = \App\Models\AdminAuditLog::where('action', 'property_document.deleted')->sole();
        $this->assertSame('agreement.pdf', $log->payload['filename']);
        $this->assertSame($property->reference, $log->payload['reference']);
    }

    /**
     * Deleting a listing must not destroy the agreement signed for it. The
     * document outlives the listing and reverts to a member-level record,
     * which is what it is.
     */
    public function test_deleting_the_property_keeps_the_document(): void
    {
        $property = $this->property();
        $this->upload($property);

        $property->delete();

        $document = MemberDocument::sole();

        $this->assertNull($document->property_id);
        $this->assertNotNull($document->user_id);
    }

    public function test_uploads_are_audited(): void
    {
        $property = $this->property();
        $this->upload($property);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action'     => 'property_document.uploaded',
            'subject_id' => $property->id,
        ]);
    }

    public function test_a_host_cannot_file_against_another_members_property(): void
    {
        $this->seed(RbacSeeder::class);
        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $this->actingAs($host)
            ->post(route('admin.properties.documents.store', $this->property()), [
                'file'     => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
                'category' => 'other',
            ])
            ->assertForbidden();

        $this->assertSame(0, MemberDocument::count());
    }

    public function test_an_executable_is_rejected(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.documents.store', $property), [
                'file'     => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
                'category' => 'other',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MemberDocument::count());
    }
}

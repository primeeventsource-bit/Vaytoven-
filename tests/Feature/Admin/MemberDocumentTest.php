<?php

namespace Tests\Feature\Admin;

use App\Models\MemberDocument;
use App\Models\Role;
use App\Models\User;
use App\Support\Storage\DocumentStorage;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents attached to a member.
 *
 * The load-bearing behaviour is the refusal: on an environment with no durable
 * storage, an upload must be rejected rather than accepted and quietly lost on
 * the next deploy. A signed agreement that appears in the list and is gone
 * when a dispute needs it is the worst possible outcome for this feature.
 */
class MemberDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $roleKey = 'super_admin'): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $roleKey)->firstOrFail()->id]);

        return $user;
    }

    // --- durability ----------------------------------------------------------

    public function test_object_storage_counts_as_durable(): void
    {
        config(['filesystems.default' => 's3', 'filesystems.disks.s3.driver' => 's3']);
        app()->detectEnvironment(fn () => 'production');

        $this->assertTrue(DocumentStorage::isDurable());
    }

    public function test_local_storage_in_production_is_not_durable(): void
    {
        config(['filesystems.default' => 'local', 'filesystems.disks.local.driver' => 'local']);
        app()->detectEnvironment(fn () => 'production');

        $this->assertFalse(DocumentStorage::isDurable());
        $this->assertStringContainsString('lost on the next deploy', DocumentStorage::reason());
    }

    /** Requiring object storage to run the test suite would be absurd. */
    public function test_local_storage_is_fine_in_development(): void
    {
        config(['filesystems.default' => 'local', 'filesystems.disks.local.driver' => 'local']);
        app()->detectEnvironment(fn () => 'local');

        $this->assertTrue(DocumentStorage::isDurable());
    }

    /**
     * The refusal. This is the exact configuration the live site has today.
     */
    public function test_an_upload_is_refused_when_storage_is_ephemeral(): void
    {
        $staff  = $this->staff();
        $member = User::factory()->create();

        config(['filesystems.default' => 'local', 'filesystems.disks.local.driver' => 'local']);
        app()->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($staff)
            ->post(route('admin.members.documents.store', $member), [
                'file'     => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
                'category' => 'advertising_agreement',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MemberDocument::count(), 'A file was accepted onto ephemeral storage.');
    }

    // --- uploading -----------------------------------------------------------

    public function test_an_admin_can_upload_a_document(): void
    {
        Storage::fake('local');

        $staff  = $this->staff();
        $member = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('admin.members.documents.store', $member), [
                'file'     => UploadedFile::fake()->create('signed-agreement.pdf', 120, 'application/pdf'),
                'category' => 'advertising_agreement',
                'title'    => 'Signed advertising agreement',
            ])
            ->assertRedirect();

        $document = MemberDocument::sole();

        $this->assertSame($member->id, $document->user_id);
        $this->assertSame($staff->id, $document->uploaded_by_user_id);
        $this->assertSame('signed-agreement.pdf', $document->original_name);
        $this->assertSame(64, strlen($document->sha256));

        Storage::disk($document->disk)->assertExists($document->path);
    }

    /**
     * The original filename is attacker-supplied. It is kept as data, never
     * used to build the path.
     */
    public function test_the_stored_path_does_not_use_the_uploaded_filename(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.members.documents.store', $member), [
                'file'     => UploadedFile::fake()->create('../../etc/passwd.pdf', 10, 'application/pdf'),
                'category' => 'other',
            ]);

        $document = MemberDocument::sole();

        $this->assertStringNotContainsString('passwd', $document->path);
        $this->assertStringNotContainsString('..', $document->path);
        $this->assertStringStartsWith("member-documents/{$member->id}/", $document->path);
    }

    public function test_an_executable_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff())
            ->post(route('admin.members.documents.store', User::factory()->create()), [
                'file'     => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
                'category' => 'other',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MemberDocument::count());
    }

    public function test_uploads_are_audited(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.members.documents.store', $member), [
                'file'     => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
                'category' => 'invoice',
            ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action'     => 'member_document.uploaded',
            'subject_id' => $member->id,
        ]);
    }

    // --- access --------------------------------------------------------------

    public function test_it_can_be_downloaded(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($this->staff())->post(route('admin.members.documents.store', $member), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 15, 'application/pdf'),
            'category' => 'receipt',
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.members.documents.download', [$member, MemberDocument::sole()]))
            ->assertOk();
    }

    /**
     * A document belonging to one member must not be reachable through
     * another's route, or the member id in the URL means nothing.
     */
    public function test_a_document_cannot_be_fetched_through_another_members_url(): void
    {
        Storage::fake('local');

        $owner     = User::factory()->create();
        $unrelated = User::factory()->create();

        $this->actingAs($this->staff())->post(route('admin.members.documents.store', $owner), [
            'file' => UploadedFile::fake()->create('private.pdf', 15, 'application/pdf'),
            'category' => 'other',
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.members.documents.download', [$unrelated, MemberDocument::sole()]))
            ->assertNotFound();
    }

    public function test_a_role_without_member_edit_cannot_upload(): void
    {
        Storage::fake('local');

        $this->actingAs($this->staff('support'))
            ->post(route('admin.members.documents.store', User::factory()->create()), [
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
                'category' => 'other',
            ])
            ->assertForbidden();
    }

    public function test_a_visitor_cannot_download(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($this->staff())->post(route('admin.members.documents.store', $member), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            'category' => 'other',
        ]);

        auth()->logout();

        $this->get(route('admin.members.documents.download', [$member, MemberDocument::sole()]))
            ->assertRedirect(route('login'));
    }

    // --- deletion -------------------------------------------------------------

    /** The audit entry must survive the thing it describes. */
    public function test_deleting_records_what_was_removed_before_removing_it(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($this->staff())->post(route('admin.members.documents.store', $member), [
            'file' => UploadedFile::fake()->create('to-delete.pdf', 10, 'application/pdf'),
            'category' => 'other',
        ]);

        $document = MemberDocument::sole();
        $path     = $document->path;

        $this->actingAs($this->staff())
            ->delete(route('admin.members.documents.destroy', [$member, $document]))
            ->assertRedirect();

        $this->assertSame(0, MemberDocument::count());
        Storage::disk('local')->assertMissing($path);

        $log = \App\Models\AdminAuditLog::where('action', 'member_document.deleted')->sole();
        $this->assertSame('to-delete.pdf', $log->payload['filename']);
    }

    /** A row whose file has gone must be visible as broken, not offered. */
    public function test_a_missing_file_is_reported_rather_than_offered(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($this->staff())->post(route('admin.members.documents.store', $member), [
            'file' => UploadedFile::fake()->create('vanishing.pdf', 10, 'application/pdf'),
            'category' => 'other',
        ]);

        $document = MemberDocument::sole();
        Storage::disk($document->disk)->delete($document->path);

        $this->assertFalse($document->fileExists());

        $this->actingAs($this->staff())
            ->get(route('admin.members.show', ['user' => $member, 'tab' => 'documents']))
            ->assertOk()
            ->assertSee('file missing from storage');

        $this->actingAs($this->staff())
            ->get(route('admin.members.documents.download', [$member, $document]))
            ->assertNotFound();
    }
}

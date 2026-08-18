<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Where a contract's PDFs live, and what happens when they do not.
 *
 * Both paths used to be written to, and read back from, a hardcoded `local`
 * disk. On Laravel Cloud that disk is inside the container, so every signed
 * contract on the environment serving the public site was lost at the next
 * deploy — and lost silently, because the row kept its path and the download
 * only failed the day somebody needed the document.
 */
class ContractDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function contract(array $attributes = []): Contract
    {
        return Contract::create(array_merge([
            'client_name'   => 'Dana Whitfield',
            'client_email'  => 'dana@example.com',
            'contract_type' => Contract::TYPE_MEMBER_PROGRAM,
            'title'         => 'Managed Listing Program',
            'status'        => Contract::STATUS_COMPLETED,
            'envelope_id'   => 'env-abc-123',
        ], $attributes));
    }

    // --- which disk ----------------------------------------------------------

    /**
     * Rows written before the disk was recorded genuinely went to `local`.
     * Falling back to the CURRENT default would point those paths at a bucket
     * that has never held them.
     */
    public function test_a_legacy_row_still_resolves_to_the_local_disk(): void
    {
        config(['filesystems.default' => 'private', 'filesystems.disks.private.driver' => 's3']);

        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => null,
        ]);

        $this->assertSame('local', $contract->documentsDisk());
    }

    public function test_a_row_reads_back_from_the_disk_it_recorded(): void
    {
        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'private',
        ]);

        $this->assertSame('private', $contract->documentsDisk());
    }

    // --- existence -----------------------------------------------------------

    public function test_a_path_with_no_file_behind_it_is_not_treated_as_available(): void
    {
        Storage::fake('local');

        $contract = $this->contract([
            'signed_pdf_path'      => 'contracts/1/signed.pdf',
            'certificate_pdf_path' => 'contracts/1/certificate.pdf',
            'documents_disk'       => 'local',
        ]);

        $this->assertFalse($contract->signedPdfExists());
        $this->assertFalse($contract->certificatePdfExists());
    }

    public function test_a_stored_file_is_reported_as_available(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contracts/1/signed.pdf', '%PDF-1.4');

        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'local',
        ]);

        $this->assertTrue($contract->signedPdfExists());
    }

    // --- downloads -----------------------------------------------------------

    public function test_an_admin_downloads_from_the_recorded_disk(): void
    {
        Storage::fake('local');
        Storage::fake('archive');
        Storage::disk('archive')->put('contracts/1/signed.pdf', '%PDF-1.4 archived');

        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'archive',
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.contracts.download.signed', $contract))
            ->assertOk();
    }

    /**
     * The failure that matters: a missing file must 404 with an explanation
     * rather than stream an empty response or 500.
     */
    public function test_a_missing_signed_pdf_is_refused_with_an_explanation(): void
    {
        Storage::fake('local');

        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'local',
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.contracts.download.signed', $contract))
            ->assertNotFound();
    }

    public function test_the_admin_screen_says_the_file_is_missing_instead_of_offering_it(): void
    {
        Storage::fake('local');

        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'local',
        ]);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.contracts.show', $contract))
            ->assertOk();

        $response->assertSee('The stored signed PDF is missing.');
        $response->assertSee('Re-fetch from DocuSign');
        $response->assertDontSee(route('admin.contracts.download.signed', $contract), false);
    }

    /**
     * A member must not be told to diagnose storage, and must not be shown a
     * download that fails.
     */
    public function test_a_member_is_pointed_at_support_rather_than_a_broken_link(): void
    {
        Storage::fake('local');

        $member = User::factory()->create(['email' => 'dana@example.com', 'must_change_password' => false]);

        $contract = $this->contract([
            'user_id'         => $member->id,
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'local',
            'signed_at'       => now(),
        ]);

        $response = $this->actingAs($member)
            ->get(route('client.contracts.show', $contract))
            ->assertOk();

        $response->assertSee('Contact@Vaytoven.com');
        $response->assertDontSee(route('client.contracts.download', $contract), false);
    }

    public function test_a_member_cannot_download_another_members_contract(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contracts/1/signed.pdf', '%PDF-1.4');

        $contract = $this->contract([
            'signed_pdf_path' => 'contracts/1/signed.pdf',
            'documents_disk'  => 'local',
        ]);

        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->get(route('client.contracts.download', $contract))
            ->assertForbidden();
    }

    // --- recovery ------------------------------------------------------------

    public function test_refetching_requires_an_envelope(): void
    {
        $contract = $this->contract(['envelope_id' => null]);

        $this->actingAs($this->staff())
            ->post(route('admin.contracts.refetch', $contract))
            ->assertNotFound();
    }
}

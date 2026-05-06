<?php

namespace Database\Seeders;

use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Database\Seeder;

/**
 * Materialises the four legal documents into terms_versions on every seed.
 *
 * Idempotent — TermsVersion::forContent matches by SHA-256 of the rendered
 * HTML, so unchanged content reuses the existing row. When counsel replaces
 * the placeholder Blade text, the next deploy seed creates a new row with a
 * different hash and EnsureCurrentTermsAccepted forces re-acceptance.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([]);
        app(LegalDocumentRegistry::class)->materialiseAll();
    }
}

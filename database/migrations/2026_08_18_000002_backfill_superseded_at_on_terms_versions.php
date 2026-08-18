<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire the legal document versions that are no longer in force.
 *
 * superseded_at has existed since the table was created and has never been
 * written to, so all 21 rows on the live database claim to be current —
 * including nine different `tos` rows. Nothing is broken today, because
 * currentFor() falls back to ordering by effective_at. The column exists to
 * carry an invariant, though, and an unenforced invariant is one that will be
 * relied upon eventually.
 *
 * Where it would actually bite: content-addressing means reverting a document
 * to earlier text returns the ORIGINAL row, whose effective_at predates the
 * text it replaces. "Latest effective" would then name a version the site no
 * longer serves, and the re-acceptance middleware would ask people to accept
 * a document that is not the one on screen.
 *
 * Each version is stamped with the effective_at of the version that replaced
 * it, which is when it genuinely stopped being in force — not now(), which
 * would claim every document was retired the day this migration ran.
 *
 * Nothing about the documents themselves is touched: no kind, label, hash,
 * url or effective_at is rewritten, and no acceptance row is altered. People
 * accepted those exact terms at that exact moment and the record stays as it
 * was. Only the "is this still in force" bookkeeping is filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kinds = DB::table('terms_versions')->distinct()->pluck('kind');

        foreach ($kinds as $kind) {
            $versions = DB::table('terms_versions')
                ->where('kind', $kind)
                ->orderBy('effective_at')
                ->orderBy('id')
                ->get(['id', 'effective_at', 'superseded_at']);

            // Every version except the last is superseded by the one after it.
            for ($i = 0; $i < $versions->count() - 1; $i++) {
                $version   = $versions[$i];
                $successor = $versions[$i + 1];

                if ($version->superseded_at !== null) {
                    continue;
                }

                DB::table('terms_versions')
                    ->where('id', $version->id)
                    ->update(['superseded_at' => $successor->effective_at]);
            }
        }
    }

    /**
     * Clearing the column returns the table to the state this migration found
     * it in — everything claiming to be current. Nothing else was written, so
     * nothing else needs undoing.
     */
    public function down(): void
    {
        DB::table('terms_versions')->update(['superseded_at' => null]);
    }
};

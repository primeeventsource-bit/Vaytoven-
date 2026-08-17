<?php

use App\Models\MemberOffer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A quotable reference and a viewed timestamp for every offer.
 *
 * Staff and members refer to an offer on the phone. Until now the only handle
 * was the database id, which is neither safe to expose (it enumerates) nor
 * possible to read aloud without ambiguity.
 *
 * viewed_at answers the question that comes up in every "the owner never got
 * back to me" conversation: did they actually see it, or did it lapse
 * unopened? Those are different failures and they need different responses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->string('reference', 24)->nullable()->unique()->after('id');
            $table->timestamp('viewed_at')->nullable()->after('sent_at');
        });

        // Backfill. Existing offers need references too, or half the register
        // shows a blank column and staff cannot quote the older ones.
        DB::table('member_offers')->whereNull('reference')->orderBy('id')
            ->select('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('member_offers')
                        ->where('id', $row->id)
                        ->update(['reference' => MemberOffer::generateReference()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->dropColumn(['reference', 'viewed_at']);
        });
    }
};

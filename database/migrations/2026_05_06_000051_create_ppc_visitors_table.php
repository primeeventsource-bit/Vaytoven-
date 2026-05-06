<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppc_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 36)->unique();
            $table->timestamp('first_seen_at');
            $table->string('landing_url', 2048)->nullable();
            $table->string('utm_source', 64)->nullable();
            $table->string('utm_medium', 64)->nullable();
            $table->string('utm_campaign', 128)->nullable();
            $table->string('utm_term', 128)->nullable();
            $table->string('utm_content', 128)->nullable();
            $table->string('gclid', 128)->nullable();
            $table->string('fbclid', 128)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->timestamps();

            $table->index('utm_source');
            $table->index('utm_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppc_visitors');
    }
};

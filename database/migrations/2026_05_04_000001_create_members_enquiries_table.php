<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members_enquiries', function (Blueprint $table) {
            $table->id();

            // Submitted form fields. Lengths mirror the validation rules
            // in routes/web.php so the column never silently truncates a
            // value the request layer accepted.
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 160);
            $table->string('phone', 40);
            $table->string('club', 80);
            $table->string('property', 255);
            $table->string('points', 60);
            $table->string('contact_window', 120)->nullable();

            // Required consent — stored as a timestamp rather than a bool so
            // we keep evidence of *when* the member opted in, which is what
            // most privacy regimes (GDPR/CCPA) actually require.
            $table->timestamp('consented_at');

            // Capture context. source_url is the page the member was on when
            // they submitted (taken from the Referer header). IP is sized
            // for IPv6. user_agent is text because UA strings have no upper
            // bound in practice.
            $table->string('source_url', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members_enquiries');
    }
};

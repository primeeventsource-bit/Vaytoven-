<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();

            // Public URL slug — kebab-case, stable identifier the support chat
            // and any external doc can link to without an integer id.
            $table->string('slug', 120)->unique();

            // Persona scope. 'all' shows for every audience; 'traveler', 'host',
            // 'member' filter the public help index and bias chat tool results.
            $table->string('audience', 20)->default('all');

            // Coarse taxonomy ('cancellation', 'payments', 'hosting', etc.)
            // surfaces the help index by category.
            $table->string('category', 60)->default('general');

            $table->string('title', 200);

            // Short blurb shown on the index list and quoted by the chat agent
            // when only a one-line answer is needed.
            $table->string('summary', 500);

            // Full markdown/plain-text body. Length matters less than findability
            // here; longtext gives us room without committing to a CMS yet.
            $table->longText('body');

            // Comma-separated free-text keywords. The DB search ANDs the user's
            // query tokens against title/summary/body/keywords — this column is
            // the editorial knob for "make this article findable for X phrasing".
            $table->text('search_keywords')->nullable();

            // Editorial controls.
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('audience');
            $table->index('category');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};

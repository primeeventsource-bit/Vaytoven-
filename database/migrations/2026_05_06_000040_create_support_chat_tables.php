<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('visitor_id', 36)->nullable();
            $table->enum('surface', ['web', 'app_ios', 'app_android', 'admin']);
            $table->string('claude_model', 64)->nullable();
            $table->string('system_prompt_version', 32)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('visitor_id');
            $table->index('started_at');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('support_chat_sessions')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool', 'system']);
            $table->mediumText('content');
            $table->json('tool_calls')->nullable();    // structured assistant tool invocations
            $table->json('tool_result')->nullable();   // tool call output
            $table->timestamp('occurred_at');

            $table->index(['session_id', 'occurred_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('support_chat_sessions');
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users');
            $table->string('subject');
            $table->mediumText('body');
            $table->enum('status', ['open', 'assigned', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->mediumText('resolution_note')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to_user_id');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users');
            $table->boolean('is_internal_note')->default(false);
            $table->mediumText('body');
            $table->timestamp('occurred_at');

            $table->index(['ticket_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_chat_sessions');
    }
};

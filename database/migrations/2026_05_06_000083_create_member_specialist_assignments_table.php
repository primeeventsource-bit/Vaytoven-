<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_specialist_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialist_user_id')->constrained('users');
            $table->foreignId('enquiry_id')->constrained('members_enquiries');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users');
            $table->enum('assignment_method', ['round_robin', 'manual'])->default('round_robin');
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();

            $table->unique(['enquiry_id', 'specialist_user_id', 'assigned_at'], 'uk_specialist_enquiry_assignment');
            $table->index('specialist_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_specialist_assignments');
    }
};

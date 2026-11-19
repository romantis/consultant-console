<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_assignment', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete(); // assignee
            $table->timestamp('due_at')->nullable();
            // remember when we sent reminders to avoid duplicates
            $table->timestamp('reminded_48h_at')->nullable();
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_4h_at')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_assignment');
    }
};

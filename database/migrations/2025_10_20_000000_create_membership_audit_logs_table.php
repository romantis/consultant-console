<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('membership_id', 26);
            $table->string('actor_membership_id', 26)->nullable();
            $table->string('action');
            $table->jsonb('properties')->default('{}');
            $table->timestamps();

            $table->index(['organization_id', 'action']);
            $table->index('membership_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_audit_logs');
    }
};

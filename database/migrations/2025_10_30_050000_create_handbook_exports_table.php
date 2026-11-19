<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handbook_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->string('format', 30)->default('pdf');
            $table->string('storage_disk')->default(config('metric-harmony.handbook_disk', 'local'));
            $table->string('storage_path');
            $table->string('download_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('checksum')->nullable();
            $table->string('status', 20)->default('queued');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['engagement_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handbook_exports');
    }
};

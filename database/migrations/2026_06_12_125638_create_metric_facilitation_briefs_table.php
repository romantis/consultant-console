<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_facilitation_briefs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->unique()->constrained('metrics')->cascadeOnDelete();
            $table->string('schema_version');
            $table->string('status');
            $table->string('source_fingerprint');
            $table->json('generated_draft')->nullable();
            $table->json('document');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_facilitation_briefs');
    }
};

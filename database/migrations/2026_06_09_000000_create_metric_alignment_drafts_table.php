<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_alignment_drafts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->unique()->constrained('metrics')->cascadeOnDelete();
            $table->string('profile_version');
            $table->string('provider');
            $table->string('requested_model');
            $table->string('returned_model');
            $table->string('source_fingerprint');
            $table->json('draft');
            $table->json('usage');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_alignment_drafts');
    }
};

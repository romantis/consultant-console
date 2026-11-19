<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('responsibility', 20);
            $table->string('availability_cadence', 20)->nullable();
            $table->jsonb('role_tags_json')->nullable();
            $table->timestamps();

            $table->unique(['metric_id', 'user_id', 'responsibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_participants');
    }
};

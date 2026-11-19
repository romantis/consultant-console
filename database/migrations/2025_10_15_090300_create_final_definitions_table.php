<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('Draft');
            $table->jsonb('definition_json')->default('{}');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('metric_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_definitions');
    }
};

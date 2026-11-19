<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discrepancies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('rule_type', 20);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('Open');
            $table->text('description')->nullable();
            $table->jsonb('values_snapshot');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['metric_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discrepancies');
    }
};

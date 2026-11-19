<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();   // 'mrr','churn','active_users'
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('form_schema_json')->nullable(); // JSON schema for W2 forms
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_templates');
    }
};

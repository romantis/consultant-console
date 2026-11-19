<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_metric_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('org_metric_id')->constrained('org_metrics')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsibility', 20);
            $table->string('subject_type', 120)->nullable();
            $table->ulid('subject_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['org_metric_id', 'user_id', 'responsibility'],
                'org_metric_participants_org_user_role_unique'
            );
            $table->unique(
                ['org_metric_id', 'subject_type', 'subject_id', 'responsibility'],
                'org_metric_participants_org_subject_role_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_metric_participants');
    }
};

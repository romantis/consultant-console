<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->string('repository_driver')->default('notion');
            $table->string('repository_reference')->nullable();
            $table->json('stakeholders_json')->nullable();
            $table->text('repository_notes')->nullable();
            $table->json('onboarding_checklist_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropColumn([
                'repository_driver',
                'repository_reference',
                'stakeholders_json',
                'repository_notes',
                'onboarding_checklist_json',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->dropIndex(['engagement_id', 'status']);
            $table->dropConstrainedForeignId('engagement_id');
        });
    }

    public function down(): void
    {
        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->foreignUlid('engagement_id')->after('organization_id')->constrained('engagements')->cascadeOnDelete();
            $table->index(['engagement_id', 'status']);
        });
    }
};

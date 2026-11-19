<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_memberships', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_memberships', 'engagement_id')) {
                $table->foreignUlid('engagement_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('engagements')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pending_memberships', 'affiliation')) {
                $table->string('affiliation')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_memberships', function (Blueprint $table) {
            if (Schema::hasColumn('pending_memberships', 'engagement_id')) {
                $table->dropConstrainedForeignId('engagement_id');
            }

            if (Schema::hasColumn('pending_memberships', 'affiliation')) {
                $table->dropColumn('affiliation');
            }
        });
    }
};

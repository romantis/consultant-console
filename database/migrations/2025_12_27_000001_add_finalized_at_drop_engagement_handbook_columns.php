<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->timestampTz('finalized_at')->nullable()->after('ends_on');
            $table->dropConstrainedForeignId('handbook_generated_by');
            $table->dropColumn(['handbook_generated_at', 'handbook_path', 'handbook_disk']);
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropColumn('finalized_at');
            $table->string('handbook_disk')->nullable()->after('ends_on');
            $table->string('handbook_path')->nullable()->after('handbook_disk');
            $table->timestampTz('handbook_generated_at')->nullable()->after('handbook_path');
            $table->foreignUlid('handbook_generated_by')->nullable()->after('handbook_generated_at')->constrained('users')->nullOnDelete();
        });
    }
};

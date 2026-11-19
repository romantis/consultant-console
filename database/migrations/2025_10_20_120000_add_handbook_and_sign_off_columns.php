<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->timestampTz('signed_off_at')->nullable()->after('notes');
            $table->foreignUlid('signed_off_by')->nullable()->after('signed_off_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('engagements', function (Blueprint $table) {
            $table->string('handbook_disk')->nullable()->after('ends_on');
            $table->string('handbook_path')->nullable()->after('handbook_disk');
            $table->timestampTz('handbook_generated_at')->nullable()->after('handbook_path');
            $table->foreignUlid('handbook_generated_by')->nullable()->after('handbook_generated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('final_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_off_by');
            $table->dropColumn('signed_off_at');
        });

        Schema::table('engagements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handbook_generated_by');
            $table->dropColumn(['handbook_generated_at', 'handbook_path', 'handbook_disk']);
        });
    }
};

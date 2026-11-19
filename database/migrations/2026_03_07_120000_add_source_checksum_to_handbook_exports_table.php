<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->string('source_checksum')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->dropColumn('source_checksum');
        });
    }
};

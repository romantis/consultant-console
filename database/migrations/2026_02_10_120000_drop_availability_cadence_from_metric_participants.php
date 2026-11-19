<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('metric_participants', 'availability_cadence')) {
            return;
        }

        Schema::table('metric_participants', function (Blueprint $table) {
            $table->dropColumn('availability_cadence');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('metric_participants', 'availability_cadence')) {
            return;
        }

        Schema::table('metric_participants', function (Blueprint $table) {
            $table->string('availability_cadence', 20)->nullable()->after('responsibility');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->string('review_cadence', 60)->nullable()->after('ends_on');
            $table->date('next_review_on')->nullable()->after('review_cadence');
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropColumn(['review_cadence', 'next_review_on']);
        });
    }
};

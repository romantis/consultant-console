<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('external_source', 40)->nullable()->after('status');
            $table->string('external_form_id', 120)->nullable()->after('external_source');
            $table->timestamp('last_submission_at')->nullable()->after('external_form_id');
            $table->index(['external_source', 'external_form_id']);
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex('forms_external_source_external_form_id_index');
            $table->dropColumn(['external_source', 'external_form_id', 'last_submission_at']);
        });
    }
};

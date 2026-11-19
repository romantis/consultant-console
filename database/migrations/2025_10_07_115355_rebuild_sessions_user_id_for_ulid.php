<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // Drop index first (name can vary). This is safe even if it doesn't exist.
            try {
                $table->dropIndex('sessions_user_id_index');
            } catch (Throwable $e) { /* ignore */
            }
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->char('user_id', 26)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            try {
                $table->dropIndex('sessions_user_id_index');
            } catch (Throwable $e) { /* ignore */
            }
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
        });
    }
};

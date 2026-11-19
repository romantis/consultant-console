<?php

use App\Enums\FormAssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_assignment', function (Blueprint $table) {
            $table->string('status', 20)
                ->default(FormAssignmentStatus::OPEN->value)
                ->index();
        });

        DB::table('form_assignment')
            ->whereNull('status')
            ->update(['status' => FormAssignmentStatus::OPEN->value]);
    }

    public function down(): void
    {
        Schema::table('form_assignment', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('does not include the archived member next steps zombie schema', function () {
    expect(Schema::hasTable('member_action_items'))->toBeFalse()
        ->and(Schema::hasTable('engagement_member_summaries'))->toBeFalse()
        ->and(Schema::hasTable('member_help_requests'))->toBeFalse()
        ->and(Schema::hasTable('member_next_steps_insights'))->toBeFalse()
        ->and(Schema::hasTable('member_next_steps_surveys'))->toBeFalse();

    if (DB::getDriverName() === 'pgsql') {
        $viewExists = DB::table('information_schema.views')
            ->where('table_schema', 'public')
            ->where('table_name', 'member_next_steps_session_deltas')
            ->exists();

        expect($viewExists)->toBeFalse();
    }
});

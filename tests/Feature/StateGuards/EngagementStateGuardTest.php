<?php

use App\Enums\EngagementStatus;
use App\Models\Engagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('engagement cannot skip mandatory phases', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::DRAFT->value,
    ]);

    expect(fn () => $engagement->forceFill(['status' => EngagementStatus::IN_REVIEW->value])->save())
        ->toThrow(ValidationException::class);

    $engagement->refresh();

    expect($engagement->status)->toBe(EngagementStatus::DRAFT);
});

test('finalized engagement cannot revert to awaiting forms', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    expect(fn () => $engagement->forceFill(['status' => EngagementStatus::AWAITING_FORMS->value])->save())
        ->toThrow(ValidationException::class);

    $engagement->refresh();

    expect($engagement->status)->toBe(EngagementStatus::FINALIZED);
});

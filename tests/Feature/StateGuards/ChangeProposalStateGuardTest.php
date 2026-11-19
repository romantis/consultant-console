<?php

use App\Enums\ChangeProposalStatus;
use App\Support\StateGuards\ChangeProposalStateGuard;
use Illuminate\Validation\ValidationException;

test('approved change proposal cannot return to open', function () {
    expect(fn () => ChangeProposalStateGuard::ensureValid(
        ChangeProposalStatus::APPROVED,
        ChangeProposalStatus::OPEN
    ))->toThrow(ValidationException::class);
});

test('open change proposal can move to approved', function () {
    expect(fn () => ChangeProposalStateGuard::ensureValid(
        ChangeProposalStatus::OPEN,
        ChangeProposalStatus::APPROVED
    ))->not->toThrow(ValidationException::class);
});

test('rejected change proposal cannot merge without an override path', function () {
    expect(fn () => ChangeProposalStateGuard::ensureValid(
        ChangeProposalStatus::REJECTED,
        ChangeProposalStatus::MERGED
    ))->toThrow(ValidationException::class);
});

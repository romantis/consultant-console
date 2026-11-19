<?php

use App\Support\Result;

it('maps ok values through map and andThen', function () {
    $result = Result::ok(5)
        ->map(fn (int $value): int => $value * 2)
        ->andThen(fn (int $value): Result => Result::ok($value + 1));

    expect($result->isOk())->toBeTrue()
        ->and($result->isErr())->toBeFalse()
        ->and($result->value())->toBe(11);
});

it('preserves errors through map and andThen without invoking callbacks', function () {
    $mapped = false;
    $chained = false;

    $result = Result::err('missing-input')
        ->map(function () use (&$mapped): string {
            $mapped = true;

            return 'should-not-run';
        })
        ->andThen(function () use (&$chained): Result {
            $chained = true;

            return Result::ok('should-not-run');
        });

    expect($mapped)->toBeFalse()
        ->and($chained)->toBeFalse()
        ->and($result->isErr())->toBeTrue()
        ->and($result->error())->toBe('missing-input');
});

it('maps error values when in err state', function () {
    $result = Result::err(['code' => 'scope_mismatch'])
        ->mapError(fn (array $error): string => strtoupper($error['code']));

    expect($result->isErr())->toBeTrue()
        ->and($result->error())->toBe('SCOPE_MISMATCH');
});

it('throws when reading value from err result', function () {
    $result = Result::err('boom');

    expect(fn () => $result->value())
        ->toThrow(LogicException::class, 'Cannot read value() from an error result.');
});

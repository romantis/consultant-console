<?php

namespace App\Support;

use LogicException;

/**
 * @template TValue
 * @template TError
 */
final class Result
{
    /**
     * @param  TValue|null  $value
     * @param  TError|null  $error
     */
    private function __construct(
        private readonly bool $ok,
        private readonly mixed $value = null,
        private readonly mixed $error = null,
    ) {}

    /**
     * @template TOk
     *
     * @param  TOk  $value
     * @return self<TOk, never>
     */
    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    /**
     * @template TErr
     *
     * @param  TErr  $error
     * @return self<never, TErr>
     */
    public static function err(mixed $error): self
    {
        return new self(false, null, $error);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isErr(): bool
    {
        return ! $this->ok;
    }

    /**
     * @return TValue
     */
    public function value(): mixed
    {
        if ($this->isErr()) {
            throw new LogicException('Cannot read value() from an error result.');
        }

        return $this->value;
    }

    /**
     * @return TError
     */
    public function error(): mixed
    {
        if ($this->isOk()) {
            throw new LogicException('Cannot read error() from an ok result.');
        }

        return $this->error;
    }

    /**
     * @template TNextValue
     *
     * @param  callable(TValue): TNextValue  $mapper
     * @return self<TNextValue, TError>
     */
    public function map(callable $mapper): self
    {
        if ($this->isErr()) {
            /** @var self<TNextValue, TError> $result */
            $result = self::err($this->error);

            return $result;
        }

        /** @var TNextValue $mapped */
        $mapped = $mapper($this->value);

        return self::ok($mapped);
    }

    /**
     * @template TNextError
     *
     * @param  callable(TError): TNextError  $mapper
     * @return self<TValue, TNextError>
     */
    public function mapError(callable $mapper): self
    {
        if ($this->isOk()) {
            /** @var self<TValue, TNextError> $result */
            $result = self::ok($this->value);

            return $result;
        }

        /** @var TNextError $mapped */
        $mapped = $mapper($this->error);

        return self::err($mapped);
    }

    /**
     * @template TNextValue
     * @template TNextError
     *
     * @param  callable(TValue): self<TNextValue, TNextError>  $next
     * @return self<TNextValue, TError|TNextError>
     */
    public function andThen(callable $next): self
    {
        if ($this->isErr()) {
            /** @var self<TNextValue, TError|TNextError> $result */
            $result = self::err($this->error);

            return $result;
        }

        /** @var self<TNextValue, TError|TNextError> $result */
        $result = $next($this->value);

        return $result;
    }

    /**
     * @template TOut
     *
     * @param  callable(TValue): TOut  $onOk
     * @param  callable(TError): TOut  $onErr
     * @return TOut
     */
    public function match(callable $onOk, callable $onErr): mixed
    {
        if ($this->isOk()) {
            return $onOk($this->value);
        }

        return $onErr($this->error);
    }
}

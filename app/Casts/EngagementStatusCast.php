<?php

namespace App\Casts;

use App\Enums\EngagementStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EngagementStatusCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?EngagementStatus
    {
        if ($value === null) {
            return null;
        }

        return EngagementStatus::fromLegacy((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof EngagementStatus) {
            return $value->value;
        }

        if (is_string($value)) {
            return EngagementStatus::fromLegacy($value)->value;
        }

        throw new InvalidArgumentException('Engagement status must be a string or EngagementStatus.');
    }
}

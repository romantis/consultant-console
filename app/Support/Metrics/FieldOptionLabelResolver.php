<?php

namespace App\Support\Metrics;

use App\Models\Metric;

class FieldOptionLabelResolver
{
    /**
     * Build a field => option-label map for the provided metric's template.
     *
     * @return array<string, array<string, string>>
     */
    public static function forMetric(Metric $metric): array
    {
        $schema = $metric->template?->form_schema_json ?? [];

        return self::fromSchema($schema);
    }

    /**
     * Build a map from a raw form schema array.
     *
     * @param  array<string, mixed>|null  $schema
     * @return array<string, array<string, string>>
     */
    public static function fromSchema(?array $schema): array
    {
        $fields = data_get($schema, 'fields', []);

        if (! is_array($fields)) {
            return [];
        }

        $map = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $options = $field['options'] ?? null;
            if (! is_array($options) || empty($options)) {
                continue;
            }

            $labels = self::normalizeOptions($options);
            if (! empty($labels)) {
                $map[$name] = $labels;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $options
     * @return array<string, string>
     */
    protected static function normalizeOptions(array $options): array
    {
        $map = [];

        foreach ($options as $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? null;
                $label = $option['label'] ?? null;

                if (! is_string($value) || $value === '') {
                    if (is_string($label) && $label !== '') {
                        $value = $label;
                    } else {
                        continue;
                    }
                }

                if (! is_string($label) || $label === '') {
                    $label = $value;
                }

                $map[$value] = $label;

                continue;
            }

            if (is_scalar($option)) {
                $value = (string) $option;
                $map[$value] = $value;
            }
        }

        return $map;
    }
}

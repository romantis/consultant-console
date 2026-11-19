<?php

return [
    'dataset' => base_path('specs/033-metric-alignment-brief/evaluation/dataset-v1.json'),
    'output_schema' => base_path('specs/033-metric-alignment-brief/evaluation/generated-draft-brief.schema.json'),
    'repetitions' => 3,
    'budget_usd' => 5.00,
    'delay_milliseconds' => 1000,
    'profile' => [
        'version' => '2.0.0',
        'provider' => 'openrouter',
        'wire_schema_version' => '1.0-relaxed',
        'temperature' => 0.1,
        'max_tokens' => 8192,
        'timeout_seconds' => 90,
        'streaming' => false,
        'response_healing' => false,
        'provider_options' => [
            'provider' => [
                'require_parameters' => false,
                'allow_fallbacks' => false,
                'data_collection' => 'deny',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter Model Candidates
    |--------------------------------------------------------------------------
    |
    | Pricing is expressed in USD per token and was captured from the
    | OpenRouter Models API on 2026-06-08. It is used for evaluation-only
    | budget guards and estimated run cost.
    |
    */
    'models' => [
        'anthropic/claude-opus-4.8' => [
            'prompt_price' => 0.000005,
            'completion_price' => 0.000025,
        ],
        'google/gemini-3.5-flash' => [
            'prompt_price' => 0.0000015,
            'completion_price' => 0.000009,
        ],
        'qwen/qwen3.7-max' => [
            'prompt_price' => 0.00000125,
            'completion_price' => 0.00000375,
        ],
        'google/gemini-3.1-flash-lite' => [
            'prompt_price' => 0.00000025,
            'completion_price' => 0.0000015,
        ],
        'google/gemini-3.1-pro-preview' => [
            'prompt_price' => 0.000002,
            'completion_price' => 0.000012,
        ],
        'google/gemini-3-flash-preview' => [
            'prompt_price' => 0.0000005,
            'completion_price' => 0.000003,
        ],
        'google/gemini-2.5-flash-lite' => [
            'prompt_price' => 0.0000001,
            'completion_price' => 0.0000004,
        ],
        'google/gemini-2.5-flash' => [
            'prompt_price' => 0.0000003,
            'completion_price' => 0.0000025,
        ],
        'openai/gpt-5.4-mini' => [
            'prompt_price' => 0.00000075,
            'completion_price' => 0.0000045,
        ],
        'deepseek/deepseek-v4-flash' => [
            'prompt_price' => 0.0000000983,
            'completion_price' => 0.0000001966,
        ],
        'mistralai/mistral-small-3.2-24b-instruct' => [
            'prompt_price' => 0.000000075,
            'completion_price' => 0.0000002,
        ],
        'google/gemma-4-31b-it' => [
            'prompt_price' => 0.00000012,
            'completion_price' => 0.00000036,
        ],
        'deepseek/deepseek-v4-pro' => [
            'prompt_price' => 0.000000435,
            'completion_price' => 0.00000087,
        ],
        'qwen/qwen3.6-35b-a3b' => [
            'prompt_price' => 0.00000014,
            'completion_price' => 0.000001,
        ],
        'moonshotai/kimi-k2.6' => [
            'prompt_price' => 0.00000068,
            'completion_price' => 0.00000341,
        ],
    ],
];

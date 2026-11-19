<?php

return [
    'prompt_version' => '1.2.1',
    'output_schema' => base_path('specs/033-metric-alignment-brief/evaluation/generated-draft-brief.schema.json'),

    'profile' => [
        'version' => '1.0.2',
        'provider' => 'openrouter',
        'model' => 'google/gemini-3-flash-preview',
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
];

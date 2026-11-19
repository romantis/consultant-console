<?php

use App\Support\Handbook\HandbookPdfDocument;
use Carbon\Carbon;

function makeHandbookPdfPayload(array $overrides = []): array
{
    $payload = [
        'organization' => [
            'name' => 'Acme Holdings',
        ],
        'generated_at' => Carbon::parse('2026-03-12 09:30:00'),
        'generated_by' => [
            'name' => 'Jordan Reviewer',
        ],
        'executive_brief' => [
            'engagement_id' => 'engagement-1',
            'engagement_title' => 'Board Pack Alignment',
            'starts_on' => '2026-02-01',
            'ends_on' => '2026-02-20',
            'total_metric_count' => 1,
            'approved_metric_count' => 1,
            'operational_ready_count' => 1,
            'needs_follow_up_count' => 0,
            'implementation_gap_count' => 1,
            'next_review_on' => '2026-06-30',
            'review_cadence' => 'Quarterly',
        ],
        'approvals' => [
            [
                'metric_name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by_name' => 'Avery Approver',
            ],
        ],
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'alignment_summary' => 'Finance and product aligned on ARR.',
                'executive_takeaway' => 'Authored ARR takeaway',
                'version' => 2,
                'operational_status' => 'operationally_ready',
                'operational_status_notes' => 'Signed dashboard matches approved logic.',
                'validation_steps' => 'Compare workbook totals to the board pack.',
                'common_mismatch_causes' => 'Late renewal backfills.',
                'implementation_gaps' => [
                    'current_state' => 'Manual month-end workbook check remains.',
                    'gap' => 'Warehouse filter update is still pending.',
                    'owner' => 'Revenue ops',
                    'suggested_next_step' => 'Ship the warehouse filter fix.',
                ],
                'fields' => [
                    [
                        'key' => 'owner_participant',
                        'label' => 'Metric Owner',
                        'value' => 'Taylor Finance',
                        'display_value' => 'Taylor Finance',
                    ],
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                        'display_value' => 'Approved ARR definition',
                    ],
                    [
                        'key' => 'summary_notes',
                        'label' => 'Summary notes',
                        'value' => 'Board-facing label agreed with finance.',
                        'display_value' => 'Board-facing label agreed with finance.',
                    ],
                    [
                        'key' => 'costs_included',
                        'label' => 'Costs included',
                        'value' => ['ad_spend', 'overhead'],
                        'display_value' => 'Paid media spend, Overhead',
                    ],
                ],
                'notes' => 'ARR official note',
                'official_definition_history' => [
                    [
                        'version' => 1,
                        'signed_off_at' => Carbon::parse('2026-02-28 11:00:00')->toIso8601String(),
                        'signed_off_by' => [
                            'name' => 'Morgan Lead',
                        ],
                    ],
                ],
            ],
        ],
    ];

    foreach (['approvals', 'metrics'] as $listKey) {
        if (array_key_exists($listKey, $overrides)) {
            $payload[$listKey] = $overrides[$listKey];
            unset($overrides[$listKey]);
        }
    }

    return array_replace_recursive($payload, $overrides);
}

it('builds render-ready front matter and metric sections from the payload', function () {
    $payload = makeHandbookPdfPayload();
    $document = HandbookPdfDocument::fromPayload($payload);

    expect($document->organizationName)->toBe('Acme Holdings')
        ->and($document->generatedBy)->toBe('Jordan Reviewer')
        ->and($document->generatedAt)->toBe($payload['generated_at']->toIso8601String())
        ->and($document->generatedDate)->toBe('2026-03-12')
        ->and($document->metadata)->toBe([
            'title' => 'Metrics Handbook - Acme Holdings',
            'subject' => 'Metrics handbook export for Board Pack Alignment',
            'author' => 'Jordan Reviewer',
            'creator' => 'Metric Harmony Console',
            'generated_at' => $payload['generated_at']->toIso8601String(),
        ])
        ->and($document->executiveBrief['engagement_title'])->toBe('Board Pack Alignment')
        ->and($document->executiveBrief['date_range'])->toBe('February 1-20, 2026')
        ->and($document->executiveBrief['total_metric_count'])->toBe(1)
        ->and($document->executiveBrief['implementation_gap_count'])->toBe(1)
        ->and($document->executiveBrief['next_review_on'])->toBe('June 30, 2026')
        ->and($document->executiveBrief['review_cadence'])->toBe('Quarterly')
        ->and($document->executiveMetricRows)->toBe([
            [
                'metric_name' => 'ARR',
                'status_label' => 'Ready for Use',
                'status_tone' => 'ready',
                'owner' => 'Taylor Finance',
                'next_step' => 'Ship the warehouse filter fix.',
                'version' => 'Version 2',
            ],
        ])
        ->and($document->metrics)->toHaveCount(1)
        ->and($document->approvals)->toHaveCount(1)
        ->and($document->revisionHistory)->toHaveCount(1);

    $metric = $document->metrics[0];

    expect($metric['name'])->toBe('ARR')
        ->and($metric['executive_takeaway'])->toBe('Authored ARR takeaway')
        ->and($metric['version'])->toBe('2')
        ->and($metric['signed_off_by'])->toBe('Avery Approver')
        ->and($metric['alignment_summary'])->toBe('Finance and product aligned on ARR.')
        ->and($metric['approval_version_rows'])->toBe([
            ['label' => 'Metric Owner', 'value' => 'Taylor Finance'],
            ['label' => 'Current Version', 'value' => 'Version 2'],
            ['label' => 'Signed Off On', 'value' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String()],
            ['label' => 'Approved By', 'value' => 'Avery Approver'],
        ])
        ->and($metric['approval_strip_segments'])->toBe([
            'Approved March 5, 2026',
            'Version 2',
            'Avery Approver',
        ])
        ->and($metric['operational_status_label'])->toBe('Ready for Use')
        ->and($metric['operational_status_notes'])->toBe('Signed dashboard matches approved logic.')
        ->and($metric['notes'])->toBe('ARR official note')
        ->and($metric['executive_sections'])->toHaveCount(1)
        ->and($metric['executive_sections'][0]['title'])->toBe('Official Definition')
        ->and($metric['executive_sections'][0]['items'])->toHaveCount(2)
        ->and($metric['validation_guidance_rows'])->toBe([
            ['label' => 'Validation steps', 'value' => 'Compare workbook totals to the board pack.'],
            ['label' => 'Common mismatch causes', 'value' => 'Late renewal backfills.'],
        ])
        ->and($metric['implementation_gap_rows'])->toBe([
            ['label' => 'Current state', 'value' => 'Manual month-end workbook check remains.'],
            ['label' => 'Gap', 'value' => 'Warehouse filter update is still pending.'],
            ['label' => 'Responsible Team', 'value' => 'Revenue ops'],
            ['label' => 'Suggested next step', 'value' => 'Ship the warehouse filter fix.'],
        ]);

    expect($document->revisionHistory[0]['name'])->toBe('ARR')
        ->and($document->revisionHistory[0]['history'])->toHaveCount(2)
        ->and($document->revisionHistory[0]['history'][0]['version'])->toBe(2)
        ->and($document->revisionHistory[0]['history'][0]['approved_by'])->toBe('Avery Approver')
        ->and($document->revisionHistory[0]['history'][1]['version'])->toBe(1)
        ->and($document->revisionHistory[0]['history'][1]['approved_by'])->toBe('Morgan Lead');

    expect(collect($metric['executive_sections'][0]['items'])->pluck('label')->all())
        ->toBe(['Summary', 'Costs included']);
});

it('uses the official definition version when it is present', function () {
    $payload = makeHandbookPdfPayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'version' => 3,
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                    ],
                ],
            ],
        ],
    ]);

    $document = HandbookPdfDocument::fromPayload($payload);

    expect($document->metrics[0]['version'])->toBe('3');
});

it('formats executive brief dates for customer-facing handbook copy', function () {
    $payload = makeHandbookPdfPayload([
        'executive_brief' => [
            'starts_on' => '2026-02-28',
            'ends_on' => '2026-03-03',
            'next_review_on' => '2026-07-15',
        ],
    ]);

    $document = HandbookPdfDocument::fromPayload($payload);

    expect($document->executiveBrief['date_range'])->toBe('February 28 - March 3, 2026')
        ->and($document->executiveBrief['next_review_on'])->toBe('July 15, 2026');
});

it('fills executive metric snapshot rows with placeholders when optional details are missing', function () {
    $payload = makeHandbookPdfPayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'version' => null,
                'operational_status' => null,
                'implementation_gaps' => null,
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                    ],
                ],
            ],
        ],
    ]);

    $document = HandbookPdfDocument::fromPayload($payload);

    expect($document->executiveMetricRows)->toBe([
        [
            'metric_name' => 'ARR',
            'status_label' => '—',
            'status_tone' => 'neutral',
            'owner' => '—',
            'next_step' => '—',
            'version' => '—',
        ],
    ]);
});

it('does not fall back to official definition fields if executive_takeaway is absent', function () {
    $payload = makeHandbookPdfPayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'fields' => [
                    [
                        'key' => 'owner_participant',
                        'label' => 'Metric Owner',
                        'value' => 'Taylor Finance',
                    ],
                    [
                        'key' => 'definition_basis',
                        'label' => 'Definition Basis',
                        'value' => 'Board-facing ARR excludes services revenue.',
                        'executive_section' => 'official_definition',
                    ],
                ],
            ],
        ],
    ]);

    $document = HandbookPdfDocument::fromPayload($payload);

    expect($document->metrics[0]['executive_takeaway'])->toBeNull();
});

it('omits empty optional sections while keeping the document renderable', function () {
    $document = HandbookPdfDocument::fromPayload(makeHandbookPdfPayload([
        'executive_brief' => [
            'engagement_title' => null,
            'starts_on' => null,
            'ends_on' => null,
            'next_review_on' => null,
            'review_cadence' => null,
            'implementation_gap_count' => 0,
        ],
        'approvals' => [],
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => null,
                'signed_off_by' => null,
                'alignment_summary' => null,
                'operational_status' => null,
                'operational_status_notes' => ' ',
                'validation_steps' => null,
                'common_mismatch_causes' => null,
                'implementation_gaps' => null,
                'fields' => [],
                'notes' => null,
                'official_definition_history' => [],
            ],
        ],
    ]));

    expect($document->executiveBrief['engagement_title'])->toBeNull()
        ->and($document->executiveBrief['date_range'])->toBeNull()
        ->and($document->executiveBrief['next_review_on'])->toBeNull()
        ->and($document->executiveBrief['review_cadence'])->toBeNull()
        ->and($document->metadata['subject'])->toBe('Metrics handbook export')
        ->and($document->generatedDate)->toBe('2026-03-12')
        ->and($document->approvals)->toBe([])
        ->and($document->revisionHistory)->toBe([]);

    $metric = $document->metrics[0];

    expect($metric['signed_off_at'])->toBe('Pending')
        ->and($metric['signed_off_by'])->toBe('—')
        ->and($metric['executive_takeaway'])->toBeNull()
        ->and($metric['approval_version_rows'])->toBe([])
        ->and($metric['approval_strip_segments'])->toBe([])
        ->and($metric['executive_sections'])->toBe([])
        ->and($metric['alignment_summary'])->toBeNull()
        ->and($metric['operational_status_label'])->toBeNull()
        ->and($metric['operational_status_notes'])->toBeNull()
        ->and($metric['validation_guidance_rows'])->toBe([])
        ->and($metric['implementation_gap_rows'])->toBe([])
        ->and($metric['notes'])->toBe('—');
});

it('groups fields into executive sections based on their executive_section key', function () {
    $payload = makeHandbookPdfPayload([
        'metrics' => [
            [
                'name' => 'LTV',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Lifetime value',
                        'executive_section' => 'official_definition',
                    ],
                    [
                        'key' => 'formula',
                        'label' => 'Formula',
                        'value' => 'ARPU * lifespan',
                        'executive_section' => 'measurement_logic_rules',
                    ],
                    [
                        'key' => 'caveats',
                        'label' => 'Caveats',
                        'value' => 'Excludes trial users',
                        'executive_section' => 'controls_caveats',
                    ],
                    [
                        'key' => 'unguided_field',
                        'label' => 'Unguided',
                        'value' => 'Falls back to official definition',
                    ],
                ],
            ],
        ],
    ]);

    $document = HandbookPdfDocument::fromPayload($payload);
    $metric = $document->metrics[0];

    expect($metric['executive_sections'])->toHaveCount(3)
        ->and($metric['executive_sections'][0]['title'])->toBe('Official Definition')
        ->and($metric['executive_sections'][0]['items'])->toHaveCount(2)
        ->and($metric['executive_sections'][0]['items'][0]['key'])->toBe('summary')
        ->and($metric['executive_sections'][0]['items'][1]['key'])->toBe('unguided_field')
        ->and($metric['executive_sections'][1]['title'])->toBe('Measurement Logic & Rules')
        ->and($metric['executive_sections'][1]['items'])->toHaveCount(1)
        ->and($metric['executive_sections'][1]['items'][0]['key'])->toBe('formula')
        ->and($metric['executive_sections'][2]['title'])->toBe('Controls & Caveats')
        ->and($metric['executive_sections'][2]['items'])->toHaveCount(1)
        ->and($metric['executive_sections'][2]['items'][0]['key'])->toBe('caveats');
});

it('normalizes repeated pain point field labels for the PDF presentation model', function () {
    $payload = makeHandbookPdfPayload([
        'metrics' => [
            [
                'name' => 'Gross Revenue Churn',
                'fields' => [
                    [
                        'key' => 'gross_revenue_churn_pain_points',
                        'label' => 'Gross Revenue Churn Churn Pain Points',
                        'value' => 'Customer success tracks logo churn operationally.',
                        'executive_section' => 'controls_caveats',
                    ],
                    [
                        'key' => 'mrr_pain_points',
                        'label' => 'Monthly Recurring Revenue (MRR) MRR Pain Points',
                        'value' => 'Product dashboards re-cut MRR daily.',
                        'executive_section' => 'controls_caveats',
                    ],
                    [
                        'key' => 'recognition_timing',
                        'label' => 'Churn Recognition Timing',
                        'value' => 'After the billing cycle ends',
                        'executive_section' => 'measurement_logic_rules',
                    ],
                ],
            ],
        ],
    ]);

    $document = HandbookPdfDocument::fromPayload($payload);
    $metric = $document->metrics[0];

    $labels = collect($metric['executive_sections'])
        ->flatMap(fn (array $section): array => $section['items'])
        ->pluck('label')
        ->all();

    expect($labels)->toBe([
        'Churn Recognition Timing',
        'Known Pain Points',
        'Known Pain Points',
    ]);
});

<?php

use App\Support\Handbook\HandbookPdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

function renderHandbookTemplate(array $payload): string
{
    $content = Blade::render('pdf.organization-handbook', [
        'document' => HandbookPdfDocument::fromPayload($payload),
    ]);

    return html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function makeMinimalHandbookTemplatePayload(array $overrides = []): array
{
    $payload = [
        'organization' => [
            'name' => 'Acme Holdings',
        ],
        'generated_at' => Carbon::parse('2026-03-12 09:30:00'),
        'generated_by' => [
            'name' => 'Jordan Reviewer',
        ],
        'next_review_on' => '2026-06-30',
        'review_cadence' => 'Quarterly',
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
                'version' => 2,
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'executive_takeaway' => 'Approved ARR definition',
                'alignment_summary' => 'Finance and product aligned on ARR after reconciling renewal timing and board labels.',
                'operational_status' => 'operationally_ready',
                'operational_status_notes' => 'The signed dashboard already matches the approved ARR logic.',
                'validation_steps' => 'Compare workbook totals to the board pack.',
                'common_mismatch_causes' => 'Late renewal backfills and internal-tenant rows can skew the board pack.',
                'implementation_gaps' => [
                    'current_state' => 'ARR still depends on one manual workbook check at month end.',
                    'gap' => 'The BI model needs a warehouse filter update for internal tenants.',
                    'owner' => 'Revenue ops',
                    'suggested_next_step' => 'Ship the warehouse filter fix before the next close.',
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
                        'key' => 'costs_included',
                        'label' => 'Costs included',
                        'value' => ['ad_spend', 'overhead'],
                        'display_value' => 'Paid media spend, Overhead (office, software, general expenses)',
                    ],
                ],
                'notes' => 'ARR official note',
                'official_definition_history' => [
                    [
                        'id' => 'official-previous-1',
                        'version' => 1,
                        'status' => 'Approved',
                        'signed_off_at' => Carbon::parse('2026-02-28 11:00:00')->toIso8601String(),
                        'signed_off_by' => [
                            'id' => 'user-2',
                            'name' => 'Morgan Lead',
                        ],
                        'metric' => [
                            'id' => 'metric-1',
                            'name' => 'ARR',
                            'engagement' => [
                                'id' => 'engagement-1',
                                'title' => 'Board Pack Alignment',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $merged = array_replace_recursive($payload, $overrides);

    foreach (['approvals', 'metrics'] as $listKey) {
        if (array_key_exists($listKey, $overrides)) {
            $merged[$listKey] = $overrides[$listKey];
        }
    }

    return $merged;
}

it('renders handbook front matter, per-metric sections, and back-matter appendices', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload());
    $executiveSnapshotPosition = strpos($rendered, 'Executive Metric Snapshot');
    $metricPosition = strpos($rendered, '<h3 id="metric-0">ARR</h3>');
    $approvalSummaryPosition = strpos($rendered, 'Approval Summary');
    $revisionHistoryPosition = strpos($rendered, 'Revision History Appendix');

    expect($rendered)
        ->toContain('<!DOCTYPE html>')
        ->toContain('<html lang="en">')
        ->toContain('<title>Metrics Handbook - Acme Holdings</title>')
        ->toContain("local('Inter Regular')")
        ->toContain("local('Inter SemiBold')")
        ->toContain("local('Inter Bold')")
        ->toContain('data:font/truetype;base64,')
        ->not->toContain('{{INTER_400_BASE64}}')
        ->not->toContain('{{INTER_600_BASE64}}')
        ->not->toContain('{{INTER_700_BASE64}}')
        ->toContain('<meta name="author" content="Jordan Reviewer">')
        ->toContain('<meta name="creator" content="Metric Harmony Console">')
        ->toContain('<meta name="subject" content="Metrics handbook export for Board Pack Alignment">')
        ->toContain('Metrics Handbook')
        ->not->toContain("section {\n            margin-top: 24px;\n            page-break-inside: avoid;")
        ->toContain("@page {\n            size: A4;\n            margin: 10mm 14mm 16mm;")
        ->toContain('content: "Metrics Handbook | Confidential";')
        ->toContain('content: counter(page);')
        ->toContain("@page :first {\n            margin: 0;")
        ->toContain("@bottom-left {\n                content: none;")
        ->toContain("main {\n            display: block;\n            max-width: 820px;\n            margin: 0 auto;\n            padding: 0 4px;")
        ->toContain("section {\n            margin-top: 18px;")
        ->toContain(".metric-card {\n            margin-top: 20px;")
        ->toContain(".metric-card__snapshot {\n            border: 1px solid var(--rule);")
        ->toContain(".executive-table-wrap {\n            margin-top: 10px;")
        ->toContain(".executive-table {\n            width: 100%;")
        ->toContain(".executive-table col.executive-table__col--metric {\n            width: 20%;")
        ->toContain(".executive-table col.executive-table__col--status {\n            width: 18%;")
        ->toContain(".executive-table col.executive-table__col--owner {\n            width: 16%;")
        ->toContain(".executive-table col.executive-table__col--next-step {\n            width: 36%;")
        ->toContain(".executive-table col.executive-table__col--version {\n            width: 10%;")
        ->toContain(".executive-table__metric-link {\n            color: var(--brand);\n            font-weight: 700;\n            text-decoration: none;")
        ->toContain(".summary-grid {\n            grid-template-columns: repeat(2, minmax(0, 1fr));\n            background: var(--lighter);\n            padding: 10px;")
        ->toContain(".metric-card__summary-panel {\n            background: var(--white);\n            border: 1px solid var(--rule);\n            border-radius: 0;\n            padding: 10px 12px;")
        ->toContain(".metric-card__title-block {\n            min-width: 0;\n            flex: 1 1 auto;")
        ->toContain(".metric-card__baseline-label {\n            flex: 0 0 auto;\n            text-align: right;")
        ->toContain(".metric-card__approval-meta {\n            display: flex;\n            flex-wrap: wrap;")
        ->toContain(".metric-card__approval-meta span + span::before {\n            content: \"-\";")
        ->toContain(".field-list article,\n        .signal-list article,\n        .companion-list article {\n            border-top: 1px solid var(--rule);\n            padding-top: 6px;\n            break-inside: avoid;\n            page-break-inside: avoid;")
        ->toContain(".executive-section-title {\n            color: var(--ink);\n            font-size: 10px;\n            font-weight: 700;\n            margin: 14px 0 6px;\n            padding-bottom: 4px;\n            padding-left: 10px;\n            border-bottom: none;\n            border-left: 3px solid var(--brand);\n            text-transform: uppercase;\n            letter-spacing: 0.06em;\n            break-after: avoid;\n            page-break-after: avoid;")
        ->toContain(".metric-detail-section--compact {\n            break-inside: avoid;\n            page-break-inside: avoid;")
        ->toContain(".appendix-section--start {\n            page-break-before: always;")
        ->toContain(".appendix-table-wrap {\n            border: none;")
        ->toContain('page-break-before: always;')
        ->toContain('page-break-inside: auto;')
        ->toContain('<dt>Organization</dt>')
        ->toContain('Acme Holdings')
        ->toContain('<dt>Generated</dt>')
        ->toContain('<dd>March 12, 2026</dd>')
        ->toContain('Board Pack Alignment')
        ->toContain('February 1-20, 2026')
        ->toContain('<dt>Cadence</dt>')
        ->toContain('Quarterly')
        ->not->toContain('<h2 id="executive-alignment-brief">Executive Alignment Brief</h2>')
        ->not->toContain('<dt>Engagement</dt>')
        ->not->toContain('<dt>Date Range</dt>')
        ->not->toContain('<h3 id="decisions-confirmed">Decisions Confirmed In This Engagement</h3>')
        ->not->toContain('<dt>Metrics in Scope</dt>')
        ->not->toContain('<dt>Approved Definitions</dt>')
        ->not->toContain('<dt>Ready for Use</dt>')
        ->not->toContain('<dt>Follow-up Needed</dt>')
        ->not->toContain('<dt>Open Implementation Gaps</dt>')
        ->toContain('<h2 id="executive-metric-snapshot">Executive Metric Snapshot</h2>')
        ->toContain('<table class="executive-table" aria-labelledby="executive-metric-snapshot">')
        ->toContain('<colgroup>')
        ->toContain('<col class="executive-table__col--metric">')
        ->toContain('<col class="executive-table__col--status">')
        ->toContain('<col class="executive-table__col--owner">')
        ->toContain('<col class="executive-table__col--next-step">')
        ->toContain('<col class="executive-table__col--version">')
        ->toContain('<th scope="col">Metric</th>')
        ->toContain('<th scope="col">Status</th>')
        ->toContain('<th scope="col">Metric Owner</th>')
        ->toContain('<th scope="col">Next Step</th>')
        ->toContain('<th scope="col">Version</th>')
        ->toContain('<td class="executive-table__metric">')
        ->toContain('<a class="executive-table__metric-link" href="#metric-0">ARR</a>')
        ->toContain('<span class="status-badge status-badge--ready">Ready for Use</span>')
        ->toContain('<td>Taylor Finance</td>')
        ->toContain('Ship the warehouse filter fix before the next close.')
        ->toContain('<td class="executive-table__version">Version 2</td>')
        ->toContain('<dt>Next Review</dt>')
        ->toContain('June 30, 2026')
        ->toContain('<aside class="executive-note" aria-label="handbook-provenance">')
        ->toContain('Metric Harmony records and renders those signed decisions; it does not generate definition content.')
        ->toContain('<aside class="executive-note" aria-label="implementation-status-boundary">')
        ->toContain("Implementation status reflects the organization's confirmed operating state and does not represent independent validation of source data or dashboard outputs by Metric Harmony.")
        ->toContain('<header class="metric-card__header">')
        ->toContain('<h3 id="metric-0">ARR</h3>')
        ->toContain('<p class="metric-card__eyebrow">Approved Metric Baseline</p>')
        ->toContain('<p class="metric-card__approval-meta">')
        ->toContain('<span>Approved March 5, 2026</span>')
        ->toContain('<span>Version 2</span>')
        ->toContain('<span>Avery Approver</span>')
        ->toContain('<p class="metric-card__headline">Approved ARR definition</p>')
        ->not->toContain('<h3 id="approval-version-0">Approval & Version</h3>')
        ->not->toContain('<p class="metric-card__approval-strip">')
        ->toContain('<h4 id="alignment-summary-0">Executive Alignment</h4>')
        ->toContain('Finance and product aligned on ARR after reconciling renewal timing and board labels.')
        ->toContain('<h4 id="operational-status-0">Implementation Status</h4>')
        ->toContain('<section class="metric-card__summary-panel metric-card__summary-panel--wide" aria-labelledby="implementation-gap-0">')
        ->toContain('<h4 id="implementation-gap-0">Implementation Follow-up</h4>')
        ->not->toContain('<section class="metric-card__summary-panel" aria-labelledby="validation-guidance-0">')
        ->toContain('<section class="metric-detail-section metric-detail-section--compact" aria-labelledby="validation-guidance-0">')
        ->toContain('<h4 id="validation-guidance-0" class="executive-section-title">Operational Validation Checklist</h4>')
        ->toContain('<section class="metric-detail-section metric-detail-section--compact" aria-labelledby="section-0-0">')
        ->toContain('<h4 id="section-0-0" class="executive-section-title">Official Definition</h4>')
        ->toContain('<dt>Summary</dt>')
        ->toContain('<dd>Approved ARR definition</dd>')
        ->toContain('<dt>Costs included</dt>')
        ->toContain('Paid media spend, Overhead (office, software, general expenses)')
        ->toContain('<dt>Status</dt>')
        ->toContain('status-badge status-badge--ready')
        ->toContain('Ready for Use')
        ->toContain('<dt>Notes</dt>')
        ->toContain('The signed dashboard already matches the approved ARR logic.')
        ->toContain('<dt>Validation steps</dt>')
        ->toContain('Compare workbook totals to the board pack.')
        ->toContain('<dt>Common mismatch causes</dt>')
        ->toContain('Late renewal backfills and internal-tenant rows can skew the board pack.')
        ->toContain('<dt>Current state</dt>')
        ->toContain('ARR still depends on one manual workbook check at month end.')
        ->toContain('<dt>Gap</dt>')
        ->toContain('The BI model needs a warehouse filter update for internal tenants.')
        ->toContain('<dt>Responsible Team</dt>')
        ->toContain('Revenue ops')
        ->toContain('<dt>Suggested next step</dt>')
        ->toContain('Ship the warehouse filter fix before the next close.')
        ->toContain('<h4 id="notes-0">Notes</h4>')
        ->toContain('<p>ARR official note</p>')
        ->toContain('<section class="appendix-section appendix-section--start" aria-labelledby="approval-summary">')
        ->toContain('<h2 id="approval-summary">Approval Summary</h2>')
        ->toContain('<table class="appendix-table">')
        ->toContain('<th scope="col">Metric</th>')
        ->toContain('<th scope="col">Approved on</th>')
        ->toContain('<th scope="col">Approved by</th>')
        ->toContain('<td class="appendix-table__metric">ARR</td>')
        ->toContain('<td>March 5, 2026</td>')
        ->toContain('<section class="appendix-section" aria-labelledby="revision-history-appendix">')
        ->toContain('<h2 id="revision-history-appendix">Revision History Appendix</h2>')
        ->toContain('<th scope="col">Version</th>')
        ->toContain('<td>2</td>')
        ->toContain('March 5, 2026')
        ->toContain('Avery Approver')
        ->toContain('<td>1</td>')
        ->toContain('<th scope="col">Approved on</th>')
        ->toContain('February 28, 2026')
        ->toContain('<th scope="col">Approved by</th>')
        ->toContain('Morgan Lead')
        ->not->toContain('## ARR')
        ->not->toContain('- Summary:')
        ->not->toContain('- Costs included:')
        ->not->toContain('<p class="lede">Status reflects the organization\'s confirmed implementation state. This does not represent independent validation of source data or dashboard outputs by Metric Harmony.</p>')
        ->not->toContain('class="appendix-entry"');

    expect(substr_count($rendered, '<dt>Metric Owner</dt>'))->toBe(0)
        ->and(substr_count($rendered, 'Taylor Finance'))->toBe(1);

    expect($executiveSnapshotPosition)->not->toBeFalse()
        ->and($metricPosition)->not->toBeFalse()
        ->and($approvalSummaryPosition)->not->toBeFalse()
        ->and($revisionHistoryPosition)->not->toBeFalse()
        ->and($executiveSnapshotPosition)->toBeLessThan($metricPosition)
        ->and($metricPosition)->toBeLessThan($approvalSummaryPosition)
        ->and($approvalSummaryPosition)->toBeLessThan($revisionHistoryPosition);

    expect(strpos($rendered, '<p class="metric-card__headline">Approved ARR definition</p>'))
        ->toBeLessThan(strpos($rendered, '<h4 id="section-0-0" class="executive-section-title">Official Definition</h4>'));

    expect(strpos($rendered, '<p class="metric-card__headline">Approved ARR definition</p>'))
        ->toBeLessThan(strpos($rendered, '<div class="metric-card__snapshot">'));

    expect(strpos($rendered, '<p class="metric-card__approval-meta">'))
        ->toBeLessThan(strpos($rendered, '<p class="metric-card__headline">Approved ARR definition</p>'));

    expect(strpos($rendered, '<h3 id="metric-0">ARR</h3>'))
        ->toBeLessThan(strpos($rendered, '<p class="metric-card__eyebrow">Approved Metric Baseline</p>'));

    expect(substr_count($rendered, "Implementation status reflects the organization's confirmed operating state and does not represent independent validation of source data or dashboard outputs by Metric Harmony."))
        ->toBe(1)
        ->and(strpos($rendered, '<aside class="executive-note" aria-label="implementation-status-boundary">'))
        ->toBeLessThan(strpos($rendered, '<h2 id="metric-sections">Metric Definitions</h2>'));

    expect(substr_count($rendered, '<aside class="executive-note" aria-label="handbook-provenance">'))
        ->toBe(1)
        ->and(strpos($rendered, '<aside class="executive-note" aria-label="handbook-provenance">'))
        ->toBeLessThan(strpos($rendered, '<aside class="executive-note" aria-label="implementation-status-boundary">'));

    expect(strpos($rendered, '<h3 id="decisions-confirmed">Decisions Confirmed In This Engagement</h3>'))
        ->toBeFalse();

    expect(strpos($rendered, '<h2 id="executive-metric-snapshot">Executive Metric Snapshot</h2>'))
        ->toBeLessThan(strpos($rendered, '<h2 id="metric-sections">Metric Definitions</h2>'));
});

it('omits single-engagement executive brief lines when the payload spans multiple engagements', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'next_review_on' => null,
        'review_cadence' => null,
        'executive_brief' => [
            'engagement_id' => null,
            'engagement_title' => null,
            'starts_on' => null,
            'ends_on' => null,
            'total_metric_count' => 2,
            'approved_metric_count' => 1,
            'operational_ready_count' => 1,
            'needs_follow_up_count' => 1,
            'implementation_gap_count' => 1,
            'next_review_on' => null,
            'review_cadence' => null,
        ],
    ]));

    expect($rendered)
        ->not->toContain('<h2 id="executive-alignment-brief">Executive Alignment Brief</h2>')
        ->not->toContain('<h3 id="decisions-confirmed">Decisions Confirmed In This Engagement</h3>')
        ->not->toContain('<dt>Metrics in Scope</dt>')
        ->not->toContain('<dt>Approved Definitions</dt>')
        ->not->toContain('<dt>Ready for Use</dt>')
        ->not->toContain('<dt>Follow-up Needed</dt>')
        ->not->toContain('<dt>Open Implementation Gaps</dt>')
        ->not->toContain('<dl class="summary-grid">')
        ->not->toContain('<dt>Next Review</dt>')
        ->not->toContain('<dt>Cadence</dt>');
});

it('omits empty per-metric consultant signal sections while keeping filled validation guidance fields', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'executive_brief' => [
            'implementation_gap_count' => 0,
        ],
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'executive_takeaway' => 'Approved ARR definition',
                'alignment_summary' => null,
                'operational_status' => null,
                'operational_status_notes' => '   ',
                'implementation_gaps' => null,
                'validation_steps' => 'Compare workbook totals to the board pack.',
                'common_mismatch_causes' => '',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                        'display_value' => 'Approved ARR definition',
                    ],
                ],
                'notes' => 'ARR official note',
            ],
        ],
    ]));

    expect($rendered)
        ->toContain('<h3 id="metric-0">ARR</h3>')
        ->toContain('<h2 id="executive-metric-snapshot">Executive Metric Snapshot</h2>')
        ->toContain('<td class="executive-table__metric">')
        ->toContain('<a class="executive-table__metric-link" href="#metric-0">ARR</a>')
        ->toContain('<p class="metric-card__headline">Approved ARR definition</p>')
        ->toContain('<p class="metric-card__approval-meta">')
        ->toContain('<span>Approved March 5, 2026</span>')
        ->toContain('<span>Avery Approver</span>')
        ->not->toContain('<h3 id="approval-version-0">Approval & Version</h3>')
        ->not->toContain('<p class="metric-card__approval-strip">')
        ->toContain('<section class="metric-detail-section metric-detail-section--compact" aria-labelledby="validation-guidance-0">')
        ->toContain('<h4 id="validation-guidance-0" class="executive-section-title">Operational Validation Checklist</h4>')
        ->toContain('<dt>Validation steps</dt>')
        ->toContain('Compare workbook totals to the board pack.')
        ->toContain('<p>ARR official note</p>')
        ->not->toContain('<dt>Current Version</dt>')
        ->not->toContain('<dt>Metric Owner</dt>')
        ->not->toContain('<h4 id="alignment-summary-0">Executive Alignment</h4>')
        ->not->toContain('<dd>Ready for Use</dd>')
        ->not->toContain('<dd>Implementation Follow-up Needed</dd>')
        ->not->toContain('<span class="status-badge status-badge--follow-up">Implementation Follow-up Needed</span>')
        ->not->toContain('<h4 id="implementation-gap-0">Implementation Follow-up</h4>')
        ->not->toContain('<dt>Common Mismatch Causes</dt>')
        ->not->toContain('<aside class="executive-note" aria-label="implementation-status-boundary">')
        ->toContain('<td>—</td>');
});

it('keeps revision history appendix when only the current signed version exists', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'approvals' => [],
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'alignment_summary' => 'Finance and product aligned on ARR after reconciling renewal timing and board labels.',
                'operational_status' => 'operationally_ready',
                'operational_status_notes' => 'The signed dashboard already matches the approved ARR logic.',
                'implementation_gaps' => [
                    'current_state' => 'ARR still depends on one manual workbook check at month end.',
                    'gap' => 'The BI model needs a warehouse filter update for internal tenants.',
                    'owner' => 'Revenue ops',
                    'suggested_next_step' => 'Ship the warehouse filter fix before the next close.',
                ],
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                        'display_value' => 'Approved ARR definition',
                    ],
                ],
                'notes' => 'ARR official note',
                'official_definition_history' => [],
            ],
        ],
    ]));

    expect($rendered)
        ->toContain('<h3 id="metric-0">ARR</h3>')
        ->not->toContain('<h2 id="approval-summary">Approval Summary</h2>')
        ->toContain('<h2 id="revision-history-appendix">Revision History Appendix</h2>')
        ->toContain('<th scope="col">Approved on</th>')
        ->toContain('<th scope="col">Approved by</th>')
        ->toContain('<td>March 5, 2026</td>')
        ->toContain('<td>Avery Approver</td>');
});

it('starts revision history on the first appendix page when no approval summary is present', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'approvals' => [],
    ]));

    expect($rendered)
        ->not->toContain('<section class="appendix-section appendix-section--start" aria-labelledby="approval-summary">')
        ->toContain('<section class="appendix-section appendix-section--start" aria-labelledby="revision-history-appendix">')
        ->toContain('<h2 id="revision-history-appendix">Revision History Appendix</h2>');
});

it('renders metric version only when a version is available', function () {
    $withVersion = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'version' => 2,
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                    ],
                ],
            ],
        ],
    ]));

    $withoutVersion = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'version' => null,
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                    ],
                ],
            ],
        ],
    ]));

    expect($withVersion)
        ->toContain('<span>Version 2</span>')
        ->toContain('<p class="metric-card__approval-meta">')
        ->and($withoutVersion)
        ->not->toContain('<span>Version 2</span>');
});

it('renders the handbook blade template empty state when no metrics are available', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'next_review_on' => null,
        'review_cadence' => null,
        'executive_brief' => [
            'engagement_id' => null,
            'engagement_title' => null,
            'starts_on' => null,
            'ends_on' => null,
            'total_metric_count' => 0,
            'approved_metric_count' => 0,
            'operational_ready_count' => 0,
            'needs_follow_up_count' => 0,
            'implementation_gap_count' => 0,
            'next_review_on' => null,
            'review_cadence' => null,
        ],
        'approvals' => [],
        'metrics' => [],
    ]));

    expect($rendered)
        ->toContain('Metrics Handbook')
        ->not->toContain('<h2 id="executive-alignment-brief">Executive Alignment Brief</h2>')
        ->not->toContain('<h3 id="decisions-confirmed">Decisions Confirmed In This Engagement</h3>')
        ->not->toContain('<dt>Metrics in Scope</dt>')
        ->not->toContain('<dl class="summary-grid">')
        ->toContain('No finalized metrics were available when this handbook was generated.')
        ->not->toContain('<h3 id="metric-0">ARR</h3>')
        ->not->toContain('<h2 id="executive-metric-snapshot">Executive Metric Snapshot</h2>');
});

it('renders long narrative fields as regular wrapping blocks instead of pseudo-list lines', function () {
    $longNarrative = str_repeat(
        'This handbook paragraph captures a long alignment narrative for board review and should wrap as normal prose in the PDF layout. ',
        8
    );
    $trimmedNarrative = rtrim($longNarrative);

    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'alignment_summary' => $longNarrative,
                'operational_status' => 'operationally_ready',
                'operational_status_notes' => $longNarrative,
                'validation_steps' => $longNarrative,
                'common_mismatch_causes' => null,
                'implementation_gaps' => [
                    'current_state' => $longNarrative,
                ],
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => $longNarrative,
                        'display_value' => $longNarrative,
                    ],
                ],
                'notes' => $longNarrative,
                'official_definition_history' => [],
            ],
        ],
    ]));

    expect($rendered)
        ->toContain('<h4 id="alignment-summary-0">Executive Alignment</h4>')
        ->toContain('<h4 id="notes-0">Notes</h4>')
        ->toContain('<dt>Summary</dt>')
        ->toContain($trimmedNarrative)
        ->toContain('overflow-wrap: break-word;')
        ->toContain('word-break: normal;')
        ->not->toContain('  '.$trimmedNarrative)
        ->not->toContain('- '.$trimmedNarrative);
});

it('renders captured decision rationale only for official-definition fields that include it', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'version' => 2,
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'executive_takeaway' => 'Approved ARR definition',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                        'display_value' => 'Approved ARR definition',
                        'rationale' => 'Finance and RevOps agreed on one board-facing summary line.',
                    ],
                    [
                        'key' => 'costs_included',
                        'label' => 'Costs included',
                        'value' => ['ad_spend', 'overhead'],
                        'display_value' => 'Paid media spend, Overhead (office, software, general expenses)',
                    ],
                    [
                        'key' => 'formula',
                        'label' => 'Formula',
                        'value' => 'Starting ARR + expansion - churn',
                        'display_value' => 'Starting ARR + expansion - churn',
                        'executive_section' => 'measurement_logic_rules',
                        'rationale' => 'This should stay out of the Official Definition section.',
                    ],
                ],
                'notes' => 'ARR official note',
                'official_definition_history' => [],
            ],
        ],
    ]));

    expect($rendered)
        ->toContain('<dt>Decision rationale</dt>')
        ->toContain('<dd>Finance and RevOps agreed on one board-facing summary line.</dd>')
        ->not->toContain('This should stay out of the Official Definition section.');

    expect(substr_count($rendered, '<dt>Decision rationale</dt>'))->toBe(1);
});

it('omits the metric card headline when executive_takeaway is null or absent', function () {
    $rendered = renderHandbookTemplate(makeMinimalHandbookTemplatePayload([
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'executive_takeaway' => null,
                'alignment_summary' => 'Some alignment summary',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                        'display_value' => 'Approved ARR definition',
                    ],
                ],
            ],
        ],
    ]));

    expect($rendered)
        ->toContain('<h3 id="metric-0">ARR</h3>')
        ->toContain('class="metric-card__header metric-card__header--no-takeaway"')
        ->not->toContain('class="metric-card__headline"');
});

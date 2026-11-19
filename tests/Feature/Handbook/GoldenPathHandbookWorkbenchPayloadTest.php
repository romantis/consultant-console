<?php

use App\Actions\HandbookWorkbench\BuildHandbookWorkbenchPayload;
use App\Models\Organization;
use Database\Seeders\MetricTemplateSeeder;
use Database\Seeders\StorySeeds\GoldenPathExecutiveArtifactStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the golden-path handbook workbench payload with field decisions in workshop source context', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->firstOrFail();

    $engagement = $organization->engagements()->where('title', 'Unit Economics Board Baseline')->firstOrFail();

    $payload = app(BuildHandbookWorkbenchPayload::class)->handle($organization, $engagement);

    $metrics = collect($payload['metrics']);
    $cacCpl = $metrics->firstWhere('name', 'CAC and CPL');

    expect($cacCpl)->not->toBeNull()
        ->and($cacCpl['is_signed_off'])->toBeTrue()
        ->and($cacCpl['source_context'])->not->toBeNull();

    $workshopAlignment = $cacCpl['source_context']['workshop_alignment'];
    expect($workshopAlignment['has_evidence'])->toBeTrue()
        ->and($workshopAlignment['workshop'])->not->toBeNull();

    $decisions = $workshopAlignment['workshop']['decisions'];
    expect($decisions['consensus'])->toBe('Board CAC/CPL uses fully loaded acquisition spend, SQL leads, first-paying customers, and cohort timing.')
        ->and($decisions['field_decisions'])->toHaveCount(6)
        ->and($decisions['field_decisions']['lead_definition']['selected'])->toBe('sales_qualified')
        ->and($decisions['field_decisions']['deduplication_method']['selected'])->toBe('unify_by_person')
        ->and($decisions['field_decisions']['overhead_handling']['selected'])->toBe('fully_loaded');

    $discrepancies = collect($workshopAlignment['discrepancies'])->keyBy('field_key');

    expect($discrepancies)->toHaveCount(6)
        ->and($discrepancies['lead_definition']['status'])->toBe('Resolved')
        ->and($discrepancies['lead_definition']['description'])->toContain('CPL denominator')
        ->and($discrepancies['overhead_handling']['status'])->toBe('Resolved');

    $grossChurn = $metrics->firstWhere('name', 'Gross Revenue Churn');

    expect($grossChurn)->not->toBeNull()
        ->and($grossChurn['is_signed_off'])->toBeTrue()
        ->and($grossChurn['source_context'])->not->toBeNull();

    $churnWorkshopAlignment = $grossChurn['source_context']['workshop_alignment'];
    expect($churnWorkshopAlignment['has_evidence'])->toBeTrue()
        ->and($churnWorkshopAlignment['workshop'])->not->toBeNull();

    $churnDecisions = $churnWorkshopAlignment['workshop']['decisions'];
    expect($churnDecisions['consensus'])->toBe('Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; downgrade reductions are included to remain conservative.')
        ->and($churnDecisions['field_decisions'])->toHaveCount(5)
        ->and($churnDecisions['field_decisions']['downgrade_handling']['selected'])->toBe('count_as_churn')
        ->and($churnDecisions['field_decisions']['churn_timing']['selected'])->toBe('end_of_cycle');

    $churnDiscrepancies = collect($churnWorkshopAlignment['discrepancies'])->keyBy('field_key');

    expect($churnDiscrepancies)->toHaveCount(4)
        ->and($churnDiscrepancies['churn_timing']['status'])->toBe('Resolved')
        ->and($churnDiscrepancies['churn_timing']['description'])->toContain('Finance uses end of cycle, RevOps immediate')
        ->and($churnDiscrepancies['downgrade_handling']['status'])->toBe('Resolved');

    $mrr = $metrics->firstWhere('name', 'Monthly Recurring Revenue (MRR)');

    expect($mrr)->not->toBeNull()
        ->and($mrr['is_signed_off'])->toBeTrue()
        ->and($mrr['signals']['operational_status'])->toBe('operationally_ready')
        ->and($mrr['source_context'])->not->toBeNull();

    $mrrWorkshopAlignment = $mrr['source_context']['workshop_alignment'];
    expect($mrrWorkshopAlignment['has_evidence'])->toBeTrue()
        ->and($mrrWorkshopAlignment['workshop'])->not->toBeNull();

    $mrrDecisions = $mrrWorkshopAlignment['workshop']['decisions'];
    expect($mrrDecisions['consensus'])->toBe('Board MRR baseline is defined as the locked month-end close pack, with annual deals prorated and usage/overage fees excluded.')
        ->and($mrrDecisions['field_decisions'])->toHaveCount(7)
        ->and($mrrDecisions['field_decisions']['mrr_snapshot_timing']['selected'])->toBe('last_calendar_day')
        ->and($mrrDecisions['field_decisions']['annual_deal_handling']['selected'])->toBe('normalized_contract_value')
        ->and($mrrDecisions['field_decisions']['discount_handling']['selected'])->toBe('net_recurring_price')
        ->and($mrrDecisions['field_decisions']['mid_month_changes']['selected'])->toBe('wait_next_cycle')
        ->and($mrrDecisions['field_decisions']['churn_timing']['selected'])->toBe('paid_through_date')
        ->and($mrrDecisions['field_decisions']['trial_handling']['selected'])->toBe('exclude_until_payment')
        ->and($mrrDecisions['field_decisions']['included_revenue_streams']['selected'])->toBe([
            'recurring_subscription_charges',
        ]);

    $mrrDiscrepancies = collect($mrrWorkshopAlignment['discrepancies'])->keyBy('field_key');

    expect($mrrDiscrepancies)->toHaveCount(4)
        ->and($mrrDiscrepancies['mrr_snapshot_timing']['status'])->toBe('Resolved')
        ->and($mrrDiscrepancies['mrr_snapshot_timing']['description'])->toContain('Finance requires month-end cutoff while RevOps uses a rolling daily view.')
        ->and($mrrDiscrepancies['annual_deal_handling']['status'])->toBe('Resolved')
        ->and($mrrDiscrepancies['mid_month_changes']['status'])->toBe('Resolved')
        ->and($mrrDiscrepancies['included_revenue_streams']['status'])->toBe('Resolved');
});

it('builds the matrix leftovers correctly for CAC and CPL metrics', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->firstOrFail();

    $engagement = $organization->engagements()->where('title', 'Unit Economics Board Baseline')->firstOrFail();

    $payload = app(BuildHandbookWorkbenchPayload::class)->handle($organization, $engagement);

    $metrics = collect($payload['metrics']);
    $cacCpl = $metrics->firstWhere('name', 'CAC and CPL');

    expect($cacCpl)->not->toBeNull()
        ->and($cacCpl['source_context'])->not->toBeNull()
        ->and($cacCpl['source_context'])->toHaveKey('matrix_leftovers');

    $leftovers = collect($cacCpl['source_context']['matrix_leftovers'])->keyBy('field_key');

    // 1. Assert lead_definition leftovers are correct
    expect($leftovers)->toHaveKey('lead_definition');
    $leadLeftover = $leftovers['lead_definition'];
    expect($leadLeftover['label'])->toBe('CPL lead definition')
        ->and($leadLeftover['final_value'])->toBe('sales_qualified')
        ->and($leadLeftover['status'])->toBe('rejected')
        ->and($leadLeftover['used_for'])->toEqual(['common_mismatch_causes', 'validation']);

    $leadPositions = collect($leadLeftover['stakeholder_positions'])->keyBy('stakeholder');
    expect($leadPositions)->toHaveKey('Finance')
        ->and($leadPositions['Finance']['raw_value'])->toBe('marketing_qualified')
        ->and($leadPositions)->toHaveKey('Marketing')
        ->and($leadPositions['Marketing']['raw_value'])->toBe('any_contact');

    // 2. Assert costs_included leftovers are correct
    expect($leftovers)->toHaveKey('costs_included');
    $costsLeftover = $leftovers['costs_included'];
    expect($costsLeftover['label'])->toBe('CAC/CPL cost components included')
        ->and($costsLeftover['final_value'])->toEqual(['ad_spend', 'marketing_comp', 'sales_comp', 'overhead'])
        ->and($costsLeftover['status'])->toBe('preserved_operating_view')
        ->and($costsLeftover['used_for'])->toEqual(['implementation_gaps', 'common_mismatch_causes']);

    // 3. Assert overhead_handling leftovers are correct
    expect($leftovers)->toHaveKey('overhead_handling');
    $overheadLeftover = $leftovers['overhead_handling'];
    expect($overheadLeftover['label'])->toBe('Overhead and indirect cost treatment')
        ->and($overheadLeftover['final_value'])->toBe('fully_loaded')
        ->and($overheadLeftover['status'])->toBe('preserved_operating_view');

    // 4. Assert deduplication_method leftovers are correct
    expect($leftovers)->toHaveKey('deduplication_method');
    $dedupeLeftover = $leftovers['deduplication_method'];
    expect($dedupeLeftover['label'])->toBe('Cross-campaign deduplication method')
        ->and($dedupeLeftover['final_value'])->toBe('unify_by_person')
        ->and($dedupeLeftover['status'])->toBe('rejected')
        ->and($dedupeLeftover['used_for'])->toEqual(['validation']);

    $dedupePositions = collect($dedupeLeftover['stakeholder_positions'])->keyBy('stakeholder');
    expect($dedupePositions)->toHaveKey('Marketing')
        ->and($dedupePositions['Marketing']['raw_value'])->toBe('count_all_touches');
});

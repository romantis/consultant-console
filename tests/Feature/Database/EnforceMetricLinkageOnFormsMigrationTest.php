<?php

use App\Models\Engagement;
use App\Models\Form;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enforceMetricLinkageOnFormsMigration(): Migration
{
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_03_10_120000_enforce_metric_linkage_on_forms.php');

    return $migration;
}

it('prunes legacy forms without metric links before enforcing constraints', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $migration = enforceMetricLinkageOnFormsMigration();
    $migration->down();

    $form = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => null,
        'template_key' => 'mrr',
        'title' => 'Monthly Recurring Revenue — Definition',
        'schema_json' => ['fields' => []],
        'status' => 'Open',
    ]);

    $migration->up();

    $this->assertDatabaseMissing('forms', [
        'id' => $form->id,
    ]);
});

it('keeps only the newest legacy form when multiple forms point to the same metric', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $template = MetricTemplate::factory()->create([
        'key' => 'retention',
        'name' => 'Retention',
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_template_id' => $template->id,
        'key' => 'retention-finance',
        'name' => 'Finance Retention',
    ]);

    $migration = enforceMetricLinkageOnFormsMigration();
    $migration->down();

    $olderForm = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'retention',
        'title' => 'Retention - Older',
        'schema_json' => ['fields' => []],
        'status' => 'Open',
    ]);

    $newerForm = Form::create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'retention',
        'title' => 'Retention - Newer',
        'schema_json' => ['fields' => []],
        'status' => 'Open',
    ]);

    $olderForm->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->save();

    $newerForm->forceFill([
        'created_at' => now(),
        'updated_at' => now(),
    ])->save();

    $migration->up();

    $this->assertDatabaseMissing('forms', [
        'id' => $olderForm->id,
    ]);

    $this->assertDatabaseHas('forms', [
        'id' => $newerForm->id,
        'metric_id' => $metric->id,
    ]);
});

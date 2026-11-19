<?php

namespace App\Console\Commands;

use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationRunner;
use Illuminate\Console\Command;
use RuntimeException;

class EvaluateMetricAlignmentDraft extends Command
{
    protected $signature = 'mh:evaluate-metric-alignment-draft
        {--model=* : Explicit OpenRouter model IDs; defaults to all configured candidates}
        {--case=* : Dataset case IDs; defaults to every case}
        {--repetitions= : Number of runs per model and fixture}
        {--budget= : Maximum allowed estimated spend in USD}
        {--output= : Artifact directory under the project root}
        {--delay-ms= : Delay between paid requests to reduce provider rate-limit noise}
        {--execute : Perform paid OpenRouter requests; omitted means dry-run only}';

    protected $description = 'Compare structured metric-alignment draft models against the synthetic evaluation dataset.';

    public function handle(MetricAlignmentEvaluationRunner $runner): int
    {
        $models = $this->option('model');
        $models = $models === [] ? array_keys(config('metric-alignment-evaluation.models', [])) : $models;
        $caseIds = $this->option('case');
        $repetitions = (int) ($this->option('repetitions') ?: config('metric-alignment-evaluation.repetitions', 3));
        $budget = (float) ($this->option('budget') ?: config('metric-alignment-evaluation.budget_usd', 5.0));
        $execute = (bool) $this->option('execute');
        $delayMilliseconds = (int) ($this->option('delay-ms') ?: config('metric-alignment-evaluation.delay_milliseconds', 1000));
        $output = $this->option('output') ?: 'tmp/metric-alignment-evaluation/'.now()->format('Ymd-His');
        $outputDirectory = base_path($output);

        if ($repetitions < 1 || $budget <= 0) {
            $this->error('Repetitions and budget must be greater than zero.');

            return self::INVALID;
        }

        try {
            $report = $runner->run(
                $models,
                $repetitions,
                $budget,
                $execute,
                $outputDirectory,
                $delayMilliseconds,
                $caseIds,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Mode', 'Models', 'Fixtures x repetitions', 'Maximum estimated cost', 'Budget'],
            [[
                $execute ? 'execute' : 'dry-run',
                count($models),
                $report['case_count'].' x '.$repetitions,
                '$'.number_format((float) $report['maximum_estimated_cost_usd'], 4),
                '$'.number_format($budget, 2),
            ]],
        );

        if (! $execute) {
            $this->info('Budget guard passed. Re-run with --execute to perform paid requests.');

            return self::SUCCESS;
        }

        $passed = collect($report['runs'])->where('status', 'passed')->count();
        $failed = count($report['runs']) - $passed;

        $this->info("Evaluation complete: {$passed} passed, {$failed} failed.");
        $this->line("Artifacts: {$outputDirectory}/results.json");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

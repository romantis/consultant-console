<?php

namespace App\Console\Commands;

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Actions\Handbook\SelectOfficialMetrics;
use App\Models\Organization;
use App\Models\User;
use App\Support\Handbook\HandbookPdfDocument;
use App\Support\Handbook\HandbookPdfRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class RenderHandbookPdfPreviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:render-handbook-pdf 
                            {--org-slug= : The slug of the organization} 
                            {--actor= : The email of the actor (optional)} 
                            {--output= : The output path for the PDF (default: tmp/{slug}-handbook.pdf)} 
                            {--html= : Optional output path for the rendered HTML} 
                            {--force : Run outside of local environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Render a handbook PDF preview locally without using the export cache';

    /**
     * Execute the console command.
     */
    public function handle(
        SelectOfficialMetrics $officialSelector,
        BuildHandbookExportPayload $payloadBuilder,
        HandbookPdfRenderer $renderer
    ) {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('This command is for local development only. Use --force to override.');

            return Command::FAILURE;
        }

        $orgSlug = $this->option('org-slug');
        if (! $orgSlug) {
            $this->error('The --org-slug option is required.');

            return Command::FAILURE;
        }

        $organization = Organization::where('slug', $orgSlug)->first();
        if (! $organization) {
            $this->error("Organization with slug '{$orgSlug}' not found.");

            return Command::FAILURE;
        }

        $actorEmail = $this->option('actor');
        $actor = null;
        if ($actorEmail) {
            $actor = User::where('email', $actorEmail)->first();
            if (! $actor) {
                $this->error("Actor with email '{$actorEmail}' not found.");

                return Command::FAILURE;
            }
        }

        $this->info("Building payload for organization: {$organization->name}");
        $metrics = $officialSelector->handle($organization);

        if ($metrics->isEmpty()) {
            $this->warn('No finalized official metrics found for this organization.');
        } else {
            $this->info("Found {$metrics->count()} finalized metrics.");
        }

        $bundle = $payloadBuilder->build($organization, $metrics, $actor);
        $payload = $bundle['payload'];

        $outputPath = $this->option('output') ?: "tmp/{$organization->slug}-handbook.pdf";
        $htmlPath = $this->option('html');

        $this->info('Rendering PDF...');
        $pdfBytes = $renderer->render('pdf.organization-handbook', $payload);

        $fullOutputPath = base_path($outputPath);
        $outputDir = dirname($fullOutputPath);
        if (! File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        File::put($fullOutputPath, $pdfBytes);
        $this->info("PDF saved to: {$outputPath}");

        if ($htmlPath) {
            $this->info('Rendering HTML...');
            $document = HandbookPdfDocument::fromPayload($payload);
            $html = View::make('pdf.organization-handbook', ['document' => $document])->render();

            $fullHtmlPath = base_path($htmlPath);
            $htmlDir = dirname($fullHtmlPath);
            if (! File::exists($htmlDir)) {
                File::makeDirectory($htmlDir, 0755, true);
            }

            File::put($fullHtmlPath, $html);
            $this->info("HTML saved to: {$htmlPath}");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace Tests\Feature\Handbook;

use App\Models\HandbookExport;
use App\Models\Organization;
use App\Models\User;
use App\Support\Handbook\HandbookPdfRenderer;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

it('requires local environment', function () {
    app()['env'] = 'production';

    $this->artisan('dev:render-handbook-pdf', ['--org-slug' => 'test-org'])
        ->expectsOutput('This command is for local development only. Use --force to override.')
        ->assertFailed();

    app()['env'] = 'testing';
});

it('can be forced outside of local environment', function () {
    app()['env'] = 'production';

    $this->artisan('dev:render-handbook-pdf', ['--org-slug' => 'non-existent', '--force' => true])
        ->expectsOutput("Organization with slug 'non-existent' not found.")
        ->assertFailed();

    app()['env'] = 'testing';
});

it('requires a valid organization slug', function () {
    $this->artisan('dev:render-handbook-pdf', ['--org-slug' => 'invalid-slug', '--force' => true])
        ->expectsOutput("Organization with slug 'invalid-slug' not found.")
        ->assertFailed();
});

it('requires a valid actor email if provided', function () {
    $org = Organization::factory()->create();

    $this->artisan('dev:render-handbook-pdf', [
        '--org-slug' => $org->slug,
        '--actor' => 'invalid@example.com',
        '--force' => true,
    ])
        ->expectsOutput("Actor with email 'invalid@example.com' not found.")
        ->assertFailed();
});

it('renders handbook pdf preview', function () {
    $org = Organization::factory()->create();
    $actor = User::factory()->create();

    $outputPath = 'tmp/test-handbook.pdf';
    $htmlPath = 'tmp/test-handbook.html';

    $fullOutputPath = base_path($outputPath);
    $fullHtmlPath = base_path($htmlPath);

    if (File::exists($fullOutputPath)) {
        File::delete($fullOutputPath);
    }
    if (File::exists($fullHtmlPath)) {
        File::delete($fullHtmlPath);
    }

    $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->with('pdf.organization-handbook', \Mockery::type('array'))
            ->andReturn('%PDF-1.4 dummy content');
    });

    $initialExportCount = HandbookExport::count();

    $this->artisan('dev:render-handbook-pdf', [
        '--org-slug' => $org->slug,
        '--actor' => $actor->email,
        '--output' => $outputPath,
        '--html' => $htmlPath,
        '--force' => true,
    ])
        ->expectsOutput("PDF saved to: {$outputPath}")
        ->expectsOutput("HTML saved to: {$htmlPath}")
        ->assertSuccessful();

    expect(File::exists($fullOutputPath))->toBeTrue()
        ->and(File::get($fullOutputPath))->toBe('%PDF-1.4 dummy content');

    expect(File::exists($fullHtmlPath))->toBeTrue();

    // Ensure no handbook export row was created
    expect(HandbookExport::count())->toBe($initialExportCount);

    // Cleanup
    File::delete($fullOutputPath);
    File::delete($fullHtmlPath);
});

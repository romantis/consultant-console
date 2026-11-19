<?php

use App\Support\Handbook\HandbookPdfDocument;
use App\Support\Handbook\HandbookPdfGenerator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Exceptions\CouldNotGeneratePdf;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class);

function makeHandbookGeneratorPayload(array $overrides = []): array
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
            'engagement_title' => 'Board Pack Alignment',
        ],
        'metrics' => [
            [
                'name' => 'ARR',
                'signed_off_at' => Carbon::parse('2026-03-05 15:00:00')->toIso8601String(),
                'signed_off_by' => 'Avery Approver',
                'fields' => [
                    [
                        'key' => 'summary',
                        'label' => 'Summary',
                        'value' => 'Approved ARR definition',
                        'display_value' => 'Approved ARR definition',
                    ],
                ],
                'official_definition_history' => [],
            ],
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}

it('builds a spatie pdf with handbook metadata', function () {
    Config::set('laravel-pdf.driver', 'cloudflare');
    Config::set('laravel-pdf.cloudflare.api_token', 'cloudflare-token');
    Config::set('laravel-pdf.cloudflare.account_id', 'cloudflare-account');

    $builder = new class
    {
        public string $name = '';

        public mixed $format = null;

        public array $meta = [];

        public bool $tagged = false;

        public function name(string $name): self
        {
            $this->name = $name;

            return $this;
        }

        public function format(mixed $format): self
        {
            $this->format = $format;

            return $this;
        }

        public function meta(
            ?string $title = null,
            ?string $author = null,
            ?string $subject = null,
            ?string $keywords = null,
            ?string $creator = null,
            mixed $creationDate = null
        ): self {
            $this->meta = compact('title', 'author', 'subject', 'keywords', 'creator', 'creationDate');

            return $this;
        }

        public function tagged(): self
        {
            $this->tagged = true;

            return $this;
        }

        public function base64(): string
        {
            return base64_encode("%PDF-1.7\nmock handbook");
        }
    };

    $factory = new class($builder)
    {
        public string $view = '';

        public array $data = [];

        public function __construct(public object $builder) {}

        public function view(string $view, array $data = []): object
        {
            $this->view = $view;
            $this->data = $data;

            return $this->builder;
        }
    };

    Pdf::swap($factory);

    $pdf = app(HandbookPdfGenerator::class)->render('pdf.organization-handbook', makeHandbookGeneratorPayload());

    expect($pdf)->toBe("%PDF-1.7\nmock handbook")
        ->and($factory->view)->toBe('pdf.organization-handbook')
        ->and($factory->data['document'])->toBeInstanceOf(HandbookPdfDocument::class)
        ->and($factory->data['document']->metadata['title'])->toBe('Metrics Handbook - Acme Holdings')
        ->and($builder->name)->toBe('metrics-handbook.pdf')
        ->and($builder->format)->toBe(Format::A4)
        ->and($builder->meta['title'])->toBe('Metrics Handbook - Acme Holdings')
        ->and($builder->meta['author'])->toBe('Jordan Reviewer')
        ->and($builder->meta['subject'])->toBe('Metrics handbook export for Board Pack Alignment')
        ->and($builder->meta['keywords'])->toBeNull()
        ->and($builder->meta['creator'])->toBe('Metric Harmony Console')
        ->and($builder->meta['creationDate'])->toBeInstanceOf(CarbonImmutable::class)
        ->and($builder->tagged)->toBeTrue();
});

it('skips metadata rewrite for the local chrome driver', function () {
    Config::set('laravel-pdf.driver', 'chrome');

    $builder = new class
    {
        public array $metaCalls = [];

        public bool $tagged = false;

        public function name(string $name): self
        {
            return $this;
        }

        public function format(mixed $format): self
        {
            return $this;
        }

        public function meta(
            ?string $title = null,
            ?string $author = null,
            ?string $subject = null,
            ?string $keywords = null,
            ?string $creator = null,
            mixed $creationDate = null
        ): self {
            $this->metaCalls[] = compact('title', 'author', 'subject', 'keywords', 'creator', 'creationDate');

            return $this;
        }

        public function tagged(): self
        {
            $this->tagged = true;

            return $this;
        }

        public function base64(): string
        {
            return base64_encode("%PDF-1.7\nmock handbook");
        }
    };

    $factory = new class($builder)
    {
        public function __construct(public object $builder) {}

        public function view(string $view, array $data = []): object
        {
            return $this->builder;
        }
    };

    Pdf::swap($factory);

    $pdf = app(HandbookPdfGenerator::class)->render('pdf.organization-handbook', makeHandbookGeneratorPayload());

    expect($pdf)->toBe("%PDF-1.7\nmock handbook")
        ->and($builder->metaCalls)->toBe([])
        ->and($builder->tagged)->toBeFalse();
});

it('maps missing cloudflare configuration to an actionable renderer failure', function () {
    Config::set('laravel-pdf.driver', 'cloudflare');
    Config::set('laravel-pdf.cloudflare.api_token', null);
    Config::set('laravel-pdf.cloudflare.account_id', null);

    Pdf::shouldReceive('view')
        ->once()
        ->andThrow(CouldNotGeneratePdf::missingCloudflareCredentials());

    expect(fn () => app(HandbookPdfGenerator::class)->render(
        'pdf.organization-handbook',
        makeHandbookGeneratorPayload()
    ))->toThrow(
        RuntimeException::class,
        'Cloudflare PDF configuration is incomplete. Set CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACCOUNT_ID.'
    );
});

it('maps cloudflare rate limits to an actionable renderer failure', function () {
    Config::set('laravel-pdf.driver', 'cloudflare');
    Config::set('laravel-pdf.cloudflare.api_token', 'cloudflare-token');
    Config::set('laravel-pdf.cloudflare.account_id', 'cloudflare-account');

    Pdf::shouldReceive('view')
        ->once()
        ->andThrow(CouldNotGeneratePdf::cloudflareApiError('429 Too Many Requests'));

    expect(fn () => app(HandbookPdfGenerator::class)->render(
        'pdf.organization-handbook',
        makeHandbookGeneratorPayload()
    ))->toThrow(
        RuntimeException::class,
        'Cloudflare PDF generation hit a rate limit (429). Check Browser Rendering limits or move to a paid Workers plan.'
    );
});

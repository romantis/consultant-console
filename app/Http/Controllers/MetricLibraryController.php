<?php

namespace App\Http\Controllers;

use App\Actions\MetricLibrary\ArchiveCanonicalMetric;
use App\Actions\MetricLibrary\CreateCanonicalMetric;
use App\Actions\MetricLibrary\UpdateCanonicalMetric;
use App\Http\Requests\CanonicalMetricActionRequest;
use App\Http\Requests\StoreCanonicalMetricRequest;
use App\Http\Requests\UpdateCanonicalMetricRequest;
use App\Models\CanonicalMetric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetricLibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CanonicalMetric::class);

        $metrics = CanonicalMetric::query()
            ->withCount('templates')
            ->orderBy('name')
            ->get()
            ->map(fn (CanonicalMetric $metric) => $this->presentMetric($metric, $request))
            ->values();

        return Inertia::render('MetricLibrary/Index', [
            'metrics' => $metrics,
            'can' => [
                'create' => $request->user()?->can('create', CanonicalMetric::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CanonicalMetric::class);

        return Inertia::render('MetricLibrary/Create');
    }

    public function edit(Request $request, CanonicalMetric $canonicalMetric): Response
    {
        $this->authorize('update', $canonicalMetric);

        $templates = $canonicalMetric->templates()
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'description'])
            ->map(fn ($template) => [
                'id' => (string) $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'description' => $template->description,
            ])
            ->values()
            ->all();

        return Inertia::render('MetricLibrary/Edit', [
            'metric' => $this->presentMetric($canonicalMetric, $request),
            'templates' => $templates,
        ]);
    }

    public function store(
        StoreCanonicalMetricRequest $request,
        CreateCanonicalMetric $creator
    ): RedirectResponse {
        $creator->handle($request->validated());

        return redirect()
            ->route('metric-library.index')
            ->with('success', 'Canonical metric created.');
    }

    public function update(
        UpdateCanonicalMetricRequest $request,
        CanonicalMetric $canonicalMetric,
        UpdateCanonicalMetric $updater
    ): RedirectResponse {
        $updater->handle($canonicalMetric, $request->validated());

        return redirect()
            ->route('metric-library.index')
            ->with('success', 'Canonical metric updated.');
    }

    public function destroy(
        CanonicalMetricActionRequest $request,
        CanonicalMetric $canonicalMetric,
        ArchiveCanonicalMetric $archiver
    ): RedirectResponse {
        $archiver->handle($canonicalMetric);

        return redirect()
            ->route('metric-library.index')
            ->with('success', 'Canonical metric archived.');
    }

    /**
     * @return array{
     *     id: string,
     *     key: string,
     *     name: string,
     *     description: string|null,
     *     default_metric_template_id: string|null,
     *     is_active: bool,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     templates_count: int,
     *     can: array{update: bool, delete: bool}
     * }
     */
    protected function presentMetric(CanonicalMetric $metric, Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (string) $metric->getKey(),
            'key' => $metric->key,
            'name' => $metric->name,
            'description' => $metric->description,
            'default_metric_template_id' => $metric->default_metric_template_id
                ? (string) $metric->default_metric_template_id
                : null,
            'is_active' => (bool) $metric->is_active,
            'created_at' => optional($metric->created_at)->toIso8601String(),
            'updated_at' => optional($metric->updated_at)->toIso8601String(),
            'templates_count' => (int) ($metric->templates_count ?? $metric->templates()->count()),
            'can' => [
                'update' => $user ? $user->can('update', $metric) : false,
                'delete' => $user ? $user->can('delete', $metric) : false,
            ],
        ];
    }
}

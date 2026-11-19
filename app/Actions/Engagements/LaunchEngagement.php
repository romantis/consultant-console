<?php

namespace App\Actions\Engagements;

use App\Actions\OrgMetrics\EnsureOrgMetricExists;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Enums\StakeholderRole;
use App\Jobs\SendEngagementInvites;
use App\Models\CanonicalMetric;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\PendingMembership;
use App\Models\User;
use App\Services\Engagements\StakeholderInvitationService;
use App\Support\EngagementChecklist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LaunchEngagement
{
    public function __construct(
        private StakeholderInvitationService $stakeholderInvitationService,
        private EnsureOrgMetricExclusivity $ensureOrgMetricExclusivity,
        private EnsureOrgMetricExists $ensureOrgMetricExists
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, User $actor): Engagement
    {
        /** @var Organization|null $organization */
        $organization = Organization::query()->find($payload['organization_id'] ?? null);

        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization_id' => __('Selected organization could not be found.'),
            ]);
        }

        $metricPayloads = collect($payload['metrics'] ?? [])->values();

        $this->validateMetricSelection($metricPayloads);

        $canonicalMetrics = $this->loadCanonicalMetrics($metricPayloads);

        $existingOrgMetrics = OrgMetric::query()
            ->where('organization_id', $organization->id)
            ->whereIn('canonical_metric_id', $canonicalMetrics->keys()->all())
            ->get()
            ->keyBy(fn (OrgMetric $orgMetric) => (string) $orgMetric->canonical_metric_id);

        $metricPayloads = $this->applyTemplateDefaults(
            $metricPayloads,
            $canonicalMetrics,
            $existingOrgMetrics
        );

        $templates = $this->loadTemplates($metricPayloads);
        $this->validateTemplateSelections($metricPayloads, $templates, $canonicalMetrics);

        $this->ensureOrgMetricExclusivity->handle(
            $organization,
            $metricPayloads->map(function (array $metric, int $index) use ($canonicalMetrics, $existingOrgMetrics) {
                $canonicalId = (string) ($metric['canonical_metric_id'] ?? '');
                $canonical = $canonicalMetrics->get($canonicalId);
                $orgMetric = $existingOrgMetrics->get($canonicalId);

                return [
                    'index' => $index,
                    'org_metric_id' => $orgMetric?->getKey(),
                    'canonical_name' => $canonical?->name,
                    'canonical_key' => $canonical?->key,
                ];
            })
        );

        $stakeholders = collect($payload['stakeholders'] ?? [])
            ->map(fn (array $stakeholder) => [
                'participant_id' => isset($stakeholder['participant_id']) ? (string) $stakeholder['participant_id'] : null,
                'email' => isset($stakeholder['email']) ? mb_strtolower((string) $stakeholder['email']) : null,
                'role' => $stakeholder['role'] ?? null,
                'department' => $stakeholder['department'] ?? null,
                'name' => $stakeholder['name'] ?? null,
            ])->values();

        $this->ensureStakeholdersPresent($stakeholders);

        $membershipIds = $this->extractMembershipIds($stakeholders, $metricPayloads);

        $memberships = $organization->memberships()
            ->with('user:id,name,email')
            ->whereIn('id', $membershipIds)
            ->get()
            ->keyBy(fn (Membership $membership) => (string) $membership->id);

        if ($memberships->count() !== $membershipIds->count()) {
            throw ValidationException::withMessages([
                'metrics' => __('All selected participants must belong to this organization.'),
            ]);
        }

        $repository = is_array($payload['handbook_repository'] ?? null)
            ? $payload['handbook_repository']
            : null;
        $repositoryFeatureEnabled = (bool) config('metric-harmony.features.repository_drivers', false);
        $defaultRepositoryDriver = $repositoryFeatureEnabled ? 'notion' : 'manual';

        return DB::transaction(function () use (
            $payload,
            $organization,
            $metricPayloads,
            $templates,
            $canonicalMetrics,
            $stakeholders,
            $memberships,
            $actor,
            $repository,
            $repositoryFeatureEnabled,
            $defaultRepositoryDriver
        ): Engagement {
            $checklist = EngagementChecklist::merge([], [
                'metrics_selected' => true,
                'stakeholders_invited' => $stakeholders->isNotEmpty(),
                'forms_bootstrapped' => true,
                'repository_connected' => (bool) data_get($payload, 'handbook_repository.identifier'),
            ]);

            /** @var Engagement $engagement */
            $engagement = $organization->engagements()->create([
                'title' => (string) $payload['name'],
                'status' => EngagementStatus::DRAFT,
                'starts_on' => $payload['timeline_start'] ?? null,
                'ends_on' => $payload['timeline_end'] ?? null,
                'repository_driver' => $repositoryFeatureEnabled && is_array($repository)
                    ? ($repository['driver'] ?? 'notion')
                    : $defaultRepositoryDriver,
                'repository_reference' => is_array($repository) ? ($repository['identifier'] ?? null) : null,
                'repository_notes' => is_array($repository) ? ($repository['notes'] ?? null) : null,
                'stakeholders_json' => $stakeholders->map(function (array $stakeholder) use ($memberships) {
                    $record = $stakeholder;

                    if (($stakeholder['participant_id'] ?? null) && $memberships->has($stakeholder['participant_id'])) {
                        $membership = $memberships->get($stakeholder['participant_id']);
                        $record['name'] ??= optional($membership?->user)->name;
                    }

                    return $record;
                })->values()->all(),
                'onboarding_checklist_json' => $checklist,
            ]);

            $participantsByIdentifier = collect();

            $stakeholdersByMembership = $stakeholders
                ->filter(fn (array $stakeholder) => isset($stakeholder['participant_id']))
                ->keyBy(fn (array $stakeholder) => (string) $stakeholder['participant_id']);

            foreach ($memberships as $membership) {
                $stakeholderRole = $this->normalizeStakeholderRole(
                    $stakeholdersByMembership->get((string) $membership->id)['role'] ?? null
                );

                $participant = EngagementParticipant::updateOrCreate(
                    [
                        'engagement_id' => $engagement->id,
                        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
                        'membership_id' => $membership->id,
                    ],
                    [
                        'pending_membership_id' => null,
                        'membership_role' => $membership->role?->value,
                        'stakeholder_role' => $stakeholderRole?->value,
                        'active' => true,
                    ]
                );

                $participant->setRelation('membership', $membership);

                $participantsByIdentifier->put((string) $membership->id, $participant);
            }

            $invitationPayloads = $this->buildInvitationCollection(
                $stakeholders,
                collect($payload['invited_emails'] ?? []),
                $memberships
            );

            $pendingParticipantMap = $this->stakeholderInvitationService
                ->syncForEngagement($engagement, $invitationPayloads, $actor);

            $pendingParticipantMap->each(function (EngagementParticipant $participant, string $identifier) use ($participantsByIdentifier) {
                $participantsByIdentifier->put($identifier, $participant);

                $pendingId = $participant->pendingMembership?->getKey();
                if ($pendingId) {
                    $participantsByIdentifier->put('pending:'.$pendingId, $participant);
                }
            });

            $pendingInviteIds = $pendingParticipantMap
                ->map(function (EngagementParticipant $participant) {
                    $pendingMembershipId = $participant->pendingMembership?->getKey();

                    return $pendingMembershipId ? (string) $pendingMembershipId : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($pendingInviteIds)) {
                SendEngagementInvites::dispatch(
                    (string) $organization->id,
                    (string) $engagement->id,
                    $pendingInviteIds,
                    (string) $actor->id
                )->afterCommit();
            }

            $orgMetricsByCanonical = $canonicalMetrics
                ->mapWithKeys(fn (CanonicalMetric $metric) => [
                    (string) $metric->getKey() => $this->ensureOrgMetricExists->handle($organization, $metric),
                ]);

            $this->seedMetrics(
                $engagement,
                $metricPayloads,
                $templates,
                $participantsByIdentifier,
                $canonicalMetrics,
                $orgMetricsByCanonical
            );

            $this->seedOrgMetricParticipants($engagement, $actor);

            return $engagement->fresh();
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return Collection<int, MetricTemplate>
     */
    private function loadTemplates(Collection $metrics): Collection
    {
        $missing = [];

        $metrics->values()->each(function (array $metric, int $index) use (&$missing) {
            $templateId = trim((string) ($metric['template_id'] ?? ''));

            if ($templateId === '') {
                $missing["metrics.$index.template_id"] = __('Select a template for this metric.');
            }
        });

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $templateIds = $metrics
            ->pluck('template_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($templateIds->isEmpty()) {
            throw ValidationException::withMessages([
                'metrics' => __('Metric templates are required.'),
            ]);
        }

        $templates = MetricTemplate::query()
            ->whereIn('id', $templateIds)
            ->get()
            ->keyBy(fn (MetricTemplate $template) => (string) $template->id);

        if ($templates->count() !== $templateIds->count()) {
            throw ValidationException::withMessages([
                'metrics' => __('One or more metric templates were not found.'),
            ]);
        }

        return $templates;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @param  Collection<string, CanonicalMetric>  $canonicalMetrics
     * @param  Collection<string, OrgMetric>  $orgMetricsByCanonical
     * @return Collection<int, array<string, mixed>>
     */
    private function applyTemplateDefaults(
        Collection $metrics,
        Collection $canonicalMetrics,
        Collection $orgMetricsByCanonical
    ): Collection {
        $templatesByCanonical = MetricTemplate::query()
            ->whereIn('canonical_metric_id', $canonicalMetrics->keys()->all())
            ->orderBy('name')
            ->get(['id', 'canonical_metric_id'])
            ->groupBy(fn (MetricTemplate $template) => (string) $template->canonical_metric_id);

        return $metrics->values()->map(function (array $metric) use (
            $canonicalMetrics,
            $orgMetricsByCanonical,
            $templatesByCanonical
        ) {
            $templateId = trim((string) ($metric['template_id'] ?? ''));

            if ($templateId !== '') {
                return $metric;
            }

            $canonicalId = (string) ($metric['canonical_metric_id'] ?? '');
            $canonical = $canonicalMetrics->get($canonicalId);
            $orgMetric = $orgMetricsByCanonical->get($canonicalId);
            $templates = $templatesByCanonical->get($canonicalId, collect());

            $resolvedId = null;

            $orgDefault = $orgMetric?->default_metric_template_id;
            if ($orgDefault && $templates->contains('id', $orgDefault)) {
                $resolvedId = (string) $orgDefault;
            }

            $canonicalDefault = $canonical?->default_metric_template_id;
            if (! $resolvedId && $canonicalDefault && $templates->contains('id', $canonicalDefault)) {
                $resolvedId = (string) $canonicalDefault;
            }

            if (! $resolvedId) {
                $resolvedId = (string) ($templates->first()?->id ?? '');
            }

            if ($resolvedId !== '') {
                $metric['template_id'] = $resolvedId;
            }

            return $metric;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return Collection<string, CanonicalMetric>
     */
    private function loadCanonicalMetrics(Collection $metrics): Collection
    {
        $canonicalIds = $metrics
            ->pluck('canonical_metric_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($canonicalIds->isEmpty()) {
            throw ValidationException::withMessages([
                'metrics' => __('Canonical metrics are required.'),
            ]);
        }

        $canonicalMetrics = CanonicalMetric::query()
            ->whereIn('id', $canonicalIds)
            ->get()
            ->keyBy(fn (CanonicalMetric $metric) => (string) $metric->getKey());

        if ($canonicalMetrics->count() !== $canonicalIds->count()) {
            throw ValidationException::withMessages([
                'metrics' => __('One or more canonical metrics were not found.'),
            ]);
        }

        return $canonicalMetrics;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @param  Collection<string, MetricTemplate>  $templates
     * @param  Collection<string, CanonicalMetric>  $canonicalMetrics
     */
    private function validateTemplateSelections(
        Collection $metrics,
        Collection $templates,
        Collection $canonicalMetrics
    ): void {
        $errors = [];

        $metrics->values()->each(function (array $metric, int $index) use (
            $templates,
            $canonicalMetrics,
            &$errors
        ) {
            $templateId = (string) ($metric['template_id'] ?? '');
            $canonicalId = (string) ($metric['canonical_metric_id'] ?? '');

            $template = $templates->get($templateId);
            $canonical = $canonicalMetrics->get($canonicalId);

            if (! $template || ! $canonical) {
                return;
            }

            if ((string) $template->canonical_metric_id !== (string) $canonical->getKey()) {
                $errors["metrics.$index.template_id"] = __(
                    'Select a template that matches the chosen canonical metric.'
                );
            }
        });

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $stakeholders
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return Collection<int, string>
     */
    private function extractMembershipIds(Collection $stakeholders, Collection $metrics): Collection
    {
        $ids = $stakeholders
            ->pluck('participant_id')
            ->filter(function ($identifier) {
                if (! $identifier) {
                    return false;
                }

                $identifier = (string) $identifier;

                if ($identifier === '') {
                    return false;
                }

                if (str_starts_with($identifier, 'invite:') || str_starts_with($identifier, 'pending:')) {
                    return false;
                }

                return true;
            })
            ->values();

        $metrics->each(function (array $metric) use ($ids) {
            foreach ($metric['participants'] ?? [] as $participant) {
                $identifier = (string) ($participant['participant_id'] ?? '');

                if (
                    $identifier === ''
                    || str_starts_with($identifier, 'invite:')
                    || str_starts_with($identifier, 'pending:')
                ) {
                    continue;
                }

                $ids->push($identifier);
            }
        });

        return $ids->unique()->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $stakeholders
     * @param  Collection<int, string>  $invitedEmails
     * @param  Collection<string, Membership>  $memberships
     */
    private function buildInvitationCollection(
        Collection $stakeholders,
        Collection $invitedEmails,
        Collection $memberships
    ): Collection {
        $entries = $stakeholders
            ->filter(fn (array $stakeholder) => isset($stakeholder['email']))
            ->map(function (array $stakeholder) use ($memberships) {
                $email = $stakeholder['email'];
                $role = $this->normalizeStakeholderRole($stakeholder['role'] ?? null);

                $name = $stakeholder['name'] ?? null;

                if (! $name && isset($stakeholder['participant_id'])) {
                    $membership = $memberships->get((string) $stakeholder['participant_id']);
                    $name = optional($membership?->user)->name;
                }

                return [
                    'email' => $email,
                    'name' => $name,
                    'role' => $role?->value,
                    'department' => $stakeholder['department'] ?? null,
                ];
            })->values();

        $invitedEmails
            ->map(fn ($email) => mb_strtolower((string) $email))
            ->filter()
            ->unique()
            ->each(function (string $email) use ($entries) {
                if ($entries->contains(fn (array $entry) => $entry['email'] === $email)) {
                    return;
                }

                $entries->push([
                    'email' => $email,
                    'name' => null,
                    'role' => StakeholderRole::VIEWER->value,
                    'department' => null,
                ]);
            });

        return $entries;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metricPayloads
     * @param  Collection<string, MetricTemplate>  $templates
     * @param  Collection<string, EngagementParticipant>  $participants
     * @param  Collection<string, CanonicalMetric>  $canonicalMetrics
     * @param  Collection<string, OrgMetric>  $orgMetricsByCanonical
     */
    private function seedMetrics(
        Engagement $engagement,
        Collection $metricPayloads,
        Collection $templates,
        Collection $participants,
        Collection $canonicalMetrics,
        Collection $orgMetricsByCanonical
    ): void {
        $templatesById = $templates->keyBy(fn (MetricTemplate $template) => (string) $template->id);

        foreach ($metricPayloads as $metricIndex => $metricPayload) {
            $templateId = (string) ($metricPayload['template_id'] ?? '');
            /** @var MetricTemplate|null $template */
            $template = $templatesById->get($templateId);
            $canonicalId = (string) ($metricPayload['canonical_metric_id'] ?? '');
            /** @var CanonicalMetric|null $canonicalMetric */
            $canonicalMetric = $canonicalMetrics->get($canonicalId);
            /** @var OrgMetric|null $orgMetric */
            $orgMetric = $orgMetricsByCanonical->get($canonicalId);

            $keySource = (string) ($metricPayload['key'] ?? $canonicalMetric?->key ?? $template?->key ?? '');
            $key = Str::slug((string) $keySource);

            if ($key === '') {
                $key = (string) ($metricPayload['key'] ?? $canonicalMetric?->key ?? Str::random(8));
            }

            $assignmentDueAt = null;

            if (! empty($metricPayload['due_date'])) {
                try {
                    $assignmentDueAt = Carbon::parse($metricPayload['due_date'])->endOfDay();
                } catch (\Throwable $exception) {
                    throw ValidationException::withMessages([
                        "metrics.$metricIndex.due_date" => __('Invalid due date provided.'),
                    ]);
                }
            }

            $metric = Metric::create([
                'organization_id' => $engagement->organization_id,
                'engagement_id' => $engagement->id,
                'metric_template_id' => $template?->id,
                'org_metric_id' => $orgMetric?->getKey(),
                'key' => $key,
                'name' => (string) ($metricPayload['name'] ?? $template?->name ?? $canonicalMetric?->name ?? $key),
                'slug' => Str::slug(sprintf('%s-%s', $key, Str::random(6))),
                'status' => MetricStatus::PROPOSED,
            ]);

            if ($orgMetric && $template && ! $orgMetric->default_metric_template_id) {
                $orgMetric->forceFill([
                    'default_metric_template_id' => $template->getKey(),
                ])->save();
            }

            $participantModels = collect();

            foreach ($metricPayload['participants'] ?? [] as $participantIndex => $payload) {
                $identifier = (string) ($payload['participant_id'] ?? '');

                if ($identifier === '') {
                    throw ValidationException::withMessages([
                        "metrics.$metricIndex.participants.$participantIndex.participant_id" => __('Participant is required.'),
                    ]);
                }

                $lookupKey = str_starts_with($identifier, 'invite:')
                    ? mb_strtolower($identifier)
                    : $identifier;

                /** @var EngagementParticipant|null $engagementParticipant */
                $engagementParticipant = $participants->get($lookupKey);

                if (! $engagementParticipant) {
                    if (str_starts_with($identifier, 'invite:')) {
                        $engagementParticipant = $participants->get(
                            'invite:'.mb_strtolower(Str::after($identifier, 'invite:'))
                        );
                    }
                }

                if (! $engagementParticipant) {
                    throw ValidationException::withMessages([
                        "metrics.$metricIndex.participants.$participantIndex.participant_id" => __('Selected participant could not be found.'),
                    ]);
                }

                $engagementParticipant->loadMissing('membership.user', 'pendingMembership');

                $role = $this->normalizeParticipantRole($payload['role'] ?? '');

                $subjectType = null;
                $subjectId = null;

                if ($engagementParticipant->participant_type === EngagementParticipant::TYPE_MEMBERSHIP
                    && $engagementParticipant->membership_id) {
                    $subjectType = Membership::class;
                    $subjectId = $engagementParticipant->membership_id;
                } elseif ($engagementParticipant->participant_type === EngagementParticipant::TYPE_PENDING
                    && $engagementParticipant->pending_membership_id) {
                    $subjectType = PendingMembership::class;
                    $subjectId = $engagementParticipant->pending_membership_id;
                }

                $metricParticipant = MetricParticipant::create([
                    'metric_id' => $metric->id,
                    'engagement_participant_id' => $engagementParticipant->id,
                    'user_id' => $engagementParticipant->membership?->user_id,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'responsibility' => $role,
                    'role_tags_json' => $payload['role_tags'] ?? [],
                ]);

                $participantModels->push($metricParticipant);
            }

            $form = Form::create([
                'organization_id' => $engagement->organization_id,
                'engagement_id' => $engagement->id,
                'metric_id' => $metric->id,
                'template_key' => $template?->key ?? 'custom',
                'title' => ($template?->name ?? $metric->name).' — Definition',
                'schema_json' => $template?->form_schema_json ?? ['fields' => []],
            ]);

            $participantModels->each(function (MetricParticipant $participant) use ($form, $metric, $assignmentDueAt) {
                if (! in_array($participant->responsibility, ['contributor', 'approver'], true)) {
                    return;
                }

                if (! $participant->user_id) {
                    return;
                }

                $assignment = FormAssignment::firstOrNew([
                    'form_id' => $form->id,
                    'user_id' => $participant->user_id,
                ]);

                $currentResponsibility = $assignment->exists
                    ? mb_strtolower((string) $assignment->responsibility)
                    : null;
                $newResponsibility = mb_strtolower((string) $participant->responsibility);
                $priority = ['contributor' => 1, 'approver' => 2];

                if (
                    $assignment->exists
                    && isset($priority[$currentResponsibility], $priority[$newResponsibility])
                    && $priority[$newResponsibility] < $priority[$currentResponsibility]
                ) {
                    FormSubmission::firstOrCreate(
                        [
                            'form_id' => $form->id,
                            'user_id' => $participant->user_id,
                        ],
                        []
                    );

                    return;
                }

                $assignment->fill([
                    'metric_id' => $metric->id,
                    'metric_participant_id' => $participant->id,
                    'responsibility' => ucfirst($participant->responsibility),
                    'role_tags_json' => $participant->role_tags_json ?? [],
                    'due_at' => $assignmentDueAt,
                ]);

                if (! $assignment->exists || $assignment->isDirty()) {
                    $assignment->save();
                }

                FormSubmission::firstOrCreate(
                    [
                        'form_id' => $form->id,
                        'user_id' => $assignment->user_id,
                    ],
                    []
                );
            });
        }
    }

    private function seedOrgMetricParticipants(Engagement $engagement, ?User $actor): void
    {
        $metrics = $engagement->metrics()
            ->with([
                'orgMetric',
                'orgMetric.participants',
                'participants.engagementParticipant.membership.user',
                'participants.engagementParticipant.pendingMembership',
            ])
            ->get();

        $seeded = [];

        foreach ($metrics as $metric) {
            $orgMetric = $metric->orgMetric;

            if (! $orgMetric) {
                continue;
            }

            $orgMetricId = (string) $orgMetric->getKey();

            if (isset($seeded[$orgMetricId])) {
                continue;
            }

            $hasParticipants = $orgMetric->participants()
                ->whereIn('responsibility', ['owner', 'approver'])
                ->exists();

            $eligible = $metric->participants
                ->filter(fn (MetricParticipant $participant) => in_array(
                    $participant->responsibility,
                    ['contributor', 'approver'],
                    true
                ));

            $approverCandidate = $this->selectOrgMetricParticipant(
                $eligible->filter(fn (MetricParticipant $participant) => $participant->responsibility === 'approver')
            );
            $approverOwnerCandidate = $this->selectOrgMetricParticipant(
                $eligible->filter(fn (MetricParticipant $participant) => $participant->responsibility === 'approver' && $participant->user_id)
            );
            $ownerCandidate = $approverOwnerCandidate ?? $this->selectOrgMetricParticipant(
                $eligible->filter(fn (MetricParticipant $participant) => $participant->responsibility !== 'approver' && $participant->user_id)
            );

            $this->seedDraftFinalDefinitionOwner($metric, $ownerCandidate, $actor);

            if ($hasParticipants) {
                $seeded[$orgMetricId] = true;

                continue;
            }

            foreach (['owner' => $ownerCandidate, 'approver' => $approverCandidate] as $responsibility => $participant) {
                if (! $participant || ! $participant->subject_type || ! $participant->subject_id) {
                    continue;
                }

                OrgMetricParticipant::firstOrCreate(
                    [
                        'org_metric_id' => $orgMetric->getKey(),
                        'responsibility' => $responsibility,
                        'subject_type' => $participant->subject_type,
                        'subject_id' => $participant->subject_id,
                    ],
                    [
                        'user_id' => $participant->user_id,
                    ]
                );
            }

            $seeded[$orgMetricId] = true;
        }
    }

    private function seedDraftFinalDefinitionOwner(Metric $metric, ?MetricParticipant $participant, ?User $actor): void
    {
        if (! $participant || ! $participant->user_id) {
            return;
        }

        $metric->loadMissing('finalDefinition');

        if ($metric->finalDefinition) {
            return;
        }

        $metric->finalDefinition()->create([
            'user_id' => $actor?->getKey(),
            'status' => 'Draft',
            'definition_json' => [],
            'owner_participant_id' => $participant->getKey(),
        ]);
    }

    private function selectOrgMetricParticipant(Collection $participants): ?MetricParticipant
    {
        return $participants
            ->sortBy(fn (MetricParticipant $participant) => $participant->user_id ? 0 : 1)
            ->first();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $stakeholders
     */
    private function ensureStakeholdersPresent(Collection $stakeholders): void
    {
        if ($stakeholders->isEmpty()) {
            throw ValidationException::withMessages([
                'stakeholders' => __('At least one stakeholder is required to launch an engagement.'),
            ]);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     */
    private function validateMetricSelection(Collection $metrics): void
    {
        $errors = [];
        $minMetrics = (int) config('metric-harmony.engagements.metrics_min', 3);
        $maxMetrics = (int) config('metric-harmony.engagements.metrics_max', 10);

        $count = $metrics->count();

        if ($count < $minMetrics || $count > $maxMetrics) {
            $errors['metrics'] = __('Engagements must include between :min and :max metrics.', [
                'min' => $minMetrics,
                'max' => $maxMetrics,
            ]);
        }

        $metrics->values()->each(function (array $metric, int $index) use (&$errors) {
            $participants = collect($metric['participants'] ?? []);

            $roles = $participants
                ->map(fn ($participant) => mb_strtolower((string) ($participant['role'] ?? '')))
                ->filter();

            $contributorCount = $roles->filter(fn (string $role) => $role === 'contributor')->count();
            if ($contributorCount < 2) {
                $errors["metrics.$index.participants"][] = __('Each metric must include at least two contributors.');
            }

            $approverCount = $roles->filter(fn (string $role) => $role === 'approver')->count();
            if ($approverCount !== 1) {
                $errors["metrics.$index.participants"][] = __('Each metric must include exactly one approver.');
            }

            $observerCount = $roles->filter(fn (string $role) => $role === 'observer')->count();

            if ($observerCount > 5) {
                $errors["metrics.$index.participants"][] = __('Metrics cannot include more than five observers.');
            }

            if ($observerCount < 1) {
                $errors["metrics.$index.participants"][] = __('Each metric must include at least one observer.');
            }
        });

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->map(fn ($messages) => (array) $messages)->all()
            );
        }
    }

    private function normalizeStakeholderRole(?string $role): ?StakeholderRole
    {
        $roleKey = mb_strtolower((string) $role);

        return match ($roleKey) {
            'admin' => StakeholderRole::ADMIN,
            'contributor', 'member' => StakeholderRole::MEMBER,
            'observer', 'viewer' => StakeholderRole::VIEWER,
            default => null,
        };
    }

    private function normalizeParticipantRole(string $role): string
    {
        return match (mb_strtolower($role)) {
            'contributor' => 'contributor',
            'approver' => 'approver',
            'observer' => 'observer',
            default => throw ValidationException::withMessages([
                'metrics' => __('Unknown participant role: :role', ['role' => $role]),
            ]),
        };
    }
}

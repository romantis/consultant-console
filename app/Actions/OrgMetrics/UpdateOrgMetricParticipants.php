<?php

namespace App\Actions\OrgMetrics;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Models\Membership;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateOrgMetricParticipants
{
    public function __construct(private readonly LogOrgMetricEvent $logOrgMetricEvent) {}

    /**
     * @param  array<int, string>  $owners
     * @param  array<int, string>  $approvers
     */
    public function handle(
        OrgMetric $orgMetric,
        array $owners,
        array $approvers,
        ?User $actor = null
    ): void {
        $memberships = Membership::query()
            ->where('organization_id', $orgMetric->organization_id)
            ->get()
            ->keyBy(fn (Membership $membership) => (string) $membership->getKey());

        $pendingMemberships = PendingMembership::query()
            ->where('organization_id', $orgMetric->organization_id)
            ->get()
            ->keyBy(fn (PendingMembership $pending) => (string) $pending->getKey());

        $resolvedOwners = $this->resolveSelections('owners', $owners, $memberships, $pendingMemberships);
        $resolvedApprovers = $this->resolveSelections('approvers', $approvers, $memberships, $pendingMemberships);

        DB::transaction(function () use ($orgMetric, $resolvedOwners, $resolvedApprovers) {
            $this->syncParticipants($orgMetric, 'owner', $resolvedOwners);
            $this->syncParticipants($orgMetric, 'approver', $resolvedApprovers);
        });

        $this->logOrgMetricEvent->handle(
            $orgMetric,
            'org-metric.participants.updated',
            [
                'owners_count' => $resolvedOwners->count(),
                'approvers_count' => $resolvedApprovers->count(),
            ],
            $actor
        );
    }

    /**
     * @param  Collection<string, Membership>  $memberships
     * @param  Collection<string, PendingMembership>  $pendingMemberships
     * @return Collection<int, array{subject_type: class-string, subject_id: string, user_id: string|null}>
     */
    private function resolveSelections(
        string $field,
        array $values,
        Collection $memberships,
        Collection $pendingMemberships
    ): Collection {
        return collect($values)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(function (string $value, int $index) use ($field, $memberships, $pendingMemberships) {
                $identifier = trim($value);
                $pendingId = Str::startsWith($identifier, 'pending:')
                    ? Str::after($identifier, 'pending:')
                    : null;

                if ($pendingId !== null) {
                    $pending = $pendingMemberships->get($pendingId);

                    if (! $pending) {
                        throw ValidationException::withMessages([
                            "{$field}.{$index}" => __('Selected participant could not be found.'),
                        ]);
                    }

                    return [
                        'subject_type' => PendingMembership::class,
                        'subject_id' => (string) $pending->getKey(),
                        'user_id' => null,
                    ];
                }

                $membership = $memberships->get($identifier);

                if ($membership) {
                    return [
                        'subject_type' => Membership::class,
                        'subject_id' => (string) $membership->getKey(),
                        'user_id' => $membership->user_id ? (string) $membership->user_id : null,
                    ];
                }

                $pending = $pendingMemberships->get($identifier);

                if ($pending) {
                    return [
                        'subject_type' => PendingMembership::class,
                        'subject_id' => (string) $pending->getKey(),
                        'user_id' => null,
                    ];
                }

                throw ValidationException::withMessages([
                    "{$field}.{$index}" => __('Selected participant could not be found.'),
                ]);
            })
            ->unique(fn (array $entry) => $entry['subject_type'].':'.$entry['subject_id'])
            ->values();
    }

    /**
     * @param  Collection<int, array{subject_type: class-string, subject_id: string, user_id: string|null}>  $entries
     */
    private function syncParticipants(OrgMetric $orgMetric, string $responsibility, Collection $entries): void
    {
        $existing = $orgMetric->participants()
            ->where('responsibility', $responsibility)
            ->get()
            ->keyBy(fn (OrgMetricParticipant $participant) => $participant->subject_type.':'.$participant->subject_id);

        $desiredKeys = $entries
            ->map(fn (array $entry) => $entry['subject_type'].':'.$entry['subject_id'])
            ->values()
            ->all();

        foreach ($entries as $entry) {
            $key = $entry['subject_type'].':'.$entry['subject_id'];
            $participant = $existing->get($key);

            if ($participant) {
                $participant->forceFill([
                    'user_id' => $entry['user_id'],
                ])->save();

                continue;
            }

            OrgMetricParticipant::create([
                'org_metric_id' => $orgMetric->getKey(),
                'responsibility' => $responsibility,
                'subject_type' => $entry['subject_type'],
                'subject_id' => $entry['subject_id'],
                'user_id' => $entry['user_id'],
            ]);
        }

        $existing->each(function (OrgMetricParticipant $participant) use ($desiredKeys) {
            $key = $participant->subject_type.':'.$participant->subject_id;

            if (! in_array($key, $desiredKeys, true)) {
                $participant->delete();
            }
        });
    }
}

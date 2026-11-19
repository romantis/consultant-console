<?php

namespace App\Http\Controllers;

use App\Enums\EngagementStatus;
use App\Http\Requests\UpdateFinalDefinitionRequest;
use App\Models\AuditLog;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class FinalDefinitionController extends Controller
{
    public function update(UpdateFinalDefinitionRequest $request, Organization $organization, Metric $metric): RedirectResponse
    {
        if ($metric->organization_id !== $organization->id) {
            abort(404);
        }

        $engagementStatus = $metric->engagement?->status;
        $engagementStatus = $engagementStatus instanceof EngagementStatus
            ? $engagementStatus
            : EngagementStatus::tryFromLegacy(
                $engagementStatus !== null ? (string) $engagementStatus : null
            );

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $finalDefinition = $metric->finalDefinition;
        if (! $finalDefinition) {
            $finalDefinition = $metric->finalDefinition()->create([
                'user_id' => $request->user()->id,
                'status' => 'Draft',
                'definition_json' => [],
            ]);
        }

        if ($finalDefinition->signed_off_at) {
            abort(403, 'Final definition is locked after sign-off.');
        }

        $this->authorize('update', $finalDefinition);

        $payload = $request->definitionPayload();
        $validated = $request->validated();

        $finalDefinition->fill(array_merge([
            'definition_json' => $payload,
            'notes' => $validated['notes'] ?? null,
            'owner_participant_id' => $validated['owner_participant_id'] ?? null,
            'user_id' => $request->user()->id,
        ], $request->consultantSignalPayload()))->save();

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => 'metric.final-definition.updated',
            'subject_type' => 'metric',
            'subject_id' => $metric->id,
            'properties' => ['final_definition_id' => $finalDefinition->id],
        ]);

        return back()->with('success', 'Final definition updated.');
    }
}

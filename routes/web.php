<?php

use App\Http\Controllers\ChangeProposalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscrepancyController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\EngagementReminderController;
use App\Http\Controllers\EngagementStatusController;
use App\Http\Controllers\EngagementWizardController;
use App\Http\Controllers\FinalDefinitionController;
use App\Http\Controllers\FormAssignmentController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\HandbookExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\MetricChangeGovernanceController;
use App\Http\Controllers\MetricController;
use App\Http\Controllers\MetricLibraryController;
use App\Http\Controllers\MetricSignOffController;
use App\Http\Controllers\MetricTemplateController;
use App\Http\Controllers\MetricWorkshopPrepController;
use App\Http\Controllers\Organization\EngagementHandbookWorkbenchController;
use App\Http\Controllers\Organization\EngagementMetricParticipantsController;
use App\Http\Controllers\Organization\EngagementParticipantController;
use App\Http\Controllers\Organization\EngagementShowController;
use App\Http\Controllers\Organization\EngagementWorkshopController;
use App\Http\Controllers\Organization\EngagementWorkshopSessionController;
use App\Http\Controllers\Organization\EngagementWorkshopSessionTransitionController;
use App\Http\Controllers\Organization\HandbookExportIndexController;
use App\Http\Controllers\Organization\OrgMetricAliasController;
use App\Http\Controllers\Organization\OrgMetricDefaultTemplateController;
use App\Http\Controllers\Organization\OrgMetricIndexController;
use App\Http\Controllers\Organization\OrgMetricParticipantsController;
use App\Http\Controllers\Organization\OrgMetricShowController;
use App\Http\Controllers\Organization\OrgMetricSignalsController;
use App\Http\Controllers\Organization\StaffOperatorAssignmentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\Owner\OwnerConsoleController;
use App\Http\Controllers\PendingMembershipAcceptanceController;
use App\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/metric-library', [MetricLibraryController::class, 'index'])
        ->name('metric-library.index');
    Route::get('/metric-library/create', [MetricLibraryController::class, 'create'])
        ->name('metric-library.create');
    Route::post('/metric-library', [MetricLibraryController::class, 'store'])
        ->name('metric-library.store');
    Route::get('/metric-library/{canonicalMetric}/edit', [MetricLibraryController::class, 'edit'])
        ->name('metric-library.edit');
    Route::patch('/metric-library/{canonicalMetric}', [MetricLibraryController::class, 'update'])
        ->name('metric-library.update');
    Route::delete('/metric-library/{canonicalMetric}', [MetricLibraryController::class, 'destroy'])
        ->name('metric-library.destroy');
    Route::get('/metric-templates', [MetricTemplateController::class, 'index'])
        ->name('metric-templates.index');
    Route::get('/metric-templates/create', [MetricTemplateController::class, 'create'])
        ->name('metric-templates.create');
    Route::post('/metric-templates', [MetricTemplateController::class, 'store'])
        ->name('metric-templates.store');
    Route::get('/metric-templates/{metricTemplate}/edit', [MetricTemplateController::class, 'edit'])
        ->name('metric-templates.edit');
    Route::get('/metric-templates/{metricTemplate}/preview', [MetricTemplateController::class, 'preview'])
        ->name('metric-templates.preview');
    Route::put('/metric-templates/{metricTemplate}', [MetricTemplateController::class, 'update'])
        ->name('metric-templates.update');
    Route::delete('/metric-templates/{metricTemplate}', [MetricTemplateController::class, 'destroy'])
        ->name('metric-templates.destroy');
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('org.index');
    Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('org.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('org.store');
    Route::post('/organizations/active', [OrganizationController::class, 'setActiveOrganization'])
        ->name('org.active');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('can:view,organization')
        ->name('org.show');

    Route::get('/forms/{form}', [FormController::class, 'show'])->name('forms.show');
    // Submission actions
    Route::put('/forms/{form}/autosave', [FormSubmissionController::class, 'autosave'])->name('forms.autosave');
    Route::post('/forms/{form}/submit', [FormSubmissionController::class, 'submit'])->name('forms.submit');

    Route::get(
        '/organizations/{organization}/settings',
        [OrganizationSettingsController::class, 'edit']
    )
        ->middleware('can:update,organization')
        ->name('org.settings.edit');

    Route::put(
        '/organizations/{organization}/settings',
        [OrganizationSettingsController::class, 'update']
    )
        ->middleware('can:update,organization')
        ->name('org.settings.update');

    Route::post('/organizations/{organization}/members', [OrganizationMemberController::class, 'store'])
        ->middleware('can:update,organization')->name('org.members.store');
    Route::put('/organizations/{organization}/members/{membership}', [OrganizationMemberController::class, 'update'])
        ->middleware('can:update,organization')->name('org.members.update');
    Route::delete('/organizations/{organization}/members/{membership}', [OrganizationMemberController::class, 'destroy'])
        ->middleware('can:update,organization')->name('org.members.destroy');
    Route::put('/organizations/{organization}/pending-members/{pendingMembership}', [OrganizationMemberController::class, 'updatePending'])
        ->middleware('can:update,organization')->name('org.pending-members.update');
    Route::delete('/organizations/{organization}/pending-members/{pendingMembership}', [OrganizationMemberController::class, 'destroyPending'])
        ->middleware('can:update,organization')->name('org.pending-members.destroy');
    Route::post('/organizations/{organization}/staff-operator-assignments', [StaffOperatorAssignmentController::class, 'store'])
        ->name('org.staff-operator-assignments.store');
    Route::delete('/organizations/{organization}/staff-operator-assignments/{assignment}', [StaffOperatorAssignmentController::class, 'destroy'])
        ->name('org.staff-operator-assignments.destroy');
    Route::get('/organizations/{organization}/engagements', [EngagementController::class, 'index'])
        ->middleware('can:view,organization')->name('org.engagements.index');
    Route::get('/organizations/{organization}/engagements/create', [EngagementController::class, 'create'])
        ->middleware('can:view,organization')
        ->name('org.engagements.create');
    Route::get('/organizations/{organization}/engagements/launch', [EngagementController::class, 'launch'])
        ->middleware('can:view,organization')
        ->name('org.engagements.launch');
    Route::get('/organizations/{organization}/engagements/{engagement}', EngagementShowController::class)
        ->middleware('can:view,engagement')
        ->name('org.engagements.show');
    Route::delete('/organizations/{organization}/engagements/{engagement}', [EngagementController::class, 'destroy'])
        ->middleware('can:delete,engagement')
        ->name('org.engagements.destroy');
    Route::get('/organizations/{organization}/engagements/{engagement}/workshop', EngagementWorkshopController::class)
        ->middleware('can:view,engagement')
        ->name('org.engagements.workshop');
    Route::get('/organizations/{organization}/engagements/{engagement}/handbook-workbench', EngagementHandbookWorkbenchController::class)
        ->middleware('can:view,engagement')
        ->name('org.engagements.handbook-workbench');
    Route::get('/organizations/{organization}/engagements/{engagement}/workshop-sessions', [EngagementWorkshopSessionController::class, 'index'])
        ->middleware('can:view,engagement')
        ->name('org.engagements.workshop-sessions.index');
    Route::post('/organizations/{organization}/engagements/{engagement}/workshop-sessions', [EngagementWorkshopSessionController::class, 'store'])
        ->middleware('can:update,engagement')
        ->name('org.engagements.workshop-sessions.store');
    Route::patch('/organizations/{organization}/engagements/{engagement}/workshop-sessions/{session}', [EngagementWorkshopSessionController::class, 'update'])
        ->middleware('can:update,engagement')
        ->name('org.engagements.workshop-sessions.update');
    Route::post('/organizations/{organization}/engagements/{engagement}/workshop-sessions/{session}/start', [EngagementWorkshopSessionTransitionController::class, 'start'])
        ->middleware('can:update,engagement')
        ->name('org.engagements.workshop-sessions.start');
    Route::post('/organizations/{organization}/engagements/{engagement}/workshop-sessions/{session}/complete', [EngagementWorkshopSessionTransitionController::class, 'complete'])
        ->middleware('can:update,engagement')
        ->name('org.engagements.workshop-sessions.complete');
    Route::post('/organizations/{organization}/engagements/{engagement}/workshop-sessions/{session}/cancel', [EngagementWorkshopSessionTransitionController::class, 'cancel'])
        ->middleware('can:update,engagement')
        ->name('org.engagements.workshop-sessions.cancel');
    Route::post('/organizations/{organization}/engagements/{engagement}/reminders', EngagementReminderController::class)
        ->middleware('can:update,engagement')
        ->name('org.engagements.reminders.store');

    Route::get('/organizations/{organization}/handbook-exports', HandbookExportIndexController::class)
        ->middleware('can:view,organization')
        ->name('org.handbook-exports.index');
    Route::get('/organizations/{organization}/org-metrics', OrgMetricIndexController::class)
        ->middleware('can:view,organization')
        ->name('org.org-metrics.index');
    Route::get('/organizations/{organization}/org-metrics/{orgMetric}', OrgMetricShowController::class)
        ->middleware('can:view,organization')
        ->name('org.org-metrics.show');
    Route::patch('/organizations/{organization}/org-metrics/{orgMetric}', OrgMetricAliasController::class)
        ->middleware('can:update,orgMetric')
        ->name('org.org-metrics.update');
    Route::patch('/organizations/{organization}/org-metrics/{orgMetric}/default-template', OrgMetricDefaultTemplateController::class)
        ->middleware('can:update,orgMetric')
        ->name('org.org-metrics.default-template.update');
    Route::patch('/organizations/{organization}/org-metrics/{orgMetric}/participants', OrgMetricParticipantsController::class)
        ->middleware('can:manageParticipants,orgMetric')
        ->name('org.org-metrics.participants.update');
    Route::patch('/organizations/{organization}/org-metrics/{orgMetric}/signals', OrgMetricSignalsController::class)
        ->middleware('can:updateSignals,orgMetric')
        ->name('org.org-metrics.signals.update');
    Route::post('/organizations/{organization}/handbook-exports/{handbookExport}/publish', [HandbookExportController::class, 'publish'])
        ->middleware('can:view,organization')
        ->name('org.handbook-exports.publish');
    Route::delete('/organizations/{organization}/handbook-exports/{handbookExport}/publish', [HandbookExportController::class, 'unpublish'])
        ->middleware('can:view,organization')
        ->name('org.handbook-exports.unpublish');
    Route::get('/organizations/{organization}/handbook-exports/{handbookExport}/download', [HandbookExportController::class, 'download'])
        ->middleware('can:view,organization')
        ->name('org.handbook-exports.download');

    Route::post('/api/engagements', [EngagementWizardController::class, 'store'])
        ->name('api.engagements.store');

    Route::get('/organizations/{organization}/engagements/{engagement}/forms', [FormController::class, 'index'])
        ->middleware('can:view,engagement')
        ->name('org.engagements.forms.index');
    Route::patch('/organizations/{organization}/engagements/{engagement}/status', EngagementStatusController::class)
        ->middleware('can:update,engagement')
        ->name('org.engagements.status.update');
    Route::put('/forms/{form}', [FormController::class, 'update'])
        ->middleware('can:update,form')->name('forms.update');

    Route::get('/organizations/{organization}/metrics/{metric}', [MetricController::class, 'show'])
        ->middleware('can:view,metric')
        ->name('org.metrics.show');
    Route::get('/organizations/{organization}/metrics/{metric}/governance', MetricChangeGovernanceController::class)
        ->middleware('can:view,metric')
        ->name('org.metrics.governance');
    Route::get('/organizations/{organization}/engagements/{engagement}/metrics/{metric}/prep', MetricWorkshopPrepController::class)
        ->middleware('can:view,metric')
        ->name('org.engagements.metrics.prep');
    Route::post('/organizations/{organization}/engagements/{engagement}/metrics/{metric}/prep/facilitation', [MetricWorkshopPrepController::class, 'storeFacilitation'])
        ->middleware('can:view,metric')
        ->name('org.engagements.metrics.prep.facilitation.store');
    Route::patch('/organizations/{organization}/engagements/{engagement}/metrics/{metric}/prep/facilitation', [MetricWorkshopPrepController::class, 'updateFacilitation'])
        ->middleware('can:view,metric')
        ->name('org.engagements.metrics.prep.facilitation.update');
    Route::patch(
        '/organizations/{organization}/engagements/{engagement}/metrics/{metric}/participants',
        EngagementMetricParticipantsController::class
    )
        ->middleware('can:update,metric')
        ->name('org.engagements.metrics.participants.update');
    Route::patch(
        '/organizations/{organization}/engagements/{engagement}/metrics/{metric}/participants/{metricParticipant}',
        [EngagementParticipantController::class, 'update']
    )
        ->middleware('can:update,metricParticipant')
        ->name('org.engagements.metric-participants.update');
    Route::put('/organizations/{organization}/metrics/{metric}/final-definition', [FinalDefinitionController::class, 'update'])
        ->middleware('can:view,metric')
        ->name('org.metrics.final-definition.update');
    Route::post('/organizations/{organization}/engagements/{engagement}/metrics/{metric}/sign-off', MetricSignOffController::class)
        ->middleware('can:signOff,metric')
        ->name('org.metrics.sign-off');
    Route::post('/organizations/{organization}/metrics/{metric}/change-proposals', [ChangeProposalController::class, 'store'])
        ->middleware(['feature.enabled:change_proposals', 'can:review,metric'])
        ->name('org.metrics.change-proposals.store');
    Route::post('/organizations/{organization}/change-proposals/{proposal}/decision', [ChangeProposalController::class, 'decide'])
        ->middleware(['feature.enabled:change_proposals', 'can:decide,proposal'])
        ->name('org.change-proposals.decision');
    Route::post('/organizations/{organization}/change-proposals/{proposal}/merge', [ChangeProposalController::class, 'merge'])
        ->middleware(['feature.enabled:change_proposals', 'can:merge,proposal'])
        ->name('org.change-proposals.merge');

    Route::middleware('owner')->group(function () {
        Route::get('/owner/console', [OwnerConsoleController::class, 'index'])
            ->name('owner.console');
    });

    Route::post('/owner/impersonation/{organization}', [ImpersonationController::class, 'start'])
        ->name('owner.impersonation.start');
    Route::delete('/owner/impersonation', [ImpersonationController::class, 'stop'])
        ->name('owner.impersonation.stop');
});

Route::middleware('auth')->group(function () {
    Route::prefix('api')->group(function () {
        Route::get('/form-assignments/{assignment}', [FormAssignmentController::class, 'show'])
            ->name('api.form-assignments.show');

        Route::patch('/form-assignments/{assignment}/autosave', [FormAssignmentController::class, 'autosave'])
            ->middleware('autosave.throttle')
            ->name('api.form-assignments.autosave');

        Route::post('/form-assignments/{assignment}/submit', [FormAssignmentController::class, 'submit'])
            ->name('api.form-assignments.submit');

        Route::get('/metrics/{metric}/discrepancies', [DiscrepancyController::class, 'index'])
            ->middleware('can:view,metric')
            ->name('api.metrics.discrepancies.index');

        Route::post('/metrics/{metric}/discrepancies/recompute', [DiscrepancyController::class, 'recompute'])
            ->middleware('can:review,metric')
            ->name('api.metrics.discrepancies.recompute');

        Route::patch('/metrics/{metric}/discrepancies/{discrepancy}', [DiscrepancyController::class, 'update'])
            ->middleware('can:resolve,discrepancy')
            ->name('api.metrics.discrepancies.update');

        Route::post('/metrics/{metric}/workshop-executions', [WorkshopController::class, 'store'])
            ->middleware('can:review,metric')
            ->name('api.metrics.workshop-executions.store');

        Route::post('/metrics/{metric}/change-proposals', [ChangeProposalController::class, 'store'])
            ->middleware(['feature.enabled:change_proposals', 'can:review,metric'])
            ->name('api.metrics.change-proposals.store');

        Route::post('/change-proposals/{proposal}/decision', [ChangeProposalController::class, 'decide'])
            ->middleware('feature.enabled:change_proposals')
            ->name('api.change-proposals.decision');

        Route::post('/change-proposals/{proposal}/merge', [ChangeProposalController::class, 'merge'])
            ->middleware('feature.enabled:change_proposals')
            ->name('api.change-proposals.merge');
    });
});

Route::match(['get', 'post'], '/invites/{pendingMembership}/accept', PendingMembershipAcceptanceController::class)
    ->middleware(['signed', 'throttle:5,1'])
    ->name('invites.accept');

Route::get('/handbooks/{handbookExport:public_slug}', [HandbookExportController::class, 'showPublic'])
    ->middleware('throttle:30,1')
    ->name('handbook-exports.public');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

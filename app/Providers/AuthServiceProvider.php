<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\CanonicalMetric;
use App\Models\ChangeProposal;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\FinalDefinition;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\HandbookExport;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Policies\AuditLogPolicy;
use App\Policies\CanonicalMetricPolicy;
use App\Policies\ChangeProposalPolicy;
use App\Policies\DiscrepancyPolicy;
use App\Policies\EngagementPolicy;
use App\Policies\EngagementWorkshopSessionPolicy;
use App\Policies\FinalDefinitionPolicy;
use App\Policies\FormPolicy;
use App\Policies\FormSubmissionPolicy;
use App\Policies\HandbookExportPolicy;
use App\Policies\MetricDefinitionPolicy;
use App\Policies\MetricParticipantPolicy;
use App\Policies\MetricPolicy;
use App\Policies\MetricTemplatePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OrgFinalDefinitionPolicy;
use App\Policies\OrgMetricPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Organization::class => OrganizationPolicy::class,
        Form::class => FormPolicy::class,
        FormSubmission::class => FormSubmissionPolicy::class,
        Metric::class => MetricPolicy::class,
        MetricParticipant::class => MetricParticipantPolicy::class,
        MetricTemplate::class => MetricTemplatePolicy::class,
        MetricDefinition::class => MetricDefinitionPolicy::class,
        Discrepancy::class => DiscrepancyPolicy::class,
        FinalDefinition::class => FinalDefinitionPolicy::class,
        CanonicalMetric::class => CanonicalMetricPolicy::class,
        OrgMetric::class => OrgMetricPolicy::class,
        OrgFinalDefinition::class => OrgFinalDefinitionPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        Engagement::class => EngagementPolicy::class,
        EngagementWorkshopSession::class => EngagementWorkshopSessionPolicy::class,
        HandbookExport::class => HandbookExportPolicy::class,
        ChangeProposal::class => ChangeProposalPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}

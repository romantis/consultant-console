<template>
    <AppShell
        :title="pageTitle"
        :description="pageDescription"
        :breadcrumbs="breadcrumbs"
    >
        <Head :title="pageTitle" />

        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Wizard progress</CardTitle>
                        <CardDescription
                            >Work through each step to launch the
                            engagement.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-3">
                            <li
                                v-for="step in stepTimeline"
                                :key="step.id"
                                class="flex items-start gap-3 rounded-lg border p-3 transition"
                                :class="stepContainerClasses(step.state)"
                            >
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-full border text-sm font-semibold"
                                    :class="stepIndicatorClasses(step.state)"
                                >
                                    <component :is="step.icon" class="size-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ step.title }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ step.description }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card v-if="currentStep === 'metrics'">
                    <CardHeader>
                        <CardTitle>Selection rules</CardTitle>
                        <CardDescription>
                            Pick between {{ MIN_METRICS }} and
                            {{ MAX_METRICS }} metrics for an engagement launch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent
                        class="space-y-2 text-sm text-muted-foreground"
                    >
                        <p>
                            {{ selectedMetricsCount }} metric{{
                                selectedMetricsCount === 1 ? '' : 's'
                            }}
                            selected.
                        </p>
                        <p v-if="remainingMetricSlots > 0">
                            You can add {{ remainingMetricSlots }} more metric{{
                                remainingMetricSlots === 1 ? '' : 's'
                            }}.
                        </p>
                        <p v-else>Metric selection limit reached.</p>
                    </CardContent>
                </Card>

                <Card v-if="currentStep === 'participants'">
                    <CardHeader>
                        <CardTitle>Assignment guardrails</CardTitle>
                        <CardDescription
                            >Every metric needs at least two contributors and
                            one approver; observers are capped at
                            five.</CardDescription
                        >
                    </CardHeader>
                    <CardContent
                        class="space-y-2 text-sm text-muted-foreground"
                    >
                        <p>
                            Invites added here become available as participants
                            once emails are queued.
                        </p>
                        <p>
                            Reminder schedules are computed from each metric due
                            date.
                        </p>
                    </CardContent>
                </Card>
            </aside>

            <section class="flex flex-col gap-6">
                <template v-if="currentStep === 'overview'">
                    <WizardStepOverview
                        :overview="overview"
                        :repository-options="repositoryOptions"
                        :organization-name="props.organization.name"
                        :repository-enabled="repositoryFeatureEnabled"
                        @change="updateOverview"
                    />
                    <div
                        v-if="stepErrors.overview.length"
                        class="rounded-md border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive"
                    >
                        <ul class="list-disc space-y-1 pl-5">
                            <li
                                v-for="(message, index) in stepErrors.overview"
                                :key="`overview-error-${index}`"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </template>

                <template v-else-if="currentStep === 'metrics'">
                    <WizardStepMetrics
                        :metrics="props.metrics"
                        :selected-keys="selectedMetrics"
                        :selected-templates="selectedTemplateIds"
                        :due-dates="metricDueDates"
                        :can-select-more="canSelectAdditionalMetric"
                        :min-metrics="MIN_METRICS"
                        :max-metrics="MAX_METRICS"
                        @toggle="
                            ({ key, selected }) => toggleMetric(key, selected)
                        "
                        @select-template="
                            ({ key, templateId }) =>
                                setTemplateVariant(key, templateId)
                        "
                        @set-due-date="
                            ({ key, dueDate }) => setMetricDueDate(key, dueDate)
                        "
                    />
                    <div
                        v-if="stepErrors.metrics.length"
                        class="rounded-md border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive"
                    >
                        <ul class="list-disc space-y-1 pl-5">
                            <li
                                v-for="(message, index) in stepErrors.metrics"
                                :key="`metrics-error-${index}`"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </template>

                <template v-else-if="currentStep === 'participants'">
                    <WizardStepParticipants
                        :invites="invites"
                        :stakeholder-roles="props.stakeholderRoles"
                        :default-invite-role="defaultInviteRole"
                        :participant-options="participantOptions"
                        :selected-templates="selectedTemplates"
                        :participants-for="participantsFor"
                        :is-participant-selected="isParticipantSelected"
                        :toggle-participant="toggleParticipant"
                        :observers-at-limit="observersAtLimit"
                        :should-hide-observer-option="shouldHideObserverOption"
                        :add-invite="addInvite"
                        :remove-invite="removeInvite"
                        :resolve-stakeholder-role-label="
                            resolveStakeholderRoleLabel
                        "
                    />
                    <div
                        v-if="stepErrors.participants.length"
                        class="rounded-md border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive"
                    >
                        <ul class="list-disc space-y-1 pl-5">
                            <li
                                v-for="(
                                    message, index
                                ) in stepErrors.participants"
                                :key="`participants-error-${index}`"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </template>

                <template v-else>
                    <WizardStepReview
                        :overview="overview"
                        :selected-metrics-count="selectedMetricsCount"
                        :summary-assignments="summaryAssignments"
                        :summary-stakeholders="summaryStakeholders"
                        :repository-driver-label="repositoryDriverLabel"
                        :resolve-stakeholder-role-label="
                            resolveStakeholderRoleLabel
                        "
                    />
                    <div
                        v-if="stepErrors.review.length"
                        class="rounded-md border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive"
                    >
                        <ul class="list-disc space-y-1 pl-5">
                            <li
                                v-for="(message, index) in stepErrors.review"
                                :key="`review-error-${index}`"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </template>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t pt-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="!canGoBack || submission.processing"
                        @click="handleBack"
                    >
                        Back
                    </Button>
                    <div class="flex flex-wrap items-center gap-3">
                        <p
                            v-if="!canAdvance"
                            class="text-sm text-muted-foreground"
                        >
                            Complete the required fields before continuing.
                        </p>
                        <p
                            v-else-if="submission.error"
                            class="text-sm text-destructive"
                        >
                            {{ submission.error }}
                        </p>
                        <Button
                            type="button"
                            :disabled="
                                !canAdvance ||
                                submission.processing ||
                                (currentStep === 'review' &&
                                    !canLaunchEngagement)
                            "
                            @click="handleAdvance"
                        >
                            {{ continueLabel }}
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </AppShell>
</template>

<script setup lang="ts">
import WizardStepMetrics from '@/components/engagements/WizardStepMetrics.vue';
import WizardStepOverview from '@/components/engagements/WizardStepOverview.vue';
import WizardStepParticipants from '@/components/engagements/WizardStepParticipants.vue';
import WizardStepReview from '@/components/engagements/WizardStepReview.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useWayfinder } from '@/composables/useWayfinder';
import AppShell from '@/layouts/AppShell.vue';
import { resolveCsrfHeaders } from '@/lib/csrf';
import {
    create as orgEngagementsCreate,
    show as orgEngagementsShow,
} from '@/routes/org/engagements';
import type { EngagementWizardMetric } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    ListChecks,
    Users,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch, type Component } from 'vue';

defineOptions({
    name: 'EngagementLaunchWizard',
});

const MAX_METRICS = 10;
const MIN_METRICS = import.meta.env.DEV ? 1 : 3;
const MAX_OBSERVERS = 5;
const DEFAULT_METRIC_DUE_DAYS = 14;
const MIN_CONTRIBUTORS_PER_METRIC = 2;
const MIN_APPROVERS_PER_METRIC = 1;
const REQUIRE_OBSERVER_PER_METRIC = true;
const APPROVER_LIMIT = 1;

const steps = ['overview', 'metrics', 'participants', 'review'] as const;
type StepId = (typeof steps)[number];
type StepState = 'complete' | 'active' | 'upcoming';

const STAKEHOLDER_ROLE_LOOKUP: Record<string, string> = {
    admin: 'Admin',
    member: 'Member',
    viewer: 'Viewer',
    contributor: 'Member',
    observer: 'Viewer',
};

const PARTICIPANT_TO_STAKEHOLDER_ROLE: Record<string, string> = {
    approver: STAKEHOLDER_ROLE_LOOKUP.admin,
    contributor: STAKEHOLDER_ROLE_LOOKUP.member,
    observer: STAKEHOLDER_ROLE_LOOKUP.viewer,
};

type WizardOverviewState = {
    name: string;
    timelineStart: string;
    timelineEnd: string;
    repository: {
        driver: string;
        identifier: string;
        notes?: string | null;
    };
};

type WizardParticipantAssignments = {
    contributors?: string[];
    approvers?: string[];
    observers?: string[];
    availability?: Record<string, string>;
};

type WizardInviteInput = {
    email: string;
    name?: string;
    role?: string;
    department?: string;
};

type LaunchWizardProps = {
    submitUrl: string;
    organization: {
        id: string;
        name: string;
        slug: string;
    };
    metrics: EngagementWizardMetric[];
    members: Array<{
        id: string;
        name: string;
        email: string;
        role: string;
        departments?: string[];
        is_staff?: boolean;
    }>;
    currentMembershipId?: string | null;
    pendingMembers: Array<{
        id: string;
        name?: string | null;
        email: string;
        role?: string | null;
        departments?: string[];
    }>;
    stakeholderRoles: Array<{ value: string; label: string }>;
    repositoryOptions: Array<{ driver: string; label: string }>;
    repositoryFeatureEnabled: boolean;
    defaultTimeline: {
        start: string;
        end: string;
    };
    permissions: {
        can_launch: boolean;
    };
};

type InternalAssignments = {
    contributors: string[];
    approvers: string[];
    observers: string[];
    availability: Record<string, string>;
};

type StoredInvite = WizardInviteInput & { id: string };

const PENDING_PARTICIPANT_PREFIX = 'pending:';

function buildPendingParticipantId(id: string) {
    return `${PENDING_PARTICIPANT_PREFIX}${id}`;
}

function normalizeRoleLabel(role?: string | null) {
    return role ? role.trim().toLowerCase() : '';
}

function isViewerRole(role?: string | null) {
    return normalizeRoleLabel(role) === 'viewer';
}

function isOwnerRole(role?: string | null) {
    return normalizeRoleLabel(role) === 'owner';
}

function isAdminRole(role?: string | null) {
    return normalizeRoleLabel(role) === 'admin';
}

function resolveMemberBadge(role?: string | null) {
    if (isOwnerRole(role)) {
        return 'Owner';
    }
    if (isAdminRole(role)) {
        return 'Admin';
    }
    return undefined;
}

function formatDateInput(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function addDays(date: Date, days: number) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
}

function resolveDateInput(input?: string) {
    if (input) {
        const trimmed = input.trim();

        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            // Anchor bare dates to local midnight so adding days doesn't shift across timezones.
            const localDate = new Date(`${trimmed}T00:00:00`);
            if (!Number.isNaN(localDate.getTime())) {
                return localDate;
            }
        }

        const parsed = new Date(trimmed);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed;
        }
    }

    return new Date();
}

function normalizeStakeholderRole(role?: string | null): string | undefined {
    if (!role) {
        return undefined;
    }

    const normalized = role.trim().toLowerCase();

    if (!normalized) {
        return undefined;
    }

    return (
        PARTICIPANT_TO_STAKEHOLDER_ROLE[normalized] ??
        STAKEHOLDER_ROLE_LOOKUP[normalized]
    );
}

function getDefaultTimelineStartDate() {
    return addDays(new Date(), 1);
}

const props = defineProps<LaunchWizardProps>();
const wayfinder = useWayfinder();
const repositoryFeatureEnabled = computed(
    () => props.repositoryFeatureEnabled ?? false,
);

const overview = reactive<WizardOverviewState>({
    name: '',
    timelineStart: props.defaultTimeline?.start ?? '',
    timelineEnd: props.defaultTimeline?.end ?? '',
    repository: {
        driver: '',
        identifier: '',
        notes: null,
    },
});
const lastAutoTimelineEnd = ref<string | null>(null);
const manualTimelineStartBlank = ref(false);
const manualTimelineEndBlank = ref(false);

const selectedMetrics = ref<string[]>([]);
const selectedTemplateIds = reactive<Record<string, string>>({});
const metricDueDates = reactive<Record<string, string>>({});
const participantAssignments = reactive<Record<string, InternalAssignments>>(
    {},
);
const invites = reactive<StoredInvite[]>([]);
const observerDefaultsApplied = reactive(new Set<string>());

const submission = reactive({
    processing: false,
    error: null as string | null,
});

const apiErrors = reactive<Record<string, string[]>>({});

const currentStepIndex = ref(0);
const currentStep = computed<StepId>(() => steps[currentStepIndex.value]);
const organizationId = props.organization.id;

const defaultInviteRole = computed(() => {
    const viewer = props.stakeholderRoles.find(
        (role) => role.value.toLowerCase() === 'viewer',
    );
    if (viewer) {
        return viewer.value;
    }

    const member = props.stakeholderRoles.find(
        (role) => role.value.toLowerCase() === 'member',
    );
    if (member) {
        return member.value;
    }

    return props.stakeholderRoles[0]?.value ?? 'Viewer';
});

const defaultObserverIds = computed(() =>
    props.members
        .filter(
            (member) =>
                !member.is_staff &&
                (isOwnerRole(member.role) || isAdminRole(member.role)),
        )
        .map((member) => member.id),
);
const defaultApproverMembershipId = computed(() => {
    const membershipId = props.currentMembershipId?.trim();

    if (!membershipId) {
        return null;
    }

    const membership = props.members.find(
        (member) => member.id === membershipId,
    );

    if (!membership || isViewerRole(membership.role)) {
        return null;
    }

    return membershipId;
});

watch(
    () => [...selectedMetrics.value],
    (currentKeys, previousKeys) => {
        const currentSet = new Set(currentKeys);
        (previousKeys ?? []).forEach((key) => {
            if (!currentSet.has(key)) {
                observerDefaultsApplied.delete(key);
            }
        });

        currentKeys.forEach((key) => {
            if (observerDefaultsApplied.has(key)) {
                return;
            }

            ensureTemplateSelection(key);
            observerDefaultsApplied.add(key);
            const record = ensureAssignment(key);
            defaultObserverIds.value.forEach((participantId) => {
                if (!record.observers.includes(participantId)) {
                    record.observers.push(participantId);
                }
            });

            const defaultApproverId = defaultApproverMembershipId.value;
            if (!defaultApproverId) {
                return;
            }

            if (APPROVER_LIMIT === 1) {
                record.approvers = [defaultApproverId];
                return;
            }

            if (!record.approvers.includes(defaultApproverId)) {
                record.approvers.push(defaultApproverId);
            }
        });
    },
    { immediate: true },
);

const stepDefinitions = [
    {
        id: 'overview',
        title: 'Overview',
        description: 'Timeline and repository details',
        icon: CalendarDays,
    },
    {
        id: 'metrics',
        title: 'Metrics',
        description: 'Select canonical metrics',
        icon: ClipboardList,
    },
    {
        id: 'participants',
        title: 'Participants',
        description: 'Assign ownership and invites',
        icon: Users,
    },
    {
        id: 'review',
        title: 'Review',
        description: 'Confirm launch details',
        icon: ListChecks,
    },
] satisfies Array<{
    id: StepId;
    title: string;
    description: string;
    icon: Component;
}>;

function ensureTimelineStart() {
    if (manualTimelineStartBlank.value) {
        return overview.timelineStart;
    }

    const trimmed = (overview.timelineStart ?? '').trim();
    if (trimmed.length > 0) {
        return overview.timelineStart;
    }

    const fallback = formatDateInput(getDefaultTimelineStartDate());
    if (overview.timelineStart !== fallback) {
        manualTimelineStartBlank.value = false;
        overview.timelineStart = fallback;
    }

    return fallback;
}

function synchronizeTimelineEnd(startInput: string) {
    if (manualTimelineEndBlank.value) {
        return;
    }

    const baseDate = resolveDateInput(startInput);
    const suggestedEnd = formatDateInput(addDays(baseDate, 14));
    const currentEnd = overview.timelineEnd ?? '';
    const endMissing = currentEnd.trim().length === 0;
    const endMatchesAuto =
        currentEnd === lastAutoTimelineEnd.value || currentEnd === suggestedEnd;

    if (endMissing || endMatchesAuto) {
        lastAutoTimelineEnd.value = suggestedEnd;
        if (currentEnd !== suggestedEnd) {
            manualTimelineEndBlank.value = false;
            overview.timelineEnd = suggestedEnd;
        }
    }
}

watch(
    () => overview.timelineStart,
    (start) => {
        if (manualTimelineStartBlank.value) {
            lastAutoTimelineEnd.value = null;
            return;
        }

        const effectiveStart =
            (start ?? '').trim().length > 0 ? start : ensureTimelineStart();
        synchronizeTimelineEnd(effectiveStart);
    },
    { immediate: true },
);

watch(
    () => overview.timelineEnd,
    (end) => {
        if (!end?.trim()) {
            if (manualTimelineEndBlank.value) {
                return;
            }

            const normalizedStart = ensureTimelineStart();
            synchronizeTimelineEnd(normalizedStart);
            return;
        }

        if (end === lastAutoTimelineEnd.value) {
            return;
        }

        lastAutoTimelineEnd.value = null;
    },
    { immediate: true },
);

// Repository selection is optional, so avoid forcing a default driver when options exist.

const stepTimeline = computed(() =>
    stepDefinitions.map((definition, index) => ({
        ...definition,
        state:
            index < currentStepIndex.value
                ? 'complete'
                : index === currentStepIndex.value
                  ? 'active'
                  : 'upcoming',
    })),
);

const breadcrumbs = computed(() => [
    {
        title: 'Organizations',
        href: wayfinder.org.index().url,
    },
    {
        title: props.organization.name,
        href: wayfinder.org.show(props.organization.id).url,
    },
    {
        title: 'Launch engagement',
        href: orgEngagementsCreate(props.organization.id).url,
    },
]);

function clearAllErrors() {
    Object.keys(apiErrors).forEach((key) => {
        delete apiErrors[key];
    });
}

function setErrorsFromPayload(
    errors: Record<string, string[] | string> | undefined,
) {
    clearAllErrors();

    if (!errors) {
        return;
    }

    Object.entries(errors).forEach(([field, messages]) => {
        const normalized = Array.isArray(messages)
            ? messages
                  .filter((message) => Boolean(message))
                  .map((message) => String(message))
            : [String(messages)].filter((message) => Boolean(message));

        if (normalized.length === 0) {
            return;
        }

        apiErrors[field] = normalized;
    });
}

function resolveStepForField(field: string): StepId {
    if (
        field.startsWith('name') ||
        field.startsWith('timeline_') ||
        field.startsWith('handbook_repository')
    ) {
        return 'overview';
    }

    if (field.startsWith('metrics')) {
        if (field.includes('participants')) {
            return 'participants';
        }

        return 'metrics';
    }

    if (
        field.startsWith('stakeholders') ||
        field.startsWith('invited_emails')
    ) {
        return 'participants';
    }

    return 'review';
}

function clearStepErrors(step: StepId) {
    Object.keys(apiErrors).forEach((key) => {
        if (resolveStepForField(key) === step) {
            delete apiErrors[key];
        }
    });
}

const stepErrors = computed(() => {
    const buckets: Record<StepId, string[]> = {
        overview: [],
        metrics: [],
        participants: [],
        review: [],
    };

    Object.entries(apiErrors).forEach(([field, messages]) => {
        const bucket = resolveStepForField(field);
        buckets[bucket].push(...messages);
    });

    return buckets;
});

function focusFirstErrorStep() {
    for (const stepId of steps) {
        if (stepErrors.value[stepId].length > 0) {
            const index = steps.indexOf(stepId);
            if (index >= 0) {
                currentStepIndex.value = index;
            }

            return;
        }
    }
}

const pageTitle = computed(
    () => `Launch engagement — ${props.organization.name}`,
);
const pageDescription = computed(
    () =>
        'Guide consultants through metrics, stakeholders, and invitations to kick off an engagement.',
);

function ensureAssignment(metricKey: string): InternalAssignments {
    if (!participantAssignments[metricKey]) {
        participantAssignments[metricKey] = {
            contributors: [],
            approvers: [],
            observers: [],
            availability: {},
        };
    }

    return participantAssignments[metricKey];
}

function resolveMetric(metricKey: string) {
    return props.metrics.find((metric) => metric.key === metricKey);
}

function resolveTemplate(metricKey: string) {
    const metric = resolveMetric(metricKey);
    if (!metric) {
        return null;
    }

    const selectedId =
        selectedTemplateIds[metricKey] ??
        metric.default_metric_template_id ??
        metric.templates[0]?.id;
    return (
        metric.templates.find((template) => template.id === selectedId) ??
        metric.templates[0] ??
        null
    );
}

function ensureTemplateSelection(metricKey: string) {
    if (selectedTemplateIds[metricKey]) {
        return;
    }

    const metric = resolveMetric(metricKey);
    const templateId =
        metric?.default_metric_template_id ?? metric?.templates[0]?.id;

    if (templateId) {
        selectedTemplateIds[metricKey] = templateId;
    }
}

function isValidDateInput(value: string | null | undefined): boolean {
    if (typeof value !== 'string') {
        return false;
    }

    return /^\d{4}-\d{2}-\d{2}$/.test(value.trim());
}

function resolveMetricDueDays(metricKey: string): number {
    const templateDays = resolveTemplate(metricKey)?.default_due_days;
    if (typeof templateDays !== 'number' || !Number.isFinite(templateDays)) {
        return DEFAULT_METRIC_DUE_DAYS;
    }

    const normalized = Math.floor(templateDays);
    return normalized > 0 ? normalized : DEFAULT_METRIC_DUE_DAYS;
}

function resolveDefaultMetricDueDate(metricKey: string): string {
    const timelineStart = overview.timelineStart.trim();
    const baseDateInput =
        timelineStart !== '' ? timelineStart : ensureTimelineStart();
    const baseDate = resolveDateInput(baseDateInput);

    return formatDateInput(addDays(baseDate, resolveMetricDueDays(metricKey)));
}

function dueDateForMetric(metricKey: string): string {
    if (Object.prototype.hasOwnProperty.call(metricDueDates, metricKey)) {
        return metricDueDates[metricKey] ?? '';
    }

    return resolveDefaultMetricDueDate(metricKey);
}

function setMetricDueDate(metricKey: string, dueDate: string | null) {
    clearStepErrors('metrics');
    metricDueDates[metricKey] = dueDate?.trim() ?? '';
}

function normalizeIdList(values?: string[]): string[] {
    return Array.from(
        new Set(
            (values ?? [])
                .map((value) => value?.trim())
                .filter((value): value is string => Boolean(value)),
        ),
    );
}

function updateOverview(patch: Partial<WizardOverviewState>) {
    clearStepErrors('overview');

    if (patch.name !== undefined) {
        overview.name = patch.name;
    }

    if (patch.timelineStart !== undefined) {
        overview.timelineStart = patch.timelineStart;
        const trimmed = patch.timelineStart?.trim() ?? '';
        manualTimelineStartBlank.value = trimmed.length === 0;

        if (manualTimelineStartBlank.value) {
            manualTimelineEndBlank.value = true;
            lastAutoTimelineEnd.value = null;
            overview.timelineEnd = '';
        } else if (manualTimelineEndBlank.value) {
            manualTimelineEndBlank.value = false;
        }
    }

    if (patch.timelineEnd !== undefined) {
        overview.timelineEnd = patch.timelineEnd;
        const trimmed = patch.timelineEnd?.trim() ?? '';
        manualTimelineEndBlank.value = trimmed.length === 0;

        if (
            !manualTimelineEndBlank.value &&
            trimmed !== lastAutoTimelineEnd.value
        ) {
            lastAutoTimelineEnd.value = null;
        }
    }

    if (patch.repository) {
        if (patch.repository.driver !== undefined) {
            overview.repository.driver = patch.repository.driver;
        }

        if (patch.repository.identifier !== undefined) {
            overview.repository.identifier = patch.repository.identifier;
        }

        if (patch.repository.notes !== undefined) {
            overview.repository.notes = patch.repository.notes ?? null;
        }
    }
}

function selectedMetricKeys() {
    return [...selectedMetrics.value];
}

function toggleMetric(metricKey: string, desiredState?: boolean) {
    clearStepErrors('metrics');

    const currentlySelected = selectedMetrics.value.includes(metricKey);
    const shouldSelect = desiredState ?? !currentlySelected;

    if (shouldSelect) {
        if (currentlySelected) {
            return true;
        }

        if (selectedMetrics.value.length >= MAX_METRICS) {
            return false;
        }

        selectedMetrics.value = [...selectedMetrics.value, metricKey];
        ensureTemplateSelection(metricKey);
        ensureAssignment(metricKey);
        metricDueDates[metricKey] = resolveDefaultMetricDueDate(metricKey);

        return true;
    }

    if (!currentlySelected) {
        return true;
    }

    selectedMetrics.value = selectedMetrics.value.filter(
        (key) => key !== metricKey,
    );
    delete participantAssignments[metricKey];
    delete selectedTemplateIds[metricKey];
    delete metricDueDates[metricKey];

    return true;
}

function setTemplateVariant(metricKey: string, templateId: string) {
    selectedTemplateIds[metricKey] = templateId;
}

function pruneAvailability(record: InternalAssignments) {
    const valid = new Set([
        ...record.contributors,
        ...record.approvers,
        ...record.observers,
    ]);

    Object.keys(record.availability).forEach((participantId) => {
        if (!valid.has(participantId)) {
            delete record.availability[participantId];
        }
    });
}

function assignParticipants(
    metricKey: string,
    assignments: WizardParticipantAssignments,
) {
    clearStepErrors('participants');

    if (!selectedMetrics.value.includes(metricKey)) {
        throw new Error(`Metric ${metricKey} is not selected.`);
    }

    const observerCount = assignments.observers?.length ?? 0;
    if (observerCount > MAX_OBSERVERS) {
        throw new Error('Observer limit exceeded for this metric.');
    }

    const record = ensureAssignment(metricKey);

    record.contributors = normalizeIdList(assignments.contributors);
    record.approvers = normalizeIdList(assignments.approvers);
    record.observers = normalizeIdList(assignments.observers);
    record.availability = {
        ...record.availability,
        ...(assignments.availability ?? {}),
    };

    pruneAvailability(record);
}

function normalizeInviteEmail(email: string): string {
    return email.trim().toLowerCase();
}

function addInvite(invite: WizardInviteInput) {
    clearStepErrors('participants');

    const email = normalizeInviteEmail(invite.email);

    if (!email) {
        throw new Error('An invite email is required.');
    }

    const id = `invite:${email}`;
    const payload: StoredInvite = {
        id,
        email,
        name: invite.name?.trim() ?? '',
        role: invite.role ?? defaultInviteRole.value,
        department: invite.department?.trim() ?? '',
    };

    const existingIndex = invites.findIndex((entry) => entry.id === id);
    if (existingIndex >= 0) {
        invites.splice(existingIndex, 1, payload);
    } else {
        invites.push(payload);
    }

    return id;
}

function removeInvite(inviteId: string) {
    clearStepErrors('participants');

    const index = invites.findIndex((invite) => invite.id === inviteId);
    if (index === -1) {
        return;
    }

    invites.splice(index, 1);

    Object.values(participantAssignments).forEach((record) => {
        record.contributors = record.contributors.filter(
            (id) => id !== inviteId,
        );
        record.approvers = record.approvers.filter((id) => id !== inviteId);
        record.observers = record.observers.filter((id) => id !== inviteId);
        delete record.availability[inviteId];
    });
}

const canAdvance = computed(() => {
    switch (currentStep.value) {
        case 'overview': {
            const hasName = overview.name.trim().length > 0;
            const hasStart = overview.timelineStart.trim().length > 0;
            const hasEnd = overview.timelineEnd.trim().length > 0;

            return hasName && hasStart && hasEnd;
        }

        case 'metrics': {
            const count = selectedMetrics.value.length;
            if (count < MIN_METRICS || count > MAX_METRICS) {
                return false;
            }

            return selectedMetrics.value.every(
                (metricKey) =>
                    Boolean(resolveTemplate(metricKey)) &&
                    isValidDateInput(dueDateForMetric(metricKey)),
            );
        }

        case 'participants': {
            if (selectedMetrics.value.length === 0) {
                return false;
            }

            return selectedMetrics.value.every((metricKey) => {
                const record = participantAssignments[metricKey];
                if (!record) {
                    return false;
                }

                const hasContributors =
                    record.contributors.length >= MIN_CONTRIBUTORS_PER_METRIC;
                const hasApprovers =
                    record.approvers.length >= MIN_APPROVERS_PER_METRIC;
                const hasObservers =
                    !REQUIRE_OBSERVER_PER_METRIC || record.observers.length > 0;

                return hasContributors && hasApprovers && hasObservers;
            });
        }

        case 'review':
        default:
            return true;
    }
});

function next() {
    if (!canAdvance.value) {
        return false;
    }

    if (currentStepIndex.value >= steps.length - 1) {
        return true;
    }

    currentStepIndex.value += 1;
    return true;
}

function previous() {
    if (currentStepIndex.value > 0) {
        currentStepIndex.value -= 1;
    }
}

function buildParticipantEntries(record: InternalAssignments) {
    const entries: Array<Record<string, unknown>> = [];

    const register = (participantId: string, role: string) => {
        entries.push({
            participant_id: participantId,
            role,
        });
    };

    record.contributors.forEach((participantId) =>
        register(participantId, 'contributor'),
    );
    record.approvers.forEach((participantId) =>
        register(participantId, 'approver'),
    );
    record.observers.forEach((participantId) =>
        register(participantId, 'observer'),
    );

    return entries;
}

function getSubmissionPayload() {
    const metrics = selectedMetrics.value.map((metricKey) => {
        const metric = resolveMetric(metricKey);
        const template = resolveTemplate(metricKey);
        const record =
            participantAssignments[metricKey] ?? ensureAssignment(metricKey);

        return {
            canonical_metric_id: metric?.id ?? null,
            template_id: template?.id ?? null,
            key: metric?.key ?? metricKey,
            name: metric?.name ?? metricKey,
            description: template?.description ?? metric?.description ?? null,
            required_departments: template?.required_departments ?? null,
            due_date: dueDateForMetric(metricKey),
            participants: buildParticipantEntries(record),
        };
    });

    const stakeholdersMap = new Map<string, Record<string, unknown>>();

    metrics.forEach((metric) => {
        metric.participants.forEach((participant) => {
            const participantId = participant.participant_id as string;
            const invite = invites.find(
                (candidate) => candidate.id === participantId,
            );
            const participantRole = normalizeStakeholderRole(
                participant.role as string | undefined,
            );

            if (invite) {
                const inviteRole = normalizeStakeholderRole(invite.role);
                stakeholdersMap.set(`invite-${participantId}`, {
                    participant_id: participantId,
                    email: invite.email,
                    name: invite.name,
                    role: inviteRole ?? participantRole ?? 'Viewer',
                    department: invite.department,
                });
            } else {
                const member = memberLookup.value.get(participantId);

                if (member) {
                    const membershipRole = normalizeStakeholderRole(
                        member.role,
                    );
                    stakeholdersMap.set(`member-${participantId}`, {
                        participant_id: participantId,
                        email: member.email,
                        name: member.name,
                        role: participantRole ?? membershipRole ?? 'Member',
                        department: member.departments?.[0] ?? null,
                    });
                } else {
                    const pending = pendingLookup.value.get(participantId);

                    if (pending) {
                        const pendingRole = pending.role
                            ? normalizeStakeholderRole(pending.role)
                            : undefined;
                        stakeholdersMap.set(`pending-${participantId}`, {
                            participant_id: participantId,
                            email: pending.email,
                            name: pending.name,
                            role: participantRole ?? pendingRole ?? 'Member',
                            department: pending.departments?.[0] ?? null,
                        });
                        return;
                    }

                    stakeholdersMap.set(`member-${participantId}`, {
                        participant_id: participantId,
                        role: participantRole ?? 'Member',
                    });
                }
            }
        });
    });

    invites.forEach((invite) => {
        const key = `invite-${invite.id}`;
        if (!stakeholdersMap.has(key)) {
            stakeholdersMap.set(key, {
                participant_id: invite.id,
                email: invite.email,
                name: invite.name,
                role: normalizeStakeholderRole(invite.role) ?? 'Viewer',
                department: invite.department,
            });
        }
    });

    const repositoryDriver = overview.repository.driver?.trim() ?? '';
    const repositoryIdentifier = overview.repository.identifier?.trim() ?? '';
    const repositoryNotesRaw = overview.repository.notes ?? null;
    const repositoryNotes =
        typeof repositoryNotesRaw === 'string' ? repositoryNotesRaw : null;
    const repositoryNotesTrimmed = repositoryNotes?.trim() ?? '';

    const payload: Record<string, unknown> = {
        organization_id: props.organization.id,
        name: overview.name,
        timeline_start: overview.timelineStart,
        timeline_end: overview.timelineEnd,
        metrics,
        stakeholders: Array.from(stakeholdersMap.values()),
        invited_emails: Array.from(
            new Set(invites.map((invite) => invite.email)),
        ),
    };

    const hasRepositoryDetails =
        repositoryDriver.length > 0 ||
        repositoryIdentifier.length > 0 ||
        repositoryNotesTrimmed.length > 0;

    if (hasRepositoryDetails) {
        payload.handbook_repository = {
            driver: repositoryDriver,
            identifier: repositoryIdentifier,
            notes: repositoryNotes,
        };
    }

    return payload;
}

async function submit() {
    if (
        !canAdvance.value ||
        submission.processing ||
        !canLaunchEngagement.value
    ) {
        if (!canLaunchEngagement.value) {
            submission.error =
                'You no longer have permission to launch this engagement.';
        }
        return;
    }

    submission.processing = true;
    submission.error = null;
    clearAllErrors();

    const payload = getSubmissionPayload();

    try {
        const response = await fetch(props.submitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...resolveCsrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (response.ok) {
            const data = await response.json();
            clearAllErrors();
            submission.processing = false;

            const organizationId =
                data.organization_id ?? props.organization.id;
            const engagementId = data.id;

            if (engagementId) {
                const destination = orgEngagementsShow([
                    organizationId,
                    engagementId,
                ]);
                const href =
                    typeof destination === 'string'
                        ? destination
                        : destination?.url;
                if (href) {
                    router.visit(href);
                    return;
                }
            }

            submission.error =
                'Engagement launched, but redirect failed. Please open it manually.';
            return;
        }

        if (response.status === 422) {
            const problem = await response.json().catch(() => null);
            setErrorsFromPayload(problem?.errors ?? {});
            focusFirstErrorStep();
            return;
        }

        const problem = await response.json().catch(() => null);
        submission.error =
            problem?.message ??
            'Unable to launch engagement. Please try again.';
    } catch {
        submission.error =
            'Unable to launch engagement. Please check your connection and try again.';
    } finally {
        submission.processing = false;
    }
}

const selectedTemplates = computed(() =>
    selectedMetrics.value
        .map((metricKey) => {
            const metric = resolveMetric(metricKey);
            const template = resolveTemplate(metricKey);

            if (!metric) {
                return null;
            }

            return {
                key: metric.key,
                name: metric.name,
                description: template?.description ?? metric.description ?? '',
                required_departments: template?.required_departments,
            };
        })
        .filter((template): template is NonNullable<typeof template> =>
            Boolean(template),
        ),
);

const selectedMetricsCount = computed(() => selectedMetrics.value.length);
const remainingMetricSlots = computed(() =>
    Math.max(0, MAX_METRICS - selectedMetrics.value.length),
);
const canSelectAdditionalMetric = computed(
    () => selectedMetrics.value.length < MAX_METRICS,
);

const memberOptions = computed(() =>
    props.members.map((member) => ({
        id: member.id,
        label: member.name,
        subtitle: member.email,
        badge: resolveMemberBadge(member.role),
        isViewer: isViewerRole(member.role),
        isStaff: Boolean(member.is_staff),
    })),
);

const pendingMemberOptions = computed(() =>
    props.pendingMembers.map((pending) => ({
        id: buildPendingParticipantId(pending.id),
        label: pending.name?.trim() || pending.email,
        subtitle: pending.email
            ? `${pending.email} · Pending invite`
            : 'Pending invite',
        badge: 'Pending invite',
        isViewer: isViewerRole(pending.role),
        isStaff: false,
    })),
);

const inviteOptions = computed(() =>
    invites.map((invite) => ({
        id: invite.id,
        label: invite.name || invite.email,
        subtitle: invite.email,
        isViewer: isViewerRole(invite.role),
        isStaff: false,
    })),
);

function allowActiveRole(option: { isViewer?: boolean; isStaff?: boolean }) {
    return !option.isViewer;
}

const participantOptions = computed(() => {
    const allOptions = [
        ...memberOptions.value,
        ...pendingMemberOptions.value,
        ...inviteOptions.value,
    ];
    const activeOptions = allOptions.filter(allowActiveRole);

    return {
        contributors: activeOptions,
        approvers: activeOptions,
        observers: allOptions,
    };
});

function participantsFor(metricKey: string) {
    return ensureAssignment(metricKey);
}

const memberLookup = computed(
    () => new Map(props.members.map((member) => [member.id, member])),
);
const pendingLookup = computed(
    () =>
        new Map(
            props.pendingMembers.map((pending) => [
                buildPendingParticipantId(pending.id),
                pending,
            ]),
        ),
);
const inviteLookup = computed(
    () => new Map(invites.map((invite) => [invite.id, invite])),
);

function isParticipantSelected(
    metricKey: string,
    role: keyof Omit<InternalAssignments, 'availability'>,
    participantId: string,
) {
    const record = ensureAssignment(metricKey);
    return record[role].includes(participantId);
}

type ParticipantRoleKey = keyof Omit<InternalAssignments, 'availability'>;

function toggleParticipant(
    metricKey: string,
    role: ParticipantRoleKey,
    participantId: string,
    selected: boolean,
) {
    clearStepErrors('participants');

    const record = ensureAssignment(metricKey);
    const bucket = record[role];

    if (selected && role !== 'observers') {
        if (record.observers.includes(participantId)) {
            return;
        }
    }

    if (selected) {
        if (
            role === 'observers' &&
            bucket.length >= MAX_OBSERVERS &&
            !bucket.includes(participantId)
        ) {
            return;
        }

        if (role === 'approvers' && APPROVER_LIMIT === 1) {
            record.approvers = [participantId];
            pruneAvailability(record);
            return;
        }

        if (
            role === 'approvers' &&
            APPROVER_LIMIT > 0 &&
            bucket.length >= APPROVER_LIMIT &&
            !bucket.includes(participantId)
        ) {
            return;
        }

        if (!bucket.includes(participantId)) {
            bucket.push(participantId);
        }
    } else {
        record[role] = bucket.filter((id) => id !== participantId);
    }

    pruneAvailability(record);
}

function observersAtLimit(metricKey: string) {
    const record = ensureAssignment(metricKey);
    return record.observers.length >= MAX_OBSERVERS;
}

function shouldHideObserverOption(metricKey: string, participantId: string) {
    const record = ensureAssignment(metricKey);
    return (
        record.contributors.includes(participantId) ||
        record.approvers.includes(participantId)
    );
}

const stakeholderRoleLabelMap = computed(() => {
    const map = new Map<string, string>();
    props.stakeholderRoles.forEach((role) => {
        map.set(role.value.toLowerCase(), role.label);
    });

    return map;
});

function resolveStakeholderRoleLabel(role: string | undefined) {
    if (!role) {
        return 'Stakeholder';
    }

    return stakeholderRoleLabelMap.value.get(role.toLowerCase()) ?? role;
}

function repositoryDriverLabel(driver: string) {
    if (!repositoryFeatureEnabled.value) {
        return 'Manual instructions (adapters paused)';
    }

    if (!driver?.trim()) {
        return 'No repository selected';
    }

    const match = repositoryOptions.value.find(
        (option) => option.driver === driver,
    );
    return match?.label ?? driver;
}

const summaryAssignments = computed(() =>
    selectedTemplates.value.map((template) => {
        const record = ensureAssignment(template.key);
        const resolveLabel = (id: string) => {
            const member = memberLookup.value.get(id);
            if (member) {
                return member.name;
            }

            const pending = pendingLookup.value.get(id);
            if (pending) {
                return pending.name?.trim() || pending.email;
            }

            const invite = inviteLookup.value.get(id);
            return invite?.name || invite?.email || id;
        };

        return {
            template,
            contributors: record.contributors.map((id) => ({
                id,
                label: resolveLabel(id),
            })),
            approvers: record.approvers.map((id) => ({
                id,
                label: resolveLabel(id),
            })),
            observers: record.observers.map((id) => ({
                id,
                label: resolveLabel(id),
            })),
        };
    }),
);

const summaryStakeholders = computed(() => {
    const collection = new Map<
        string,
        { id: string; name: string; email: string; role: string }
    >();

    summaryAssignments.value.forEach((summary) => {
        [
            ...summary.contributors,
            ...summary.approvers,
            ...summary.observers,
        ].forEach((participant) => {
            const member = memberLookup.value.get(participant.id);
            if (member) {
                collection.set(`member-${participant.id}`, {
                    id: participant.id,
                    name: member.name,
                    email: member.email,
                    role: member.role ?? 'Member',
                });
                return;
            }

            const pending = pendingLookup.value.get(participant.id);
            if (pending) {
                collection.set(`pending-${participant.id}`, {
                    id: participant.id,
                    name: pending.name?.trim() || pending.email,
                    email: pending.email,
                    role: pending.role ?? 'Member',
                    isPending: true,
                    badge: 'Pending invite',
                });
                return;
            }

            const invite = inviteLookup.value.get(participant.id);
            if (invite) {
                collection.set(`invite-${participant.id}`, {
                    id: participant.id,
                    name: invite.name || invite.email,
                    email: invite.email,
                    role: invite.role ?? 'Viewer',
                });
            }
        });
    });

    invites.forEach((invite) => {
        const key = `invite-${invite.id}`;
        if (!collection.has(key)) {
            collection.set(key, {
                id: invite.id,
                name: invite.name || invite.email,
                email: invite.email,
                role: invite.role ?? 'Viewer',
            });
        }
    });

    return Array.from(collection.values());
});

const repositoryOptions = computed(() =>
    repositoryFeatureEnabled.value ? (props.repositoryOptions ?? []) : [],
);

const canGoBack = computed(() => currentStepIndex.value > 0);
const canLaunchEngagement = computed(
    () => props.permissions?.can_launch ?? false,
);
const continueLabel = computed(() =>
    currentStep.value === 'review'
        ? submission.processing
            ? 'Launching…'
            : 'Launch engagement'
        : 'Continue',
);

function handleAdvance() {
    if (!canAdvance.value || submission.processing) {
        return;
    }

    if (currentStep.value === 'review') {
        void submit();
        return;
    }

    const result = next();
    if (result && typeof (result as Promise<boolean>).then === 'function') {
        void (result as Promise<boolean>);
    }
}

function handleBack() {
    if (!canGoBack.value || submission.processing) {
        return;
    }

    previous();
}

function stepIndicatorClasses(state: StepState) {
    return [
        'transition',
        state === 'complete'
            ? 'border-primary bg-primary/10 text-primary'
            : state === 'active'
              ? 'border-primary/70 bg-primary/10 text-primary'
              : 'border-muted-foreground/30 text-muted-foreground',
    ];
}

function stepContainerClasses(state: StepState) {
    return [
        state === 'complete'
            ? 'border-primary/40 bg-primary/5'
            : state === 'active'
              ? 'border-primary bg-primary/5'
              : 'border-muted bg-background',
    ];
}

defineExpose({
    steps,
    currentStep,
    canAdvance,
    organizationId,
    updateOverview,
    toggleMetric,
    setMetricDueDate,
    assignParticipants,
    addInvite,
    selectedMetricKeys,
    next,
    previous,
    submit,
    getSubmissionPayload,
});
</script>

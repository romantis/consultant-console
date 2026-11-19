<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ClientLayout from '@/layouts/ClientLayout.vue';
import '../../../css/client-metric-handbook.css';

type Organization = {
    id: string;
    name: string;
};

type Engagement = {
    id: string;
    title: string;
};

type UserSummary = {
    id: string;
    name: string;
    email?: string | null;
};

type MetricDefinitionPayload = {
    id: string;
    status?: string | null;
    version?: number | null;
    definition_json: Record<string, unknown>;
    notes?: string | null;
    alignment_summary?: string | null;
    operational_status?: string | null;
    operational_status_notes?: string | null;
    implementation_gaps?: Record<string, unknown> | null;
    validation_steps?: string | null;
    common_mismatch_causes?: string | null;
    signed_off_at?: string | null;
    signed_off_by?: UserSummary | null;
    owner_participant_id?: string | null;
    owner_participant?: {
        id: string;
        responsibility?: string | null;
        user?: UserSummary | null;
    } | null;
    owner?: {
        key: string;
        label: string;
        value: string | null;
        participant_id?: string;
        user?: UserSummary | null;
    } | null;
    metric?: {
        id: string;
        name: string;
        engagement?: { id: string; title: string } | null;
    } | null;
};

type MetricDefinitionHistoryEntry = {
    id: string;
    version?: number | null;
    status?: string | null;
    signed_off_at?: string | null;
    signed_off_by?: UserSummary | null;
    metric?: {
        id: string;
        name: string;
        engagement?: { id: string; title: string } | null;
    } | null;
};

type FieldRoleEntry = {
    key: string;
    label: string;
    role: 'definition' | 'context' | 'governance' | 'diagnostic';
    exclude_from_consensus?: boolean;
    companion_of?: string | null;
    executive_section?:
        | 'official_definition'
        | 'measurement_logic_rules'
        | 'controls_caveats';
};

type ExecutiveSectionCompanion = {
    key: string;
    label: string;
    value: unknown;
};

type ExecutiveSectionItem = {
    key: string;
    label: string;
    value: unknown;
    companions: ExecutiveSectionCompanion[];
};

type ExecutiveSection = {
    key: 'official_definition' | 'measurement_logic_rules' | 'controls_caveats';
    title: string;
    items: ExecutiveSectionItem[];
};

type DetailRow = {
    key: string;
    label: string;
    value: string;
};

type EngagementMetricPayload = {
    metric: {
        id: string;
        status?: string | null;
        last_submitted_at?: string | null;
        field_roles?: FieldRoleEntry[];
    };
};

type OrgMetricDetail = {
    org_metric: {
        id: string;
        key: string;
        slug?: string | null;
        name: string;
        alias?: string | null;
        organization?: Organization | null;
    };
    official_definition: MetricDefinitionPayload | null;
    official_definition_history?: MetricDefinitionHistoryEntry[];
    engagement_metric: EngagementMetricPayload | null;
    executive_sections?: ExecutiveSection[];
};

const props = defineProps<{
    organization: Organization;
    engagement: Engagement;
    detail: OrgMetricDetail;
}>();

const orgMetric = computed(() => props.detail.org_metric);
const orgMetricDisplayName = computed(() =>
    orgMetric.value.alias?.trim()
        ? orgMetric.value.alias
        : orgMetric.value.name,
);
const officialDefinition = computed(() => props.detail.official_definition);
const definitionHistory = computed(
    () => props.detail.official_definition_history ?? [],
);
const executiveSections = computed(() => props.detail.executive_sections ?? []);
const historyExpanded = ref(false);

const normalizeText = (value: string | null | undefined): string | null => {
    const normalized = value?.trim();

    return normalized ? normalized : null;
};

const startCase = (value: string): string =>
    value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase()) || value;

const formatDate = (value: string | null | undefined) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatVersionLabel = (value: number | null | undefined): string => {
    if (value === null || value === undefined) {
        return 'Version unavailable';
    }

    return `Version ${value}`;
};

const stringifyValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (Array.isArray(value)) {
        if (!value.length) {
            return '—';
        }
        return value.map((entry) => stringifyValue(entry)).join(', ');
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);
        if (!entries.length) {
            return '{}';
        }
        return entries
            .map(
                ([key, nested]) =>
                    `${startCase(key)}: ${stringifyValue(nested)}`,
            )
            .join(', ');
    }

    if (typeof value === 'number') {
        return Number.isInteger(value) ? value.toString() : value.toFixed(2);
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'string' && /[_-]/.test(value)) {
        return startCase(value);
    }

    return String(value);
};

const signedOffLabel = computed(() =>
    formatDate(officialDefinition.value?.signed_off_at ?? null),
);
const alignmentSummary = computed(() =>
    normalizeText(officialDefinition.value?.alignment_summary),
);
const operationalStatus = computed(() =>
    normalizeText(officialDefinition.value?.operational_status),
);
const operationalStatusNotes = computed(() =>
    normalizeText(officialDefinition.value?.operational_status_notes),
);
const validationSteps = computed(() =>
    normalizeText(officialDefinition.value?.validation_steps),
);
const commonMismatchCauses = computed(() =>
    normalizeText(officialDefinition.value?.common_mismatch_causes),
);
const implementationGapRows = computed<DetailRow[]>(() => {
    const gaps = officialDefinition.value?.implementation_gaps;

    if (!gaps || typeof gaps !== 'object' || Array.isArray(gaps)) {
        return [];
    }

    const gapRecord = gaps as Record<string, unknown>;

    const nextStep = normalizeText(
        typeof gapRecord.suggested_next_step === 'string'
            ? gapRecord.suggested_next_step
            : typeof gapRecord.next_step === 'string'
              ? gapRecord.next_step
              : null,
    );

    return [
        {
            key: 'current_state',
            label: 'Current State',
            value:
                normalizeText(
                    typeof gapRecord.current_state === 'string'
                        ? gapRecord.current_state
                        : null,
                ) ?? '',
        },
        {
            key: 'gap',
            label: 'Gap',
            value:
                normalizeText(
                    typeof gapRecord.gap === 'string' ? gapRecord.gap : null,
                ) ?? '',
        },
        {
            key: 'owner',
            label: 'Owner',
            value:
                normalizeText(
                    typeof gapRecord.owner === 'string'
                        ? gapRecord.owner
                        : null,
                ) ?? '',
        },
        {
            key: 'suggested_next_step',
            label: 'Suggested Next Step',
            value: nextStep ?? '',
        },
    ].filter((row) => row.value !== '');
});
const validationRows = computed<DetailRow[]>(() => {
    const rows: DetailRow[] = [];

    if (validationSteps.value) {
        rows.push({
            key: 'validation_steps',
            label: 'Validation Steps',
            value: validationSteps.value,
        });
    }

    if (commonMismatchCauses.value) {
        rows.push({
            key: 'common_mismatch_causes',
            label: 'Common Mismatch Causes',
            value: commonMismatchCauses.value,
        });
    }

    return rows;
});
const operationalStatusLabel = computed(() => {
    if (operationalStatus.value === 'operationally_ready') {
        return 'Ready for Use';
    }

    if (operationalStatus.value === 'needs_follow_up') {
        return 'Implementation Follow-up Needed';
    }

    return operationalStatus.value ? startCase(operationalStatus.value) : null;
});
const operationalStatusVariant = computed(() => {
    if (operationalStatus.value === 'operationally_ready') {
        return 'success';
    }

    if (operationalStatus.value === 'needs_follow_up') {
        return 'warning';
    }

    return 'secondary';
});
const hasDefinitionHistory = computed(() => definitionHistory.value.length > 0);
const canToggleDefinitionHistory = computed(
    () => definitionHistory.value.length > 1,
);
const historyToggleLabel = computed(() =>
    historyExpanded.value
        ? 'Hide history'
        : `View history (${definitionHistory.value.length})`,
);
const showDefinitionHistory = computed(
    () =>
        hasDefinitionHistory.value &&
        (!canToggleDefinitionHistory.value || historyExpanded.value),
);
const showSignalSection = computed(() => alignmentSummary.value !== null);
const showImplementationStatusSection = computed(
    () =>
        operationalStatusLabel.value !== null ||
        operationalStatusNotes.value !== null,
);
const showImplementationFollowUpSection = computed(
    () => implementationGapRows.value.length > 0,
);
const showValidationSection = computed(() => validationRows.value.length > 0);
const approvalVersionRows = computed<DetailRow[]>(() => {
    const rows: DetailRow[] = [];
    const owner = officialDefinition.value?.owner;
    const signedOffDate = signedOffLabel.value;
    const signedOffBy = normalizeText(
        officialDefinition.value?.signed_off_by?.name,
    );

    if (owner?.value) {
        rows.push({
            key: 'owner',
            label:
                owner.label === 'Metric Owner' ? owner.label : 'Metric Owner',
            value: owner.value,
        });
    }

    if (
        officialDefinition.value?.version !== null &&
        officialDefinition.value?.version !== undefined
    ) {
        rows.push({
            key: 'version',
            label: 'Current Version',
            value: formatVersionLabel(officialDefinition.value.version),
        });
    }

    if (signedOffDate) {
        rows.push({
            key: 'signed_off_at',
            label: 'Signed Off On',
            value: signedOffDate,
        });
    }

    if (signedOffBy) {
        rows.push({
            key: 'signed_off_by',
            label: 'Approved By',
            value: signedOffBy,
        });
    }

    return rows;
});
const formatHistoryAuthor = (entry: MetricDefinitionHistoryEntry): string =>
    normalizeText(entry.signed_off_by?.name) ?? 'Unknown approver';
const layoutDescription = computed(() =>
    signedOffLabel.value
        ? `Official definition. Signed off ${signedOffLabel.value}.`
        : 'Official definition.',
);
</script>

<template>
    <Head :title="orgMetricDisplayName" />
    <ClientLayout
        :title="orgMetricDisplayName"
        :description="layoutDescription"
    >
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <Card class="mx-auto max-w-5xl border-border/60 bg-card/70">
                <CardContent
                    class="client-metric-handbook space-y-8 md:space-y-10"
                >
                    <section
                        v-if="showSignalSection"
                        data-test="executive-alignment-section"
                        class="client-metric-handbook__section"
                    >
                        <h2 class="client-metric-handbook__section-title">
                            Executive Alignment
                        </h2>

                        <div class="space-y-5">
                            <article
                                v-if="alignmentSummary"
                                data-test="alignment-summary-block"
                                class="client-metric-handbook__item"
                            >
                                <h3 class="client-metric-handbook__label">
                                    Alignment Summary
                                </h3>
                                <p
                                    class="client-metric-handbook__value whitespace-pre-line"
                                >
                                    {{ alignmentSummary }}
                                </p>
                            </article>
                        </div>
                    </section>

                    <section
                        v-if="showImplementationStatusSection"
                        data-test="implementation-status-section"
                        class="client-metric-handbook__section"
                    >
                        <h2 class="client-metric-handbook__section-title">
                            Implementation Status
                        </h2>

                        <div class="space-y-3">
                            <Badge
                                v-if="operationalStatusLabel"
                                data-test="operational-status-badge"
                                :variant="operationalStatusVariant"
                            >
                                {{ operationalStatusLabel }}
                            </Badge>

                            <p class="text-sm leading-6 text-muted-foreground">
                                Status reflects the organization's confirmed
                                implementation state. This does not represent
                                independent validation of source data or
                                dashboard outputs by Metric Harmony.
                            </p>

                            <p
                                v-if="operationalStatusNotes"
                                data-test="operational-status-notes"
                                class="client-metric-handbook__value whitespace-pre-line"
                            >
                                {{ operationalStatusNotes }}
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="showImplementationFollowUpSection"
                        data-test="implementation-follow-up-section"
                        class="client-metric-handbook__section"
                    >
                        <h2 class="client-metric-handbook__section-title">
                            Implementation Follow-up
                        </h2>

                        <dl
                            class="client-metric-handbook__detail-list"
                            data-test="implementation-follow-up-list"
                        >
                            <div
                                v-for="row in implementationGapRows"
                                :key="row.key"
                                class="client-metric-handbook__detail-row"
                            >
                                <dt
                                    class="client-metric-handbook__detail-label"
                                >
                                    {{ row.label }}
                                </dt>
                                <dd
                                    :data-test="`implementation-follow-up-${row.key}`"
                                    class="client-metric-handbook__detail-value whitespace-pre-line"
                                >
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="showValidationSection"
                        data-test="validation-guidance-section"
                        class="client-metric-handbook__section"
                    >
                        <h2 class="client-metric-handbook__section-title">
                            Operational Validation Checklist
                        </h2>

                        <dl class="client-metric-handbook__detail-list">
                            <div
                                v-for="row in validationRows"
                                :key="row.key"
                                class="client-metric-handbook__detail-row"
                            >
                                <dt
                                    class="client-metric-handbook__detail-label"
                                >
                                    {{ row.label }}
                                </dt>
                                <dd
                                    :data-test="row.key"
                                    class="client-metric-handbook__detail-value whitespace-pre-line"
                                >
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="approvalVersionRows.length"
                        data-test="approval-version-section"
                        class="client-metric-handbook__section"
                    >
                        <h2 class="client-metric-handbook__section-title">
                            Approval &amp; Version
                        </h2>

                        <dl class="client-metric-handbook__detail-list">
                            <div
                                v-for="row in approvalVersionRows"
                                :key="row.key"
                                class="client-metric-handbook__detail-row"
                            >
                                <dt
                                    class="client-metric-handbook__detail-label"
                                >
                                    {{ row.label }}
                                </dt>
                                <dd
                                    :data-test="`approval-version-${row.key}`"
                                    class="client-metric-handbook__detail-value"
                                >
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <template v-if="executiveSections.length">
                        <section
                            v-for="section in executiveSections"
                            :key="section.key"
                            :data-test="`executive-section-${section.key}`"
                            class="client-metric-handbook__section"
                        >
                            <h2 class="client-metric-handbook__section-title">
                                {{ section.title }}
                            </h2>

                            <div
                                class="client-metric-handbook__section-items space-y-5"
                            >
                                <article
                                    v-for="item in section.items"
                                    :key="item.key"
                                    :data-test="`official-definition-field-${item.key}`"
                                    class="client-metric-handbook__item"
                                >
                                    <h3 class="client-metric-handbook__label">
                                        {{ item.label }}
                                    </h3>
                                    <p class="client-metric-handbook__value">
                                        {{ stringifyValue(item.value) }}
                                    </p>

                                    <div
                                        v-if="item.companions.length"
                                        class="client-metric-handbook__companions"
                                    >
                                        <div
                                            v-for="companion in item.companions"
                                            :key="companion.key"
                                            :data-test="`official-definition-companion-${companion.key}`"
                                            class="client-metric-handbook__companion"
                                        >
                                            <h4
                                                class="client-metric-handbook__companion-label"
                                            >
                                                {{ companion.label }}
                                            </h4>
                                            <p
                                                class="client-metric-handbook__companion-value"
                                            >
                                                {{
                                                    stringifyValue(
                                                        companion.value,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </template>

                    <p
                        v-else-if="!officialDefinition"
                        class="text-sm text-muted-foreground"
                    >
                        No official definition has been signed off yet.
                    </p>

                    <section
                        v-if="hasDefinitionHistory"
                        data-test="revision-history-section"
                        class="client-metric-handbook__section"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-1">
                                <h2
                                    class="client-metric-handbook__section-title"
                                >
                                    Revision history
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Prior signed versions for this metric.
                                </p>
                            </div>

                            <Button
                                v-if="canToggleDefinitionHistory"
                                type="button"
                                variant="outline"
                                size="sm"
                                data-test="revision-history-toggle"
                                :aria-expanded="historyExpanded"
                                @click="historyExpanded = !historyExpanded"
                            >
                                {{ historyToggleLabel }}
                            </Button>
                        </div>

                        <ol
                            v-if="showDefinitionHistory"
                            data-test="revision-history-list"
                            class="client-metric-handbook__timeline"
                        >
                            <li
                                v-for="entry in definitionHistory"
                                :key="entry.id"
                                data-test="revision-history-item"
                                class="client-metric-handbook__timeline-item"
                            >
                                <div
                                    class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between"
                                >
                                    <h3
                                        data-test="revision-history-version"
                                        class="client-metric-handbook__label mb-0"
                                    >
                                        {{ formatVersionLabel(entry.version) }}
                                    </h3>
                                    <p
                                        data-test="revision-history-date"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{
                                            formatDate(entry.signed_off_at) ??
                                            'Date unavailable'
                                        }}
                                    </p>
                                </div>
                                <p
                                    data-test="revision-history-author"
                                    class="client-metric-handbook__value"
                                >
                                    Signed off by
                                    {{ formatHistoryAuthor(entry) }}
                                </p>
                            </li>
                        </ol>
                    </section>
                </CardContent>
            </Card>
        </div>
    </ClientLayout>
</template>

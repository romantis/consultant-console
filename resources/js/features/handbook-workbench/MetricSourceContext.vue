<script setup lang="ts">
import {
    ArrowRight,
    BookOpen,
    Info,
    MessageSquare,
    User,
} from 'lucide-vue-next';

/**
 * Source context item within a definition section.
 */
interface SourceContextItem {
    key: string;
    label: string;
    value: unknown;
    companions: {
        key: string;
        label: string;
        value: unknown;
    }[];
}

/**
 * A structured section of the signed definition (e.g. Official Definition,
 * Measurement Logic & Rules, Controls & Caveats).
 */
interface SourceContextSection {
    key: string;
    title: string;
    items: SourceContextItem[];
}

/**
 * Owner information extracted from the signed definition.
 */
interface SourceOwner {
    label: string;
    value: string | null;
}

/**
 * The signed definition source context payload from the backend.
 */
export interface SignedDefinitionContext {
    version: number;
    signed_off_at: string | null;
    signed_off_by: { id: string; name: string } | null;
    notes: string | null;
    owner: SourceOwner | null;
    sections: SourceContextSection[];
}

export interface DiscrepancyInfo {
    field_key: string;
    severity: string;
    status: string;
    description: string;
}

interface WorkshopFieldDecision {
    selected?: unknown;
    rationale?: string | null;
    status?: string | null;
}

interface WorkshopDecisions {
    consensus: string | null;
    follow_up: string[];
    field_decisions: Record<string, WorkshopFieldDecision>;
}

export interface WorkshopInfo {
    id: string;
    scheduled_at: string | null;
    notes: string | null;
    decisions: WorkshopDecisions;
    participants?: unknown[];
    workshop_url: string;
}

export interface WorkshopAlignment {
    has_evidence: boolean;
    workshop: WorkshopInfo | null;
    discrepancies: DiscrepancyInfo[];
}

export interface StakeholderPosition {
    stakeholder: string;
    raw_value: unknown;
    normalized_value: unknown;
}

export interface MatrixLeftover {
    field_key: string;
    label: string;
    stakeholder_positions: StakeholderPosition[];
    final_value: unknown;
    status: string;
    reason: string | null;
    used_for: string[];
}

export interface SourceContext {
    signed_definition: SignedDefinitionContext;
    workshop_alignment: WorkshopAlignment;
    matrix_leftovers?: MatrixLeftover[];
}

const props = defineProps<{
    sourceContext: SourceContext | null;
}>();

const uppercaseKeyTokens = new Set([
    'api',
    'arr',
    'cac',
    'cpl',
    'crm',
    'dau',
    'mau',
    'mql',
    'mrr',
    'nps',
    'sql',
    'wau',
]);

function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) {
        return value
            .map((item) => formatValue(item))
            .filter(Boolean)
            .join(', ');
    }
    if (['string', 'number', 'bigint'].includes(typeof value)) {
        return String(value);
    }

    return '';
}

function formatKey(value: string): string {
    return value
        .split('_')
        .map((token) =>
            uppercaseKeyTokens.has(token.toLowerCase())
                ? token.toUpperCase()
                : token.charAt(0).toUpperCase() + token.slice(1),
        )
        .join(' ');
}

function formatDisplayValue(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (Array.isArray(value)) {
        return value
            .map((item) => formatDisplayValue(item))
            .filter(Boolean)
            .join(', ');
    }
    const str = String(value);
    if (str.includes('_')) {
        return formatKey(str);
    }
    return str;
}

function fieldDecisionEntries(
    fieldDecisions: Record<string, WorkshopFieldDecision> | undefined,
) {
    return Object.entries(fieldDecisions ?? {}).map(([key, decision]) => ({
        key,
        ...decision,
    }));
}

function hasFieldDecisions(
    fieldDecisions: Record<string, WorkshopFieldDecision> | undefined,
): boolean {
    return fieldDecisionEntries(fieldDecisions).length > 0;
}

function hasDisplayValue(value: unknown): boolean {
    return formatValue(value) !== '';
}
</script>

<template>
    <div data-test="metric-source-context">
        <template v-if="!props.sourceContext">
            <div
                class="flex flex-col items-center justify-center rounded-lg border border-border/60 bg-muted/20 p-6 text-center"
            >
                <Info class="mb-2 size-5 text-muted-foreground/60" />
                <p class="text-xs text-muted-foreground">
                    Source context is not available for this metric.
                </p>
            </div>
        </template>

        <template v-else>
            <div class="space-y-4">
                <!-- Header with approval metadata -->
                <div
                    class="rounded-lg border border-border/60 bg-muted/20 p-3.5"
                    data-test="source-context-header"
                >
                    <h4
                        class="mb-2 flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <BookOpen class="size-3 text-muted-foreground/80" />
                        Signed Definition
                    </h4>
                    <div
                        class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="props.sourceContext.signed_definition.version"
                            data-test="source-context-version"
                        >
                            v{{ props.sourceContext.signed_definition.version }}
                        </span>
                        <span
                            v-if="
                                props.sourceContext.signed_definition
                                    .signed_off_at
                            "
                            data-test="source-context-signed-at"
                        >
                            Signed
                            {{
                                new Date(
                                    props.sourceContext.signed_definition
                                        .signed_off_at,
                                ).toLocaleDateString()
                            }}
                        </span>
                        <span
                            v-if="
                                props.sourceContext.signed_definition
                                    .signed_off_by
                            "
                            data-test="source-context-signed-by"
                        >
                            by
                            {{
                                props.sourceContext.signed_definition
                                    .signed_off_by.name
                            }}
                        </span>
                    </div>

                    <!-- Owner -->
                    <div
                        v-if="
                            props.sourceContext.signed_definition.owner?.value
                        "
                        class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
                        data-test="source-context-owner"
                    >
                        <User class="size-3 text-muted-foreground/70" />
                        <span class="font-medium">
                            {{
                                props.sourceContext.signed_definition.owner
                                    .label
                            }}:
                        </span>
                        <span>
                            {{
                                props.sourceContext.signed_definition.owner
                                    .value
                            }}
                        </span>
                    </div>

                    <!-- Notes -->
                    <div
                        v-if="
                            props.sourceContext.signed_definition.notes?.trim()
                        "
                        class="mt-2 rounded border border-border/40 bg-background/50 p-2 text-sm leading-relaxed text-foreground/80"
                        data-test="source-context-notes"
                    >
                        {{ props.sourceContext.signed_definition.notes }}
                    </div>
                </div>

                <!-- Definition Sections -->
                <template
                    v-if="
                        props.sourceContext.signed_definition.sections.length >
                        0
                    "
                >
                    <div
                        v-for="section in props.sourceContext.signed_definition
                            .sections"
                        :key="section.key"
                        class="rounded-lg border border-border/60 bg-muted/10 p-3.5"
                        :data-test="`source-section-${section.key}`"
                    >
                        <h4
                            class="mb-2.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ section.title }}
                        </h4>
                        <div class="space-y-2">
                            <div v-for="item in section.items" :key="item.key">
                                <div
                                    class="text-xs font-semibold text-muted-foreground/80"
                                >
                                    {{ item.label }}
                                </div>
                                <div
                                    class="mt-1 text-sm leading-relaxed whitespace-pre-line text-foreground/85"
                                >
                                    {{ formatValue(item.value) || '—' }}
                                </div>

                                <!-- Companions (e.g. notes fields) -->
                                <template
                                    v-if="
                                        item.companions &&
                                        item.companions.length > 0
                                    "
                                >
                                    <div
                                        v-for="companion in item.companions"
                                        :key="companion.key"
                                        class="mt-1 border-l-2 border-border/40 pl-2.5"
                                    >
                                        <div
                                            class="text-xs font-semibold text-muted-foreground/70"
                                        >
                                            {{ companion.label }}
                                        </div>
                                        <div
                                            class="mt-1 text-sm leading-relaxed whitespace-pre-line text-foreground/75"
                                        >
                                            {{
                                                formatValue(companion.value) ||
                                                '—'
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty sections state -->
                <div
                    v-if="
                        props.sourceContext.signed_definition.sections
                            .length === 0
                    "
                    class="rounded-lg border border-dashed border-border/50 p-4 text-center text-sm text-muted-foreground/60 italic"
                    data-test="source-context-empty-sections"
                >
                    No structured definition fields are available for this
                    metric.
                </div>

                <!-- Workshop Alignment Section -->
                <div
                    class="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3.5"
                    data-test="workshop-alignment-panel"
                >
                    <h4
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <MessageSquare
                            class="size-3 text-muted-foreground/80"
                        />
                        Workshop Alignment
                    </h4>

                    <!-- Missing Evidence State -->
                    <div
                        v-if="
                            !props.sourceContext.workshop_alignment
                                ?.has_evidence
                        "
                        class="text-sm text-muted-foreground/60 italic"
                        data-test="workshop-alignment-missing"
                    >
                        No workshop alignment evidence recorded for this metric.
                    </div>

                    <!-- Available Evidence -->
                    <div v-else class="space-y-3">
                        <!-- Decisions (Consensus, Follow-up) -->
                        <div
                            v-if="
                                props.sourceContext.workshop_alignment.workshop
                                    ?.decisions &&
                                Object.keys(
                                    props.sourceContext.workshop_alignment
                                        .workshop.decisions,
                                ).length > 0
                            "
                            class="space-y-2 text-sm"
                            data-test="workshop-alignment-decisions"
                        >
                            <!-- Consensus -->
                            <div
                                v-if="
                                    props.sourceContext.workshop_alignment
                                        .workshop.decisions.consensus
                                "
                            >
                                <span
                                    class="font-semibold text-muted-foreground"
                                    >Consensus Decision:</span
                                >
                                <p
                                    class="mt-0.5 leading-relaxed text-foreground/85"
                                >
                                    {{
                                        props.sourceContext.workshop_alignment
                                            .workshop.decisions.consensus
                                    }}
                                </p>
                            </div>

                            <!-- Follow-up -->
                            <div
                                v-if="
                                    props.sourceContext.workshop_alignment
                                        .workshop.decisions.follow_up &&
                                    props.sourceContext.workshop_alignment
                                        .workshop.decisions.follow_up.length > 0
                                "
                            >
                                <span
                                    class="font-semibold text-muted-foreground"
                                    >Follow-up:</span
                                >
                                <ul
                                    class="mt-1 list-disc space-y-1 pl-4 text-foreground/80"
                                >
                                    <li
                                        v-for="(item, idx) in props
                                            .sourceContext.workshop_alignment
                                            .workshop.decisions.follow_up"
                                        :key="idx"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>

                            <!-- Field-level decisions -->
                            <div
                                v-if="
                                    hasFieldDecisions(
                                        props.sourceContext.workshop_alignment
                                            .workshop.decisions.field_decisions,
                                    )
                                "
                                class="space-y-1.5"
                                data-test="workshop-alignment-field-decisions"
                            >
                                <span
                                    class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Field Decisions
                                </span>
                                <div class="space-y-1">
                                    <div
                                        v-for="decision in fieldDecisionEntries(
                                            props.sourceContext
                                                .workshop_alignment.workshop
                                                .decisions.field_decisions,
                                        )"
                                        :key="decision.key"
                                        class="rounded border border-border/40 bg-background/50 p-2 leading-relaxed"
                                    >
                                        <div
                                            class="text-sm font-semibold text-foreground/90"
                                        >
                                            {{ formatKey(decision.key) }}
                                        </div>
                                        <div
                                            v-if="
                                                hasDisplayValue(
                                                    decision.selected,
                                                )
                                            "
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            <span class="font-semibold"
                                                >Selected:</span
                                            >
                                            {{ formatValue(decision.selected) }}
                                        </div>
                                        <p
                                            v-if="decision.rationale?.trim()"
                                            class="mt-1 text-sm text-foreground/75"
                                        >
                                            {{ decision.rationale }}
                                        </p>
                                        <span
                                            v-if="decision.status?.trim()"
                                            class="mt-1 inline-block rounded border border-border/60 bg-muted px-1.5 py-0.5 text-xs font-semibold text-muted-foreground"
                                        >
                                            {{ formatKey(decision.status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Discrepancies -->
                        <div
                            v-if="
                                props.sourceContext.workshop_alignment
                                    .discrepancies &&
                                props.sourceContext.workshop_alignment
                                    .discrepancies.length > 0
                            "
                            class="space-y-1.5"
                            data-test="workshop-alignment-discrepancies"
                        >
                            <span
                                class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Resolved Discrepancies
                            </span>
                            <div class="space-y-1">
                                <div
                                    v-for="d in props.sourceContext
                                        .workshop_alignment.discrepancies"
                                    :key="d.field_key"
                                    class="rounded border border-border/40 bg-background/50 p-2 text-sm leading-relaxed text-foreground/80"
                                >
                                    <div
                                        class="font-semibold text-foreground/90"
                                    >
                                        {{ d.description }}
                                    </div>
                                    <span
                                        class="mt-1 inline-block rounded border border-border/60 bg-muted px-1.5 py-0.5 text-xs font-semibold text-muted-foreground capitalize"
                                    >
                                        {{ d.severity }} severity
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Collapsible Discussion Notes -->
                        <details
                            v-if="
                                props.sourceContext.workshop_alignment.workshop?.notes?.trim()
                            "
                            class="group text-sm"
                            data-test="workshop-alignment-notes-details"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center gap-1 font-medium text-primary select-none hover:underline"
                            >
                                <span
                                    class="transition-transform group-open:rotate-90"
                                    >▶</span
                                >
                                View Discussion Notes
                            </summary>
                            <p
                                class="mt-2 rounded border border-border/40 bg-background/50 p-2 leading-relaxed whitespace-pre-line text-foreground/75"
                            >
                                {{
                                    props.sourceContext.workshop_alignment
                                        .workshop.notes
                                }}
                            </p>
                        </details>

                        <!-- Go to Workshop link -->
                        <div
                            v-if="
                                props.sourceContext.workshop_alignment.workshop
                                    ?.workshop_url
                            "
                        >
                            <a
                                :href="
                                    props.sourceContext.workshop_alignment
                                        .workshop.workshop_url
                                "
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                data-test="workshop-alignment-link"
                            >
                                Go to Workshop page
                                <ArrowRight class="size-3" />
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Matrix Leftovers Section -->
                <div
                    v-if="
                        props.sourceContext.matrix_leftovers &&
                        props.sourceContext.matrix_leftovers.length > 0
                    "
                    class="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3.5"
                    data-test="matrix-leftovers-panel"
                >
                    <h4
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <Info class="size-3.5 text-muted-foreground/80" />
                        Matrix Leftovers
                    </h4>
                    <div class="space-y-3">
                        <div
                            v-for="item in props.sourceContext.matrix_leftovers"
                            :key="item.field_key"
                            class="rounded border border-border/40 bg-background/50 p-3 leading-relaxed"
                            :data-test="`matrix-leftover-${item.field_key}`"
                        >
                            <!-- Label -->
                            <div
                                class="text-sm font-semibold text-foreground/90"
                            >
                                {{ item.label }}
                            </div>

                            <!-- Final value -->
                            <div class="mt-1.5 text-xs text-muted-foreground">
                                <span class="font-bold">Final Decision:</span>
                                <span
                                    class="ml-1 inline-block rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wider text-primary uppercase"
                                >
                                    {{
                                        formatDisplayValue(item.final_value) ||
                                        '—'
                                    }}
                                </span>
                            </div>

                            <!-- Stakeholder Positions -->
                            <div class="mt-2 space-y-1">
                                <div
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Stakeholder Positions:
                                </div>
                                <div
                                    class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="pos in item.stakeholder_positions"
                                        :key="pos.stakeholder"
                                        class="flex items-center justify-between rounded border border-border/40 bg-muted/30 p-2 text-xs"
                                        :data-test="`leftover-stakeholder-${item.field_key}-${pos.stakeholder}`"
                                    >
                                        <span
                                            class="font-medium text-muted-foreground"
                                            >{{ pos.stakeholder }}</span
                                        >
                                        <span
                                            class="font-semibold text-foreground/85"
                                            >{{
                                                formatDisplayValue(
                                                    pos.raw_value,
                                                ) || '—'
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded border px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                                    :class="
                                        item.status ===
                                        'preserved_operating_view'
                                            ? 'border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400'
                                            : item.status === 'follow_up'
                                              ? 'border-amber-200/60 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400'
                                              : 'border-border bg-muted text-muted-foreground'
                                    "
                                    :data-test="`leftover-status-${item.field_key}`"
                                >
                                    {{ formatKey(item.status) }}
                                </span>
                            </div>

                            <!-- Reason -->
                            <p
                                v-if="item.reason?.trim()"
                                class="mt-2 border-l-2 border-border/60 bg-muted/20 py-1 pl-2 text-xs leading-relaxed text-foreground/75 italic"
                                :data-test="`leftover-reason-${item.field_key}`"
                            >
                                {{ item.reason }}
                            </p>

                            <!-- Used for tags -->
                            <div
                                class="mt-2.5 flex flex-wrap items-center gap-1"
                            >
                                <span
                                    class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Used for:</span
                                >
                                <span
                                    v-for="use in item.used_for"
                                    :key="use"
                                    class="rounded border bg-muted px-1.5 py-0.5 text-[9px] font-semibold text-muted-foreground capitalize"
                                >
                                    {{ use.replace(/_/g, ' ') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

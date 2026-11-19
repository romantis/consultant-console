import { flushPromises, mount } from '@vue/test-utils';
import { cloneVNode, defineComponent, h, inject, provide, ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WorkshopPrepPage from '@/pages/Metrics/WorkshopPrep.vue';
import { router } from '@inertiajs/vue3';
import FacilitationPlanPanel from '@/components/metrics/FacilitationPlanPanel.vue';

import type {
    FacilitationBrief,
    FacilitatorReadinessPack,
    FieldRole,
    MetricAlignmentBrief,
    SourceRef,
    WorkshopCue,
    WorkshopCueContext,
} from '@/types';

type PageProps = {
    organization: { id: string; name: string };
    engagement: { id: string; title: string; status: string };
    metric: {
        id: string;
        name: string;
        status: string;
        key: string;
        field_roles: FieldRole[];
        unclassified: string[];
    };
    fieldValueLabels?: Record<string, Record<string, string>>;
    metric_alignment_brief: MetricAlignmentBrief;
    facilitation_brief?: FacilitationBrief | null;
    facilitator_readiness_pack?: FacilitatorReadinessPack | null;
    engagement_metrics?: Array<{ id: string; name: string; status: string }>;
};

const facilitationBriefFixture = (
    overrides: Partial<FacilitationBrief> = {},
): FacilitationBrief => ({
    id: 'fac-1',
    status: 'draft',
    schema_version: '2.0',
    source_fingerprint: 'fingerprint-1',
    revision: 1,
    needs_source_review: false,
    document: {
        source_snapshot: {
            brief_fingerprint: 'fingerprint-1',
            generated_draft: {
                schema_version: '1.0',
                prompt_version: '1.2.1',
                profile_version: '1.0.0',
            },
        },
        coverage: {
            agenda_topic_ids: ['topic:0', 'topic:1'],
            covered_topic_ids: ['topic:0', 'topic:1'],
            omitted_topics: [],
        },
        decisions_to_lock: [
            {
                id: 'decision:0',
                title: 'Lock Official Definition',
                priority: 'high',
                agenda_topic_ids: ['topic:0'],
                field_keys: ['overhead_handling'],
                decision_prompt: {
                    text: 'Agree the official overhead allocation method.',
                    provenance: 'source_backed',
                    source_item_ids: [],
                    citation_ids: [],
                },
            },
        ],
        run_of_show: [
            {
                id: 'segment:framing',
                position: 1,
                duration_minutes: 10,
                title: 'Frame the metric and alignment goal',
                decision_ids: [],
                purpose: {
                    text: 'Open the workshop and set expectations.',
                    provenance: 'system_suggested',
                    source_item_ids: [],
                    citation_ids: [],
                },
            },
            {
                id: 'segment:official_definition',
                position: 2,
                duration_minutes: 15,
                title: 'Lock Official Definition',
                decision_ids: ['decision:0'],
                purpose: {
                    text: 'Agree the official overhead allocation method.',
                    provenance: 'system_suggested',
                    source_item_ids: [],
                    citation_ids: [],
                },
            },
        ],
        expected_outputs: [
            {
                id: 'output:official_definition',
                label: 'Official Definition agreed',
                status: 'planned',
                decision_ids: ['decision:0'],
                description: {
                    text: 'A documented decision for the official definition.',
                    provenance: 'system_suggested',
                    source_item_ids: [],
                    citation_ids: [],
                },
            },
        ],
    },
    ...overrides,
});

const focusTimeBrief = () => ({
    schema_version: '1.0',
    status: 'draft',
    metric_id: 'focus-time-metric',
    metric_name: 'Focus Time Alignment',
    summary: {
        text: 'Ideal focus hours per workday has 1 contested aspect.',
        citation_ids: [],
    },
    material_conflicts: [
        {
            id: 'field:focus_hours_target:material_conflict',
            field_key: 'focus_hours_target',
            field_label: 'Ideal focus hours per workday',
            title: 'Stakeholders disagree on Ideal focus hours per workday',
            rationale: 'Engineering and Product differ on the target hours.',
            source_refs: [
                {
                    id: 'submitted_answer:usr-1:focus_hours_target',
                    type: 'submitted_answer',
                    label: 'Avery Lee (Engineering)',
                    field_key: 'focus_hours_target',
                    source_id: 'usr-1',
                    excerpt: '4',
                },
                {
                    id: 'submitted_answer:usr-2:focus_hours_target',
                    type: 'submitted_answer',
                    label: 'Jordan Park (Product)',
                    field_key: 'focus_hours_target',
                    source_id: 'usr-2',
                    excerpt: '2',
                }
            ],
        }
    ],
    decision_needed: [],
    missing_evidence: [],
    context_signals: [],
    likely_non_issues: [],
    recommended_questions: [],
    resolution_options: [],
    cue_contexts: [
        {
            field_key: 'focus_hours_target',
            field_label: 'Ideal focus hours per workday',
            field_role: 'definition',
            stakeholder_positions: [
                {
                    field_key: 'focus_hours_target',
                    field_label: 'Ideal focus hours per workday',
                    stakeholder: {
                        id: 'usr-1',
                        name: 'Avery Lee',
                        affiliation: 'Engineering',
                    },
                    definition_id: 'usr-1',
                    raw_value: '4',
                    normalized_value: '4',
                    display_value: '4',
                    submitted_at: null,
                    source_refs: [
                        {
                            id: 'submitted_answer:usr-1:focus_hours_target',
                            type: 'submitted_answer',
                            label: 'Avery Lee (Engineering)',
                            field_key: 'focus_hours_target',
                            source_id: 'usr-1',
                            excerpt: '4',
                        }
                    ],
                },
                {
                    field_key: 'focus_hours_target',
                    field_label: 'Ideal focus hours per workday',
                    stakeholder: {
                        id: 'usr-2',
                        name: 'Jordan Park',
                        affiliation: 'Product',
                    },
                    definition_id: 'usr-2',
                    raw_value: '2',
                    normalized_value: '2',
                    display_value: '2',
                    submitted_at: null,
                    source_refs: [
                        {
                            id: 'submitted_answer:usr-2:focus_hours_target',
                            type: 'submitted_answer',
                            label: 'Jordan Park (Product)',
                            field_key: 'focus_hours_target',
                            source_id: 'usr-2',
                            excerpt: '2',
                        }
                    ],
                }
            ],
            companion_answers: [],
            matrix_context: [],
            workshop_guidance: null,
            alignment_signal: {
                id: 'focus-hours-disc-1',
                severity: 'medium',
                description:
                    'The team disagrees on how many hours of uninterrupted focus time are realistic in a day.',
                status: 'open',
            },
            citation_ids: ['submitted_answer:usr-1:focus_hours_target', 'submitted_answer:usr-2:focus_hours_target'],
        }
    ],
    workshop_cues: [],
});

const focusTimeFacilitationBrief = () => ({
    id: 'brief-123',
    status: 'draft',
    schema_version: '2.0',
    source_fingerprint: 'fp-123',
    revision: 1,
    needs_source_review: false,
    document: {
        schema_version: '2.0',
        status: 'draft',
        metric_id: 'focus-time-metric',
        metric_name: 'Focus Time Alignment',
        workshop_thesis: {
            text: 'Ideal focus hours per workday has 1 contested aspect.',
            citation_ids: [],
        },
        decisions_to_lock: [
            {
                id: 'decision:focus_hours_target',
                field_keys: ['focus_hours_target'],
                priority: 'high',
                title: 'Ideal focus hours per workday',
                agenda_topic_ids: ['field:focus_hours_target:material_conflict'],
                decision_prompt: {
                    text: 'Reconcile the ideal focus hours target.',
                    citation_ids: ['submitted_answer:usr-1:focus_hours_target', 'submitted_answer:usr-2:focus_hours_target'],
                },
            }
        ],
        run_of_show: [
            {
                id: 'segment:focus_hours_target',
                title: 'Lock: Ideal focus hours per workday',
                duration_minutes: 10,
                decision_ids: ['decision:focus_hours_target'],
                purpose: {
                    text: 'Discuss and agree on the baseline target for deep work hours.',
                    provenance: 'system_suggested',
                },
            }
        ],
        expected_outputs: [],
        readiness_gaps: [],
    },
});

let consoleWarnSpy: ReturnType<typeof vi.spyOn>;
const originalConsoleWarn = console.warn.bind(console);

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

vi.mock('@inertiajs/vue3', () => {
    const Head = defineComponent({
        name: 'InertiaHeadStub',
        setup(_, { slots }) {
            return () => h('div', { 'data-test': 'inertia-head' }, slots.default ? slots.default() : []);
        },
    });

    const Link = defineComponent({
        name: 'InertiaLinkStub',
        props: {
            href: {
                type: [String, Object],
                required: true,
            },
        },
        setup(props, { slots }) {
            return () => h('a', { 'data-href': typeof props.href === 'string' ? props.href : '' }, slots.default ? slots.default() : []);
        },
    });

    const router = {
        on: vi.fn().mockReturnValue(() => {}),
        visit: vi.fn(),
        reload: vi.fn(),
    };

    return {
        Head,
        Link,
        router,
    };
});

vi.mock('@/layouts/AppShell.vue', () => ({
    default: defineComponent({
        name: 'AppShellStub',
        props: {
            title: { type: String, required: false },
            description: { type: String, required: false },
        },
        setup(props, { slots }) {
            return () => h('section', [
                h('h1', slots.title ? slots.title() : props.title),
                slots.actions ? h('div', { 'data-test': 'shell-actions' }, slots.actions()) : null,
                slots.default ? slots.default() : null,
            ]);
        },
    }),
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardDescription: basicWrapper('p'),
    CardContent: basicWrapper('article'),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: basicWrapper('span'),
}));

vi.mock('@/components/ui/button', () => ({
    Button: defineComponent({
        name: 'ButtonStub',
        props: { variant: { type: String, default: 'default' } },
        setup(props, { slots }) {
            return () => h('button', { 'data-variant': props.variant }, slots.default ? slots.default() : []);
        },
    }),
}));

// Lightweight Dialog stubs that model open/close so confirmation flows render
// the content only after the trigger is clicked (no reka-ui teleport in jsdom).
vi.mock('@/components/ui/dialog', () => {
    const Dialog = defineComponent({
        name: 'DialogStub',
        setup(_, { slots }) {
            provide('dialogOpen', ref(false));

            return () => h('div', slots.default ? slots.default() : []);
        },
    });
    const DialogTrigger = defineComponent({
        name: 'DialogTriggerStub',
        setup(_, { slots }) {
            const open = inject<{ value: boolean }>('dialogOpen');

            return () => {
                const defaultSlot = slots.default ? slots.default() : [];
                if (defaultSlot.length === 1) {
                    const child = defaultSlot[0];
                    return cloneVNode(child, {
                        onClick: () => {
                            if (open) open.value = true;
                        }
                    });
                }
                return h(
                    'div',
                    {
                        onClick: () => {
                            if (open) open.value = true;
                        },
                    },
                    defaultSlot,
                );
            };
        },
    });
    const DialogContent = defineComponent({
        name: 'DialogContentStub',
        setup(_, { slots, attrs }) {
            const open = inject<{ value: boolean }>('dialogOpen');

            return () =>
                open && open.value
                    ? h('div', attrs, slots.default ? slots.default() : [])
                    : null;
        },
    });
    const DialogClose = defineComponent({
        name: 'DialogCloseStub',
        setup(_, { slots }) {
            const open = inject<{ value: boolean }>('dialogOpen');

            return () =>
                h(
                    'div',
                    {
                        onClick: () => {
                            if (open) open.value = false;
                        },
                    },
                    slots.default ? slots.default() : [],
                );
        },
    });
    const passthrough = (tag: string) =>
        defineComponent({
            name: `${tag}DialogStub`,
            setup(_, { slots, attrs }) {
                return () => h(tag, attrs, slots.default ? slots.default() : []);
            },
        });

    return {
        Dialog,
        DialogTrigger,
        DialogContent,
        DialogClose,
        DialogHeader: passthrough('div'),
        DialogTitle: passthrough('h2'),
        DialogDescription: passthrough('p'),
        DialogFooter: passthrough('div'),
    };
});

vi.mock('@/components/ui/alert', () => ({
    Alert: basicWrapper('section'),
    AlertTitle: basicWrapper('h3'),
    AlertDescription: basicWrapper('p'),
}));

vi.mock('lucide-vue-next', () => {
    const icon = (name: string) => defineComponent({
        name: `${name}IconStub`,
        setup(_, { attrs }) {
            return () => h('svg', attrs);
        },
    });
    return {
        Check: icon('Check'),
        CheckCircle2: icon('CheckCircle2'),
        Play: icon('Play'),
        Compass: icon('Compass'),
        ClipboardCheck: icon('ClipboardCheck'),
        ArrowRight: icon('ArrowRight'),
        ArrowUp: icon('ArrowUp'),
        ArrowDown: icon('ArrowDown'),
        GripVertical: icon('GripVertical'),
        Plus: icon('Plus'),
        HelpCircle: icon('HelpCircle'),
        Info: icon('Info'),
        Calendar: icon('Calendar'),
        Users: icon('Users'),
        ChevronRight: icon('ChevronRight'),
        ChevronDown: icon('ChevronDown'),
        ChevronUp: icon('ChevronUp'),
        Lock: icon('Lock'),
        ListFilter: icon('ListFilter'),
        FileText: icon('FileText'),
        AlertCircle: icon('AlertCircle'),
        CheckSquare: icon('CheckSquare'),
        Hammer: icon('Hammer'),
        AlertTriangle: icon('AlertTriangle'),
        CircleHelp: icon('CircleHelp'),
        LoaderCircle: icon('LoaderCircle'),
        RefreshCw: icon('RefreshCw'),
        Search: icon('Search'),
        Sparkles: icon('Sparkles'),
        Trash2: icon('Trash2'),
    };
});

vi.mock('@/actions/App/Http/Controllers/Organization/EngagementWorkshopController', () => ({
    default: (args: any) => ({ url: `/organizations/${args[0]}/engagements/${args[1]}/workshop` }),
}));

vi.mock('@/actions/App/Http/Controllers/MetricWorkshopPrepController', () => {
    const url = (args: any) =>
        `/organizations/${args.organization}/engagements/${args.engagement}/metrics/${args.metric}/prep`;
    const defaultExport = { url };
    return {
        default: defaultExport,
        generate: (args: any) => ({
            url: `/organizations/${args[0]}/engagements/${args[1]}/metrics/${args[2]}/prep/draft`,
            method: 'post',
        }),
        discard: (args: any) => ({
            url: `/organizations/${args[0]}/engagements/${args[1]}/metrics/${args[2]}/prep/draft`,
            method: 'delete',
        }),
        storeFacilitation: (args: any) => ({
            url: `/organizations/${args[0]}/engagements/${args[1]}/metrics/${args[2]}/prep/facilitation`,
            method: 'post',
        }),
        updateFacilitation: (args: any) => ({
            url: `/organizations/${args[0]}/engagements/${args[1]}/metrics/${args[2]}/prep/facilitation`,
            method: 'patch',
        }),
    };
});

vi.mock('@/routes/org', () => ({
    default: {
        index: () => '/organizations',
        show: (orgId: any) => `/organizations/${orgId}`,
    },
}));

vi.mock('@/routes/org/engagements', () => ({
    index: (orgId: any) => `/organizations/${orgId}/engagements`,
    show: (args: any) => `/organizations/${args[0]}/engagements/${args[1]}`,
}));

vi.mock('@/routes/org/metrics', () => ({
    show: (args: any) => `/organizations/${args[0]}/metrics/${args[1]}`,
}));

const createFieldRole = (overrides: Partial<FieldRole> = {}): FieldRole => ({
    key: 'overhead_handling',
    label: 'Overhead Handling',
    role: 'definition',
    required: true,
    exclude_from_consensus: false,
    companion_of: null,
    executive_section: 'official_definition',
    value: null,
    unclassified: false,
    workshop_prompt: 'How should corporate overhead be allocated?',
    workshop_guidance: 'Facilitator should drive consensus on whether to use head-count or revenue ratio.',
    alignment_signal: {
        id: 'disc-1',
        severity: 'medium',
        description: 'Finance uses head-count while Sales uses revenue ratio.',
        status: 'Open',
    },
    values: [
        {
            definition_id: 'def-1',
            raw: 'head_count',
            normalized: 'head_count',
            user: { id: 'usr-1', name: 'Alice Finance' },
            submitted_at: '2026-05-29T10:00:00Z',
        },
        {
            definition_id: 'def-2',
            raw: 'revenue_ratio',
            normalized: 'revenue_ratio',
            user: { id: 'usr-2', name: 'Bob Sales' },
            submitted_at: '2026-05-29T10:30:00Z',
        },
    ],
    ...overrides,
});

const mockBrief = (overrides: Partial<MetricAlignmentBrief> = {}): MetricAlignmentBrief => ({
    summary: {
        metric_id: 'metric-789',
        metric_name: 'Gross Churn Rate',
        status: 'InReview',
        source_counts: {
            submitted_answers: 2,
            alignment_signals: 1,
            guided_fields: 1,
            missing_evidence_items: 0,
        },
    },
    stakeholder_positions: [],
    companion_answers: [],
    cue_contexts: [],
    material_conflicts: [
        {
            id: 'field:overhead_handling:material_conflict',
            category: 'material_conflict',
            field_key: 'overhead_handling',
            field_label: 'Overhead Handling',
            field_role: 'definition',
            severity: 'medium',
            title: 'Stakeholders disagree on Overhead Handling.',
            rationale: 'Stakeholders submitted different normalized values.',
            stakeholder_positions: [],
            source_refs: [
                {
                    id: 'submitted_answer:def-1:overhead_handling',
                    type: 'submitted_answer',
                    label: 'Alice Finance answer',
                    field_key: 'overhead_handling',
                    source_id: 'def-1',
                    excerpt: 'Head Count Ratio',
                },
                {
                    id: 'submitted_answer:def-2:overhead_handling',
                    type: 'submitted_answer',
                    label: 'Bob Sales answer',
                    field_key: 'overhead_handling',
                    source_id: 'def-2',
                    excerpt: 'Revenue Ratio',
                },
                {
                    id: 'alignment_signal:disc-1:overhead_handling',
                    type: 'alignment_signal',
                    label: 'Detector signal',
                    field_key: 'overhead_handling',
                    source_id: 'disc-1',
                    excerpt:
                        'Finance uses head-count while Sales uses revenue ratio.',
                }
            ],
        }
    ],
    decision_needed: [],
    missing_evidence: [
        {
            id: 'field:missing_field:missing_evidence',
            category: 'missing_evidence',
            field_key: 'missing_field',
            field_label: 'Missing Field',
            field_role: 'definition',
            severity: 'low',
            title: 'Missing stakeholder answers for Missing Field.',
            rationale: 'This required or guided field has fewer than two submitted answers to compare.',
            stakeholder_positions: [],
            source_refs: [
                {
                    id: 'field_guidance:missing_field',
                    type: 'field_guidance',
                    label: 'Workshop prompt / guidance',
                    field_key: 'missing_field',
                    source_id: null,
                    excerpt: 'Workshop guidance description',
                }
            ],
        }
    ],
    context_signals: [],
    likely_non_issues: [],
    recommended_questions: [
        {
            id: 'question:overhead_handling',
            field_key: 'overhead_handling',
            question: 'How should corporate overhead be allocated?',
            reason: 'Stakeholders submitted different positions.',
            priority: 'high',
            source_refs: []
        }
    ],
    resolution_options: [],
    draft_alignment_summary: null,
    ...overrides,
});

const cueContextFixture = (
    fieldKey = 'overhead_handling',
): WorkshopCueContext => ({
    field_key: fieldKey,
    field_label: 'Overhead Handling',
    field_role: 'definition',
    stakeholder_positions: [
        {
            field_key: fieldKey,
            field_label: 'Overhead Handling',
            stakeholder: {
                id: 'usr-1',
                name: 'Alice Finance',
                affiliation: 'Finance',
            },
            definition_id: 'def-1',
            raw_value: 'head_count',
            normalized_value: 'head_count',
            display_value: 'Head Count Ratio',
            submitted_at: '2026-05-29T10:00:00Z',
            source_refs: [
                {
                    id: `submitted_answer:def-1:${fieldKey}`,
                    type: 'submitted_answer',
                    label: 'Alice Finance answer',
                    field_key: fieldKey,
                    source_id: 'def-1',
                    excerpt: 'Head Count Ratio',
                },
            ],
        },
    ],
    companion_answers: [],
    workshop_guidance: {
        text: 'Drive consensus on the allocation basis.',
        source_refs: [
            {
                id: `field_guidance:${fieldKey}:guidance`,
                type: 'field_guidance',
                label: 'Workshop guidance',
                field_key: fieldKey,
                source_id: null,
                excerpt: 'Drive consensus on the allocation basis.',
                meta: { guidance_kind: 'workshop_guidance' },
            },
        ],
        citation_ids: [`field_guidance:${fieldKey}:guidance`],
    },
    matrix_context: [
        {
            field_key: 'cac_pain_points',
            field_label: 'CAC/CPL divergence points',
            field_role: 'diagnostic',
            stakeholder_positions: [],
            source_refs: [],
            citation_ids: [],
        },
    ],
    citation_ids: [
        `submitted_answer:def-1:${fieldKey}`,
        `field_guidance:${fieldKey}:guidance`,
    ],
    alignment_signal: null,
});

const workshopCueFixture = (
    fieldKey = 'overhead_handling',
    overrides: Partial<WorkshopCue> = {},
): WorkshopCue => ({
    id: `workshop_cue:${fieldKey}:fixture`,
    field_key: fieldKey,
    field_label: 'Overhead Handling',
    status: 'generated',
    edit_state: 'review_only',
    provenance: 'local_fixture',
    why_this_cue: {
        text: 'The wider matrix says the overhead choice changes who owns the official board baseline.',
        draft_text:
            'The wider matrix says the overhead choice changes who owns the official board baseline.',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: `submitted_answer:def-1:${fieldKey}`,
                type: 'submitted_answer',
                label: 'Alice Finance answer',
                field_key: fieldKey,
                source_id: 'def-1',
                excerpt: 'Head Count Ratio',
            },
            {
                id: 'submitted_answer:def-1:cac_pain_points',
                type: 'submitted_answer',
                label: 'Alice Finance answer',
                field_key: 'cac_pain_points',
                source_id: 'def-1',
                excerpt: 'Finance needs a board baseline.',
            },
        ],
        citation_ids: [
            `submitted_answer:def-1:${fieldKey}`,
            'submitted_answer:def-1:cac_pain_points',
        ],
    },
    decision_cue: {
        text: 'Name the official view and the audience it serves before debating allocation mechanics.',
        draft_text:
            'Name the official view and the audience it serves before debating allocation mechanics.',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: `field_guidance:${fieldKey}:guidance`,
                type: 'field_guidance',
                label: 'Workshop guidance',
                field_key: fieldKey,
                source_id: null,
                excerpt: 'Drive consensus on the allocation basis.',
                meta: { guidance_kind: 'workshop_guidance' },
            },
        ],
        citation_ids: [`field_guidance:${fieldKey}:guidance`],
    },
    probe_cue: {
        text: 'If overhead stays in scope, what allocation basis can Finance publish and Marketing reproduce?',
        draft_text:
            'If overhead stays in scope, what allocation basis can Finance publish and Marketing reproduce?',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: 'submitted_answer:def-1:cac_pain_points',
                type: 'submitted_answer',
                label: 'Alice Finance answer',
                field_key: 'cac_pain_points',
                source_id: 'def-1',
                excerpt: 'Finance needs a board baseline.',
            },
        ],
        citation_ids: ['submitted_answer:def-1:cac_pain_points'],
    },
    risk_cue: {
        text: 'If the audience stays implicit, both teams can look aligned while still publishing different CAC/CPL values.',
        draft_text:
            'If the audience stays implicit, both teams can look aligned while still publishing different CAC/CPL values.',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: 'submitted_answer:def-1:cac_pain_points',
                type: 'submitted_answer',
                label: 'Alice Finance answer',
                field_key: 'cac_pain_points',
                source_id: 'def-1',
                excerpt: 'Finance needs a board baseline.',
            },
        ],
        citation_ids: ['submitted_answer:def-1:cac_pain_points'],
    },
    tradeoff_cue: {
        text: 'Campaign-only CAC is more channel-actionable, while fully loaded CAC is more defensible for finance review.',
        draft_text:
            'Campaign-only CAC is more channel-actionable, while fully loaded CAC is more defensible for finance review.',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: `submitted_answer:def-1:${fieldKey}`,
                type: 'submitted_answer',
                label: 'Alice Finance answer',
                field_key: fieldKey,
                source_id: 'def-1',
                excerpt: 'Head Count Ratio',
            },
        ],
        citation_ids: [`submitted_answer:def-1:${fieldKey}`],
    },
    decision_wording: {
        text: 'Official CAC/CPL includes allocated overhead for the board baseline; campaign-only CAC remains a labeled operating view.',
        draft_text:
            'Official CAC/CPL includes allocated overhead for the board baseline; campaign-only CAC remains a labeled operating view.',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: `submitted_answer:def-1:${fieldKey}`,
                type: 'submitted_answer',
                label: 'Alice Finance answer',
                field_key: fieldKey,
                source_id: 'def-1',
                excerpt: 'Head Count Ratio',
            },
            {
                id: `field_guidance:${fieldKey}:guidance`,
                type: 'field_guidance',
                label: 'Workshop guidance',
                field_key: fieldKey,
                source_id: null,
                excerpt: 'Drive consensus on the allocation basis.',
                meta: { guidance_kind: 'workshop_guidance' },
            },
        ],
        citation_ids: [
            `submitted_answer:def-1:${fieldKey}`,
            `field_guidance:${fieldKey}:guidance`,
        ],
    },
    source_refs: [
        {
            id: `submitted_answer:def-1:${fieldKey}`,
            type: 'submitted_answer',
            label: 'Alice Finance answer',
            field_key: fieldKey,
            source_id: 'def-1',
            excerpt: 'Head Count Ratio',
        },
        {
            id: `field_guidance:${fieldKey}:guidance`,
            type: 'field_guidance',
            label: 'Workshop guidance',
            field_key: fieldKey,
            source_id: null,
            excerpt: 'Drive consensus on the allocation basis.',
            meta: { guidance_kind: 'workshop_guidance' },
        },
        {
            id: 'submitted_answer:def-1:cac_pain_points',
            type: 'submitted_answer',
            label: 'Alice Finance answer',
            field_key: 'cac_pain_points',
            source_id: 'def-1',
            excerpt: 'Finance needs a board baseline.',
        },
    ],
    citation_ids: [
        `submitted_answer:def-1:${fieldKey}`,
        `field_guidance:${fieldKey}:guidance`,
        'submitted_answer:def-1:cac_pain_points',
    ],
    ...overrides,
});

const packText = (text: string, sourceRefs: SourceRef[] = []) => ({
    text,
    provenance: 'deterministic' as const,
    source_refs: sourceRefs,
    citation_ids: sourceRefs.map((ref) => ref.id),
});

const packAnswerRef = (defId: string, fieldKey: string): SourceRef => ({
    id: `submitted_answer:${defId}:${fieldKey}`,
    type: 'submitted_answer',
    label: 'Submitted answer',
    field_key: fieldKey,
    source_id: defId,
    excerpt: 'value',
});

// Dense pack: two contested decisions where overhead handling carries a
// companion answer, a matrix link, a watch-out, and mixed source refs (a
// submitted answer that must be dropped from the disclosure plus a detector
// signal that must stay).
const readinessPackFixture = (
    overrides: Partial<FacilitatorReadinessPack> = {},
): FacilitatorReadinessPack => ({
    schema_version: '0.1',
    metric_id: 'metric-789',
    metric_name: 'Gross Churn Rate',
    generated_at: null,
    provenance: 'deterministic',
    readiness: {
        state: 'in_progress',
        decision_count: 2,
        blocker_count: 0,
        planned_minutes: 25,
        next_action: 'Finish preparing the run of show before the workshop.',
        blocker_summary: [],
    },
    workshop_thesis: null,
    decisions: [
        {
            id: 'readiness_decision:overhead_handling',
            field_key: 'overhead_handling',
            field_label: 'Overhead Handling',
            run_of_show_item_id: 'segment:overhead',
            attention_tier: 'must_resolve',
            decision_question: packText(
                'How should corporate overhead be allocated?',
            ),
            why_this_matters: packText(
                'Finance uses head-count while Sales uses revenue ratio.',
            ),
            source_positions: [
                {
                    stakeholder_name: 'Alice Finance',
                    stakeholder_affiliation: 'Finance',
                    value: 'Head Count Ratio',
                    source_refs: [packAnswerRef('def-1', 'overhead_handling')],
                    citation_ids: ['submitted_answer:def-1:overhead_handling'],
                },
                {
                    stakeholder_name: 'Bob Sales',
                    stakeholder_affiliation: 'Sales',
                    value: 'Revenue Ratio',
                    source_refs: [packAnswerRef('def-2', 'overhead_handling')],
                    citation_ids: ['submitted_answer:def-2:overhead_handling'],
                },
            ],
            companion_groups: [
                {
                    field_key: 'overhead_handling_notes',
                    field_label: 'Allocation and apportionment rules',
                    answers: [
                        {
                            stakeholder_name: 'Alice Finance',
                            stakeholder_affiliation: 'Finance',
                            value: 'We allocate shared RevOps overhead by headcount.',
                            source_refs: [],
                            citation_ids: [],
                        },
                    ],
                    source_refs: [],
                    citation_ids: [],
                },
            ],
            matrix_context: [
                packText('Cost Pool', [
                    {
                        id: 'field_schema:cost_pool',
                        type: 'field_schema',
                        label: 'Cost Pool',
                        field_key: 'cost_pool',
                        source_id: null,
                        excerpt: 'Cost Pool',
                    },
                ]),
            ],
            start_with: packText(
                'Name the official view and the audience it serves.',
            ),
            fallback_question: packText(
                'What allocation basis can both teams reproduce?',
            ),
            watch_outs: [
                {
                    id: 'wo:overhead:1',
                    decision_id: 'readiness_decision:overhead_handling',
                    severity: 'high',
                    label: 'Do not present one stakeholder source position as the recommendation.',
                    mitigation: 'Name the official choice through the workshop.',
                    source_refs: [],
                    citation_ids: [],
                },
            ],
            capture_requirements: [
                {
                    id: 'cap:overhead:wording',
                    decision_id: 'readiness_decision:overhead_handling',
                    label: 'Official wording captured',
                    reason: 'Record the agreed definition wording.',
                    required: true,
                    source_refs: [],
                    citation_ids: [],
                },
                {
                    id: 'cap:overhead:operating_view',
                    decision_id: 'readiness_decision:overhead_handling',
                    label: 'Surviving operating view labeled',
                    reason: 'Label any non-official view still reported.',
                    required: true,
                    source_refs: [],
                    citation_ids: [],
                },
            ],
            related_decision_ids: ['readiness_decision:cost_pool'],
            source_refs: [
                packAnswerRef('def-1', 'overhead_handling'),
                {
                    id: 'alignment_signal:disc-1:overhead_handling',
                    type: 'alignment_signal',
                    label: 'Detector signal',
                    field_key: 'overhead_handling',
                    source_id: 'disc-1',
                    excerpt: 'Finance and Sales diverge.',
                },
            ],
            citation_ids: [
                'submitted_answer:def-1:overhead_handling',
                'alignment_signal:disc-1:overhead_handling',
            ],
        },
        {
            id: 'readiness_decision:cost_pool',
            field_key: 'cost_pool',
            field_label: 'Cost Pool',
            run_of_show_item_id: 'segment:cost_pool',
            attention_tier: 'must_resolve',
            decision_question: packText('Which costs belong in the cost pool?'),
            why_this_matters: null,
            source_positions: [
                {
                    stakeholder_name: 'Alice Finance',
                    stakeholder_affiliation: 'Finance',
                    value: 'Fully loaded',
                    source_refs: [packAnswerRef('def-1', 'cost_pool')],
                    citation_ids: ['submitted_answer:def-1:cost_pool'],
                },
            ],
            companion_groups: [],
            matrix_context: [],
            start_with: packText('Name the official cost pool scope.'),
            fallback_question: null,
            watch_outs: [],
            capture_requirements: [
                {
                    id: 'cap:cost_pool:wording',
                    decision_id: 'readiness_decision:cost_pool',
                    label: 'Official wording captured',
                    reason: 'Record the agreed scope.',
                    required: true,
                    source_refs: [],
                    citation_ids: [],
                },
            ],
            related_decision_ids: ['readiness_decision:overhead_handling'],
            source_refs: [],
            citation_ids: [],
        },
    ],
    opening_plan: {
        first_decision_id: 'readiness_decision:overhead_handling',
        opening_question: packText(
            'Name the official view and the audience it serves.',
        ),
        fallback_question: null,
        setup_note: null,
        timebox_note: '15 min planned for this decision.',
    },
    capture_checklist: [
        {
            id: 'cap:overhead:wording',
            decision_id: 'readiness_decision:overhead_handling',
            label: 'Official wording captured',
            reason: 'Record the agreed definition wording.',
            required: true,
            source_refs: [],
            citation_ids: [],
        },
    ],
    facilitation_risks: [
        {
            id: 'wo:overhead:1',
            decision_id: 'readiness_decision:overhead_handling',
            severity: 'high',
            label: 'Do not present one stakeholder source position as the recommendation.',
            mitigation: 'Name the official choice through the workshop.',
            source_refs: [],
            citation_ids: [],
        },
        {
            id: 'risk:workshop:unresolved_evidence',
            decision_id: null,
            severity: 'medium',
            label: 'Do not present a field with missing evidence as a decided baseline.',
            mitigation: 'Keep thin fields marked unresolved.',
            source_refs: [],
            citation_ids: [],
        },
    ],
    source_refs: [],
    citation_ids: [],
    ...overrides,
});

// Thin pack: one concrete decision with no companion, matrix, related, watch-out,
// fallback, or workshop-level extras.
const thinReadinessPackFixture = (): FacilitatorReadinessPack => ({
    schema_version: '0.1',
    metric_id: 'focus-1',
    metric_name: 'Focus Time',
    generated_at: null,
    provenance: 'deterministic',
    readiness: {
        state: 'needs_review',
        decision_count: 1,
        blocker_count: 0,
        planned_minutes: 0,
        next_action: 'Review the prepared run of show before the workshop.',
        blocker_summary: [],
    },
    workshop_thesis: null,
    decisions: [
        {
            id: 'readiness_decision:focus_hours_target',
            field_key: 'focus_hours_target',
            field_label: 'Ideal focus hours per workday',
            run_of_show_item_id: null,
            attention_tier: 'must_resolve',
            decision_question: packText(
                'How many focus hours are realistic per day?',
            ),
            why_this_matters: packText('The team disagrees on realistic hours.'),
            source_positions: [
                {
                    stakeholder_name: 'Avery Lee',
                    stakeholder_affiliation: 'Engineering',
                    value: '4',
                    source_refs: [
                        packAnswerRef('usr-1', 'focus_hours_target'),
                    ],
                    citation_ids: ['submitted_answer:usr-1:focus_hours_target'],
                },
            ],
            companion_groups: [],
            matrix_context: [],
            start_with: packText('How many focus hours are realistic per day?'),
            fallback_question: null,
            watch_outs: [],
            capture_requirements: [
                {
                    id: 'cap:focus:wording',
                    decision_id: 'readiness_decision:focus_hours_target',
                    label: 'Official wording captured',
                    reason: 'Record the agreed target.',
                    required: true,
                    source_refs: [],
                    citation_ids: [],
                },
            ],
            related_decision_ids: [],
            source_refs: [],
            citation_ids: [],
        },
    ],
    opening_plan: {
        first_decision_id: 'readiness_decision:focus_hours_target',
        opening_question: packText(
            'How many focus hours are realistic per day?',
        ),
        fallback_question: null,
        setup_note: null,
        timebox_note: null,
    },
    capture_checklist: [
        {
            id: 'cap:focus:wording',
            decision_id: 'readiness_decision:focus_hours_target',
            label: 'Official wording captured',
            reason: 'Record the agreed target.',
            required: true,
            source_refs: [],
            citation_ids: [],
        },
    ],
    facilitation_risks: [],
    source_refs: [],
    citation_ids: [],
});

const createPageProps = (overrides: Partial<PageProps> = {}): PageProps => ({
    organization: { id: 'org-123', name: 'Orion Collective' },
    engagement: { id: 'eng-456', title: 'Sprint Gross Churn', status: 'InWorkshop' },
    metric: {
        id: 'metric-789',
        name: 'Gross Churn Rate',
        status: 'InReview',
        key: 'gross-churn-rate',
        field_roles: [
            createFieldRole(),
            createFieldRole({
                key: 'missing_field',
                label: 'Missing Field',
                workshop_prompt: null,
                workshop_guidance: null,
                alignment_signal: null,
                values: [],
            }),
        ],
        unclassified: [],
    },
    fieldValueLabels: {
        overhead_handling: {
            head_count: 'Head Count Ratio',
            revenue_ratio: 'Revenue Ratio',
        },
    },
    metric_alignment_brief: mockBrief({
        cue_contexts: [cueContextFixture()],
    }),
    facilitation_brief: null,
    engagement_metrics: [
        { id: 'metric-789', name: 'Gross Churn Rate', status: 'InReview' },
        { id: 'metric-101', name: 'Net Revenue Retention', status: 'Draft' },
    ],
    ...overrides,
});

// Field context now lives inside the integrated run-of-show segment (T028).
// Opening it expands the field-bound segment's prep workspace and returns that
// body so tests can inspect the read-only field context rendered inside it.
const openIntegratedFieldPrep = async (
    wrapper: ReturnType<typeof mount>,
) => {
    const panel = wrapper.find('[data-test="facilitation-plan"]');
    await panel
        .find('[data-test="run-of-show-field-prep-toggle"]')
        .trigger('click');

    return panel.find('[data-test="run-of-show-field-prep"]');
};

describe('Metrics/WorkshopPrep Vue Page', () => {
    beforeEach(() => {
        window.history.replaceState(null, '', '/');
        Object.defineProperty(window.HTMLElement.prototype, 'scrollIntoView', {
            configurable: true,
            value: vi.fn(),
        });

        consoleWarnSpy = vi
            .spyOn(console, 'warn')
            .mockImplementation((...args: Parameters<typeof console.warn>) => {
                const message = String(args[0] ?? '');

                if (message.includes('Failed to resolve component')) {
                    return;
                }

                originalConsoleWarn(...args);
            });
    });

    afterEach(() => {
        const unresolvedComponentWarnings = consoleWarnSpy.mock.calls
            .map((call) => String(call[0] ?? ''))
            .filter((message) =>
                message.includes('Failed to resolve component'),
            );

        consoleWarnSpy.mockRestore();
        vi.unstubAllGlobals();
        window.history.replaceState(null, '', '/');

        expect(unresolvedComponentWarnings).toEqual([]);
    });

    it('renders the run-of-show prep surface without a parallel field evidence browser', () => {
        const props = createPageProps();
        const wrapper = mount(WorkshopPrepPage, { props });

        expect(wrapper.find('[data-test="facilitation-plan"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Gross Churn Rate');

        // The integrated run of show is the single prep surface: the old
        // standalone "Field Evidence Details" browser is gone.
        expect(wrapper.text()).not.toContain('Field Evidence Details');
        expect(wrapper.find('[data-test="field-drilldown"]').exists()).toBe(
            false,
        );
    });

    it('removes the parallel Decision Prep and Field Evidence surfaces', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        // No tier filter, no standalone evidence accordion, no preview card —
        // field context is shown once, inside the run-of-show segment.
        expect(wrapper.find('[data-test="field-drilldown"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="field-filter-group"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="field-evidence-list"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).not.toContain('Field Evidence Details');
        expect(wrapper.text()).not.toContain('Decision Prep');
    });

    it('keeps the streamlined header actions when the plan is ready', () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture({
                status: 'ready_for_workshop',
            }),
        });
        const wrapper = mount(WorkshopPrepPage, { props });

        expect(wrapper.text()).toContain('Metric overview');
        expect(wrapper.text()).not.toContain('Continue preparation');
        expect(wrapper.text()).not.toContain('Live workshop');
        expect(
            wrapper.find('[data-test="continue-preparation-action"]').exists(),
        ).toBe(false);
    });

    it('surfaces source context and discussion prep inside the integrated segment', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);

        expect(fieldPrep.exists()).toBe(true);
        expect(fieldPrep.text()).toContain('Source Context & Discussion Prep');
        expect(wrapper.text()).not.toContain('Source Summary');
        expect(wrapper.text()).not.toContain('Source-backed counts');
        expect(wrapper.text()).not.toContain('Metric Alignment Brief');
        expect(wrapper.text()).not.toContain('Brief Context & Discussion Prep');
    });

    it('renders the facilitator question once and keeps guidance as secondary notes', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);
        const promptText = 'How should corporate overhead be allocated?';

        expect(fieldPrep.text()).toContain('Supplemental prompts (1)');
        expect(fieldPrep.text().split(promptText).length - 1).toBe(1);
        expect(wrapper.text()).not.toContain('Key Question');
        expect(wrapper.text()).not.toContain('Reconciliation Guide');

        // Template workshop guidance stays available as read-only notes.
        expect(
            fieldPrep
                .find('[data-test="run-of-show-workshop-guidance"]')
                .exists(),
        ).toBe(true);
        expect(fieldPrep.text()).toContain(
            'Facilitator should drive consensus on whether to use head-count or revenue ratio.',
        );
    });

    it('renders contributor inputs with stringified labels', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);

        expect(fieldPrep.text()).toContain('Alice Finance');
        expect(fieldPrep.text()).toContain('Head Count Ratio');
        expect(fieldPrep.text()).toContain('Bob Sales');
        expect(fieldPrep.text()).toContain('Revenue Ratio');
    });

    describe('T039 - Companion Answers Under Primary Answers', () => {
        const companionPrep = () =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    material_conflicts: [],
                    missing_evidence: [],
                    recommended_questions: [],
                    stakeholder_positions: [
                        {
                            field_key: 'overhead_handling',
                            field_label: 'Overhead Handling',
                            stakeholder: {
                                id: 'usr-1',
                                name: 'Lia Price',
                                affiliation: 'Finance',
                            },
                            definition_id: 'def-1',
                            raw_value: 'Fully loaded acquisition cost base.',
                            normalized_value:
                                'Fully loaded acquisition cost base.',
                            display_value: 'Fully loaded acquisition cost base.',
                            submitted_at: '2026-06-01T10:00:00Z',
                            source_refs: [],
                        },
                        {
                            field_key: 'overhead_handling',
                            field_label: 'Overhead Handling',
                            stakeholder: { id: 'usr-2', name: 'Lila Martin' },
                            definition_id: 'def-2',
                            raw_value: 'Only direct campaign costs.',
                            normalized_value: 'Only direct campaign costs.',
                            display_value: 'Only direct campaign costs.',
                            submitted_at: '2026-06-01T11:00:00Z',
                            source_refs: [],
                        },
                    ],
                    companion_answers: [
                        {
                            primary_field_key: 'overhead_handling',
                            companion_field_key: 'overhead_handling_notes',
                            companion_field_label:
                                'Allocation and apportionment rules',
                            companion_field_role: 'context',
                            answers: [
                                {
                                    definition_id: 'def-1',
                                    stakeholder: {
                                        id: 'usr-1',
                                        name: 'Lia Price',
                                    },
                                    display_value:
                                        'We allocate shared RevOps overhead by headcount.',
                                    raw_value:
                                        'We allocate shared RevOps overhead by headcount.',
                                    normalized_value:
                                        'We allocate shared RevOps overhead by headcount.',
                                    submitted_at: '2026-06-01T10:00:00Z',
                                    source_refs: [],
                                },
                            ],
                        },
                    ],
                }),
            });

        it('nests each respondent companion answer under their primary answer', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: companionPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const positions = fieldPrep.findAll(
                '[data-test="respondent-position"]',
            );
            expect(positions).toHaveLength(2);

            // Respondent with a companion answer: primary first, companion nested.
            const lia = positions[0];
            expect(lia.find('[data-test="respondent-name"]').text()).toContain(
                'Lia Price',
            );
            expect(
                lia.find('[data-test="respondent-primary-answer"]').text(),
            ).toBe('Fully loaded acquisition cost base.');

            const companion = lia.find('[data-test="companion-answer"]');
            expect(companion.exists()).toBe(true);
            expect(
                companion.find('[data-test="companion-answer-label"]').text(),
            ).toBe('Allocation and apportionment rules');
            expect(
                companion.find('[data-test="companion-answer-value"]').text(),
            ).toBe('We allocate shared RevOps overhead by headcount.');

            // Respondent without a companion answer shows only their primary.
            expect(
                positions[1].find('[data-test="companion-answer"]').exists(),
            ).toBe(false);
            expect(
                positions[1]
                    .find('[data-test="respondent-primary-answer"]')
                    .text(),
            ).toBe('Only direct campaign costs.');
        });

        it('labels respondents with role/team context only when available', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: companionPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const names = fieldPrep
                .findAll('[data-test="respondent-name"]')
                .map((node) => node.text().replace(/\s+/g, ' ').trim());

            expect(names[0]).toBe('Lia Price (Finance)');
            // No affiliation on the second respondent: plain name, no parens.
            expect(names[1]).toBe('Lila Martin');
        });

        it('shows raw submitted answers verbatim without a generated marker', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: companionPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const positions = fieldPrep.find(
                '[data-test="field-respondent-positions"]',
            );

            expect(positions.text()).toContain('Fully loaded acquisition cost base.');
            expect(positions.text()).toContain('Only direct campaign costs.');
            expect(positions.text()).not.toContain('✨');
        });

        // The attention items carry submitted_answer source refs and the brief
        // also carries top-level stakeholder_positions for the same field. The
        // respondent grid is the canonical evidence, so the source group must not
        // restate the same answers.
        const conflictWithPositions = (): PageProps =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    recommended_questions: [],
                    missing_evidence: [],
                    stakeholder_positions: [
                        {
                            field_key: 'overhead_handling',
                            field_label: 'Overhead Handling',
                            stakeholder: { id: 'usr-1', name: 'Alice Finance' },
                            definition_id: 'def-1',
                            raw_value: 'head_count',
                            normalized_value: 'head_count',
                            display_value: 'Head Count Ratio',
                            submitted_at: '2026-05-29T10:00:00Z',
                            source_refs: [],
                        },
                    ],
                }),
            });

        it('drops the duplicate submitted-answer source group when the respondent grid is shown', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: conflictWithPositions(),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // The respondent grid is the single place the answer appears.
            expect(
                fieldPrep
                    .find('[data-test="field-respondent-positions"]')
                    .text(),
            ).toContain('Head Count Ratio');

            // The conflict no longer restates it as a Submitted Answers group.
            const conflict = fieldPrep.find(
                '[data-test="field-conflict-item"]',
            );
            expect(
                conflict.find('[data-test="group-submitted_answer"]').exists(),
            ).toBe(false);
        });

        it('keeps the submitted-answer source group when no respondent grid exists', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    metric_alignment_brief: mockBrief({
                        recommended_questions: [],
                        missing_evidence: [],
                        stakeholder_positions: [],
                    }),
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const conflict = fieldPrep.find(
                '[data-test="field-conflict-item"]',
            );

            expect(
                conflict.find('[data-test="group-submitted_answer"]').exists(),
            ).toBe(true);
        });
    });

    describe('T001/T002 - Clean Field Prep Evidence Surface', () => {
        // A conflict whose field also carries top-level respondent positions, so
        // the respondent-positions grid renders and proves the divergence on its
        // own. The machine-style conflict rationale becomes redundant noise.
        const conflictWithRespondentGrid = (): PageProps =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    recommended_questions: [],
                    missing_evidence: [],
                    stakeholder_positions: [
                        {
                            field_key: 'overhead_handling',
                            field_label: 'Overhead Handling',
                            stakeholder: { id: 'usr-1', name: 'Alice Finance' },
                            definition_id: 'def-1',
                            raw_value: 'head_count',
                            normalized_value: 'head_count',
                            display_value: 'Head Count Ratio',
                            submitted_at: '2026-05-29T10:00:00Z',
                            source_refs: [],
                        },
                        {
                            field_key: 'overhead_handling',
                            field_label: 'Overhead Handling',
                            stakeholder: { id: 'usr-2', name: 'Bob Sales' },
                            definition_id: 'def-2',
                            raw_value: 'revenue_ratio',
                            normalized_value: 'revenue_ratio',
                            display_value: 'Revenue Ratio',
                            submitted_at: '2026-05-29T10:30:00Z',
                            source_refs: [],
                        },
                    ],
                }),
            });

        it('hides the machine-style conflict rationale when respondent positions are shown', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: conflictWithRespondentGrid(),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // The respondent grid carries the concrete divergence.
            const positions = fieldPrep.find(
                '[data-test="field-respondent-positions"]',
            );
            expect(positions.text()).toContain('Head Count Ratio');
            expect(positions.text()).toContain('Revenue Ratio');

            // The conflict card stays (title + sources) but no longer restates
            // the machine-style rationale the grid already proves.
            const conflict = fieldPrep.find(
                '[data-test="field-conflict-item"]',
            );
            expect(conflict.exists()).toBe(true);
            expect(
                conflict.find('[data-test="attention-rationale"]').exists(),
            ).toBe(false);
            expect(fieldPrep.text()).not.toContain(
                'Stakeholders submitted different normalized values.',
            );
        });

        it('keeps the conflict rationale when no respondent grid is shown', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    metric_alignment_brief: mockBrief({
                        recommended_questions: [],
                        missing_evidence: [],
                        stakeholder_positions: [],
                    }),
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const conflict = fieldPrep.find(
                '[data-test="field-conflict-item"]',
            );

            expect(
                conflict.find('[data-test="attention-rationale"]').text(),
            ).toBe('Stakeholders submitted different normalized values.');
        });

        it('keeps missing-evidence rationale even when one respondent has answered', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    metric_alignment_brief: mockBrief({
                        recommended_questions: [],
                        material_conflicts: [],
                        // One respondent answered, so the field still needs more
                        // evidence to compare — the rationale explains why it is
                        // on the agenda and must survive the conflict cleanup.
                        stakeholder_positions: [
                            {
                                field_key: 'overhead_handling',
                                field_label: 'Overhead Handling',
                                stakeholder: {
                                    id: 'usr-1',
                                    name: 'Alice Finance',
                                },
                                definition_id: 'def-1',
                                raw_value: 'head_count',
                                normalized_value: 'head_count',
                                display_value: 'Head Count Ratio',
                                submitted_at: '2026-05-29T10:00:00Z',
                                source_refs: [],
                            },
                        ],
                        missing_evidence: [
                            {
                                id: 'field:overhead_handling:missing_evidence',
                                category: 'missing_evidence',
                                field_key: 'overhead_handling',
                                field_label: 'Overhead Handling',
                                field_role: 'definition',
                                severity: 'low',
                                title: 'Missing stakeholder answers for Overhead Handling.',
                                rationale:
                                    'This required or guided field has fewer than two submitted answers to compare.',
                                stakeholder_positions: [],
                                source_refs: [],
                            },
                        ],
                    }),
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const missing = fieldPrep.find('[data-test="field-missing-item"]');

            expect(missing.exists()).toBe(true);
            expect(
                missing.find('[data-test="attention-rationale"]').text(),
            ).toBe(
                'This required or guided field has fewer than two submitted answers to compare.',
            );
        });

        it('labels companion answers without the repeated "Companion answer ·" prefix', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    metric_alignment_brief: mockBrief({
                        material_conflicts: [],
                        missing_evidence: [],
                        recommended_questions: [],
                        stakeholder_positions: [
                            {
                                field_key: 'overhead_handling',
                                field_label: 'Overhead Handling',
                                stakeholder: {
                                    id: 'usr-1',
                                    name: 'Lia Price',
                                },
                                definition_id: 'def-1',
                                raw_value: 'Fully loaded acquisition cost base.',
                                normalized_value:
                                    'Fully loaded acquisition cost base.',
                                display_value:
                                    'Fully loaded acquisition cost base.',
                                submitted_at: '2026-06-01T10:00:00Z',
                                source_refs: [],
                            },
                        ],
                        companion_answers: [
                            {
                                primary_field_key: 'overhead_handling',
                                companion_field_key: 'overhead_handling_notes',
                                companion_field_label:
                                    'Allocation and apportionment rules',
                                companion_field_role: 'context',
                                answers: [
                                    {
                                        definition_id: 'def-1',
                                        stakeholder: {
                                            id: 'usr-1',
                                            name: 'Lia Price',
                                        },
                                        display_value:
                                            'We allocate shared RevOps overhead by headcount.',
                                        raw_value:
                                            'We allocate shared RevOps overhead by headcount.',
                                        normalized_value:
                                            'We allocate shared RevOps overhead by headcount.',
                                        submitted_at: '2026-06-01T10:00:00Z',
                                        source_refs: [],
                                    },
                                ],
                            },
                        ],
                    }),
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const label = fieldPrep.find(
                '[data-test="companion-answer-label"]',
            );

            expect(label.exists()).toBe(true);
            expect(label.text()).toBe('Allocation and apportionment rules');
            expect(fieldPrep.text()).not.toContain('Companion answer ·');
        });
    });

    describe('T009/T010 - Facilitator Readiness Pack Panel', () => {
        it('renders a compact workshop-level script above the run of show', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    facilitator_readiness_pack: readinessPackFixture(),
                }),
            });

            const pack = wrapper.find(
                '[data-test="facilitator-readiness-pack"]',
            );
            expect(pack.exists()).toBe(true);
            // Clearly private + generated preparation support.
            expect(
                pack.find('[data-test="readiness-pack-private"]').exists(),
            ).toBe(true);
            expect(pack.find('[data-test="generated-marker"]').exists()).toBe(
                true,
            );

            // Readiness overview + opening plan.
            expect(
                pack.find('[data-test="readiness-pack-state"]').text(),
            ).toBe('In progress');
            expect(
                pack.find('[data-test="readiness-pack-next-action"]').text(),
            ).toContain('Finish preparing');
            expect(
                pack
                    .find('[data-test="readiness-pack-opening-question"]')
                    .text(),
            ).toContain('Name the official view');

            // Compact ordered decision index linking into the run of show.
            const items = pack.findAll(
                '[data-test="readiness-decision-index-item"]',
            );
            expect(items).toHaveLength(2);
            expect(items[0].attributes('data-test-id')).toBe(
                'readiness_decision:overhead_handling',
            );
            expect(items[1].attributes('data-test-id')).toBe(
                'readiness_decision:cost_pool',
            );
            expect(
                items[0]
                    .find('[data-test="readiness-decision-index-label"]')
                    .text(),
            ).toBe('Overhead Handling');
            expect(
                items[0]
                    .find('[data-test="readiness-decision-index-tier"]')
                    .text(),
            ).toBe('Must resolve');
            expect(
                items[0]
                    .find('[data-test="readiness-decision-index-question"]')
                    .text(),
            ).toContain('How should corporate overhead');

            // Workshop-level closing reminders carry only non-decision-bound
            // items. A readiness gap is surfaced once as an entry blocker (FX04),
            // not echoed here, so the only reminder is the unresolved-evidence risk.
            const notes = pack.find('[data-test="readiness-workshop-notes"]');
            expect(notes.exists()).toBe(true);
            expect(notes.text()).toContain(
                'missing evidence as a decided baseline',
            );

            // Rendered above the run of show, which stays the editable surface.
            const html = wrapper.html();
            expect(html.indexOf('facilitator-readiness-pack')).toBeLessThan(
                html.indexOf('data-test="facilitation-plan"'),
            );
        });

        it('does not re-render per-decision evidence or field assist at the top level', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    facilitator_readiness_pack: readinessPackFixture(),
                }),
            });
            const pack = wrapper.find(
                '[data-test="facilitator-readiness-pack"]',
            );

            // The duplicated per-decision card surface is gone: no source
            // positions, companion groups, per-decision prompts, or source
            // toggles above the run of show.
            expect(
                pack.find('[data-test="readiness-decision"]').exists(),
            ).toBe(false);
            expect(
                pack.find('[data-test="readiness-source-position"]').exists(),
            ).toBe(false);
            expect(
                pack.find('[data-test="readiness-companion-group"]').exists(),
            ).toBe(false);
            expect(
                pack.find('[data-test="readiness-decision-start"]').exists(),
            ).toBe(false);
            expect(
                pack
                    .find('[data-test="readiness-decision-fallback"]')
                    .exists(),
            ).toBe(false);
            expect(
                pack
                    .find('[data-test="readiness-decision-sources-toggle"]')
                    .exists(),
            ).toBe(false);
        });

        it('enriches the matching run-of-show segment with why, capture, and related hints', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    facilitator_readiness_pack: readinessPackFixture(),
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const support = fieldPrep.find(
                '[data-test="readiness-segment-support"]',
            );
            expect(support.exists()).toBe(true);
            expect(
                support.find('[data-test="readiness-segment-why"]').text(),
            ).toContain('Finance uses head-count');

            const captureItems = support.findAll(
                '[data-test="readiness-segment-capture-item"]',
            );
            expect(captureItems.length).toBeGreaterThanOrEqual(2);
            expect(support.text()).toContain('Official wording captured');
            expect(support.text()).toContain('Surviving operating view labeled');

            // Related decision resolves to a link into the cost pool segment.
            const related = support.find(
                '[data-test="readiness-segment-related-link"]',
            );
            expect(related.text()).toBe('Cost Pool');
            expect(related.attributes('href')).toBe('#field-detail-cost_pool');
        });

        it('keeps the Facilitation assist as the only source of field-level prompts', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    facilitator_readiness_pack: readinessPackFixture(),
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // The field-level Start with lives only in the Facilitation assist...
            const assist = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );
            expect(assist.exists()).toBe(true);
            expect(
                assist.find('[data-test="field-assist-start"]').exists(),
            ).toBe(true);

            // ...and the readiness segment support never re-renders those prompts.
            const support = fieldPrep.find(
                '[data-test="readiness-segment-support"]',
            );
            expect(
                support.find('[data-test="field-assist-start"]').exists(),
            ).toBe(false);
            expect(
                support.find('[data-test="readiness-source-position"]').exists(),
            ).toBe(false);
        });

        it('links each decision-index row to its run-of-show segment anchor', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                    facilitator_readiness_pack: readinessPackFixture(),
                }),
            });

            // The index targets the existing #field-detail-{key} anchors, so the
            // current hash navigation (covered separately) opens, scrolls to, and
            // pulses the matching segment without a second source browser.
            const links = wrapper.findAll(
                '[data-test="readiness-decision-link"]',
            );
            expect(links).toHaveLength(2);
            expect(links[0].attributes('href')).toBe(
                '#field-detail-overhead_handling',
            );
            expect(links[1].attributes('href')).toBe('#field-detail-cost_pool');
        });

        it('renders a sparse top-level script for a thin metric without empty shells', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitator_readiness_pack: thinReadinessPackFixture(),
                }),
            });

            const pack = wrapper.find(
                '[data-test="facilitator-readiness-pack"]',
            );
            // One compact decision in the index, no padded sections.
            expect(
                pack.findAll('[data-test="readiness-decision-index-item"]'),
            ).toHaveLength(1);
            expect(
                pack.find('[data-test="readiness-workshop-notes"]').exists(),
            ).toBe(false);
            expect(
                pack.find('[data-test="readiness-pack-thesis"]').exists(),
            ).toBe(false);
            expect(
                pack.find('[data-test="readiness-pack-blockers"]').exists(),
            ).toBe(false);
        });

        it('renders the workshop thesis only when present', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitator_readiness_pack: readinessPackFixture({
                        workshop_thesis: packText(
                            'The team must lock the official overhead baseline.',
                        ),
                    }),
                }),
            });

            expect(
                wrapper
                    .find('[data-test="readiness-pack-thesis"]')
                    .text(),
            ).toContain('lock the official overhead baseline');
        });

        it('does not render the pack when none is provided or there is nothing to prepare', async () => {
            const noPack = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });
            expect(
                noPack.find('[data-test="facilitator-readiness-pack"]').exists(),
            ).toBe(false);

            const emptyPack = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitator_readiness_pack: readinessPackFixture({
                        decisions: [],
                    }),
                }),
            });
            expect(
                emptyPack
                    .find('[data-test="facilitator-readiness-pack"]')
                    .exists(),
            ).toBe(false);
        });
    });

    describe('T040 - Workshop Cues In The Facilitation Column', () => {
        const cuePrep = (
            cueOverrides: Partial<WorkshopCue> = {},
        ): PageProps =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    cue_contexts: [cueContextFixture()],
                    workshop_cues: [
                        workshopCueFixture('overhead_handling', cueOverrides),
                    ],
                }),
            });

        const decisionCueText =
            'Name the official view and the audience it serves before debating allocation mechanics.';

        it('renders the compact field assist in the right facilitation column', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );
            expect(cues.exists()).toBe(true);
            expect(cues.text()).toContain('Facilitation assist');
            // The start move comes from the decision cue...
            expect(
                cues.find('[data-test="field-assist-start"]').text(),
            ).toContain(decisionCueText);
            // ...and the heavy why/probe/risk/decision-frame wall is gone.
            expect(cues.text()).not.toContain('Why this cue');
            expect(cues.text()).not.toContain('Decision Frame');
            expect(
                cues.find('[data-test="workshop-cue-section"]').exists(),
            ).toBe(false);
        });

        it('pairs the cue panel amber palette with dark-mode variants', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // The amber accents must carry dark counterparts so the cue panel does
            // not render as a bright box in dark mode (T023 standard).
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );
            expect(cues.classes()).toContain('dark:bg-amber-950/5');
            expect(cues.classes()).toContain('dark:border-amber-900/40');

            expect(
                fieldPrep
                    .find('[data-test="workshop-cue-heading"]')
                    .classes(),
            ).toContain('dark:text-amber-200');
            expect(
                fieldPrep
                    .find('[data-test="workshop-cue-provenance"]')
                    .classes(),
            ).toContain('dark:text-amber-300');
        });

        it('removes workshop cues from the left source evidence groups', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // The left "Source Context & Discussion Prep" column no longer holds
            // an alignment_signal group; the cue moved to the facilitation column.
            const leftColumn = fieldPrep.find(
                '[data-test="field-brief-context"]',
            );
            expect(leftColumn.exists()).toBe(true);
            expect(
                leftColumn.findAll('[data-test="group-alignment_signal"]'),
            ).toHaveLength(0);
            expect(
                leftColumn.find('[data-test="field-conflict-item"]').text(),
            ).not.toContain(decisionCueText);
        });

        it('keeps Workshop guidance separate from Workshop Cues', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const guidance = fieldPrep.find(
                '[data-test="run-of-show-workshop-guidance"]',
            );
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            expect(guidance.exists()).toBe(true);
            expect(cues.exists()).toBe(true);
            // Static template guidance stays its own block, distinct from the cue.
            expect(guidance.text()).toContain('Workshop guidance');
            expect(guidance.text()).not.toContain(decisionCueText);
            expect(cues.text()).not.toContain('head-count or revenue ratio');
        });

        it('T061: shows workshop guidance once per expanded field-prep surface, not inside cue context disclosure', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // 1. Standalone blue panel shows it
            const guidance = fieldPrep.find('[data-test="run-of-show-workshop-guidance"]');
            expect(guidance.exists()).toBe(true);
            expect(guidance.text()).toContain('Facilitator should drive consensus on whether to use head-count or revenue ratio.');

            // 2. Workshop Cues does not repeat it inside cue content or context disclosure
            const cues = fieldPrep.find('[data-test="run-of-show-workshop-cues"]');
            expect(cues.text()).not.toContain('Facilitator should drive consensus on whether to use head-count or revenue ratio.');

            const toggle = cues.find('[data-test="workshop-cue-context-toggle"]');
            await toggle.trigger('click');

            const context = cues.find('[data-test="workshop-cue-context"]');
            expect(context.text()).not.toContain('Workshop guidance');
            expect(context.text()).not.toContain('Drive consensus on the allocation basis.');
        });

        it('labels the panel as Facilitation assist and the detector-signal source references as Alignment Signals / Detector signal', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep({
                source_refs: [
                    {
                        id: 'alignment_signal:sig-123:costs_included',
                        type: 'alignment_signal',
                        label: 'Detector signal',
                        field_key: 'costs_included',
                        source_id: 'sig-123',
                        excerpt: 'Finance and Marketing submitted different positions.',
                    }
                ]
            }) });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );
            expect(cues.find('[data-test="workshop-cue-heading"]').text()).toBe('Facilitation assist');

            // Expand the context toggle
            const toggle = cues.find('[data-test="workshop-cue-context-toggle"]');
            expect(toggle.exists()).toBe(true);
            await toggle.trigger('click');

            // Verify the source reference group is labeled Alignment Signals and not Workshop Cues
            const group = cues.find('[data-test="group-alignment_signal"]');
            expect(group.exists()).toBe(true);
            expect(group.find('[data-test="group-label"]').text()).toBe('Alignment Signals');
            expect(group.find('[data-test="source-label"]').text()).toBe('Detector signal');
        });

        it('does not mark the plan dirty when cue context is expanded', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            const toggle = cues.find(
                '[data-test="workshop-cue-context-toggle"]',
            );
            expect(toggle.exists()).toBe(true);
            await toggle.trigger('click');
            expect(toggle.attributes('aria-expanded')).toBe('true');

            expect(
                wrapper.find('[data-test="run-of-show-save-status"]').text(),
            ).not.toContain('Unsaved changes');
        });
    });

    describe('T043 - Define Cue Output Shape And Review UX', () => {
        const cuePrep = (
            cueOverrides: Partial<WorkshopCue> = {},
        ): PageProps =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    cue_contexts: [cueContextFixture()],
                    workshop_cues: [
                        workshopCueFixture('overhead_handling', cueOverrides),
                    ],
                }),
            });

        it('maps the dense cue to a start move, a fallback, and a watch-out', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            // Start move (decision cue) + fallback (probe cue) + watch-out (risk cue).
            expect(
                cues.find('[data-test="field-assist-start"]').text(),
            ).toContain('Name the official view and the audience it serves');
            expect(
                cues.find('[data-test="field-assist-fallback"]').text(),
            ).toContain(
                'what allocation basis can Finance publish and Marketing reproduce?',
            );
            const watchOuts = cues.findAll(
                '[data-test="field-assist-watch-out"]',
            );
            expect(watchOuts).toHaveLength(1);
            expect(watchOuts[0].text()).toContain(
                'both teams can look aligned while still publishing different CAC/CPL values',
            );

            // The heavy section stack, decision frame, and trade-off/wording
            // framing no longer render — those move to the readiness pack.
            expect(
                cues.find('[data-test="workshop-cue-section"]').exists(),
            ).toBe(false);
            expect(
                cues.find('[data-test="workshop-cue-decision-frame"]').exists(),
            ).toBe(false);
            expect(cues.text()).not.toContain(
                'Campaign-only CAC is more channel-actionable',
            );
            expect(cues.text()).not.toContain(
                'Official CAC/CPL includes allocated overhead',
            );
        });

        it('omits the watch-out cleanly when the risk cue is absent', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: cuePrep({
                    risk_cue: null,
                    tradeoff_cue: null,
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            // Start move and fallback stay (decision + probe cues present)...
            expect(
                cues.find('[data-test="field-assist-start"]').exists(),
            ).toBe(true);
            expect(
                cues.find('[data-test="field-assist-fallback"]').exists(),
            ).toBe(true);
            // ...but with no risk cue the watch-out is dropped, not left empty.
            expect(
                cues.find('[data-test="field-assist-watch-outs"]').exists(),
            ).toBe(false);
            expect(cues.text()).not.toContain('Watch out:');
        });

        it('omits the fallback cleanly when the probe cue is absent', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: cuePrep({
                    probe_cue: null,
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            // Start move and watch-out keep the panel substantive...
            expect(
                cues.find('[data-test="field-assist-start"]').exists(),
            ).toBe(true);
            expect(
                cues.find('[data-test="field-assist-watch-out"]').exists(),
            ).toBe(true);
            // ...while the fallback slot is omitted, not rendered as a label.
            expect(
                cues.find('[data-test="field-assist-fallback"]').exists(),
            ).toBe(false);
            expect(cues.text()).not.toContain('Fallback:');
        });

        it('makes cue source context reachable from the panel', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            const toggle = cues.find(
                '[data-test="workshop-cue-context-toggle"]',
            );
            await toggle.trigger('click');

            const context = cues.find('[data-test="workshop-cue-context"]');
            expect(context.exists()).toBe(true);
            expect(context.text()).not.toContain('Workshop guidance');
            expect(context.text()).toContain('CAC/CPL divergence points');
            // Submitted answers are already shown in the segment's left column;
            // the cue disclosure no longer re-lists them (T058/F2).
            expect(context.text()).not.toContain('Submitted Answers');
        });

        it('keeps facilitator notes editing separate from cue review state', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: cuePrep() });
            const child = wrapper.findComponent(FacilitationPlanPanel);
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            expect(child.vm.isDirty).toBe(false);

            await cues
                .find('[data-test="workshop-cue-context-toggle"]')
                .trigger('click');
            expect(child.vm.isDirty).toBe(false);

            expect(cues.find('textarea').exists()).toBe(false);

            await fieldPrep
                .find('[data-test="run-of-show-purpose"]')
                .setValue('Updated facilitator note');
            expect(child.vm.isDirty).toBe(true);
        });
    });

    describe('T041 - Generated And Inferred Markers', () => {
        // Default brief: a deterministic conflict summary on overhead_handling
        // plus a fixture workshop cue, so both marked surfaces render for the
        // field-bound segment.
        const markerPrep = () =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    cue_contexts: [cueContextFixture()],
                    workshop_cues: [workshopCueFixture()],
                }),
            });

        // Raw-evidence-only brief: a respondent position with a companion answer
        // and no attention items or cues, so no generated marker should appear.
        const rawEvidencePrep = () =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    material_conflicts: [],
                    missing_evidence: [],
                    recommended_questions: [],
                    stakeholder_positions: [
                        {
                            field_key: 'overhead_handling',
                            field_label: 'Overhead Handling',
                            stakeholder: {
                                id: 'usr-1',
                                name: 'Lia Price',
                                affiliation: 'Finance',
                            },
                            definition_id: 'def-1',
                            raw_value: 'Fully loaded acquisition cost base.',
                            normalized_value:
                                'Fully loaded acquisition cost base.',
                            display_value: 'Fully loaded acquisition cost base.',
                            submitted_at: '2026-06-01T10:00:00Z',
                            source_refs: [],
                        },
                    ],
                    companion_answers: [
                        {
                            primary_field_key: 'overhead_handling',
                            companion_field_key: 'overhead_handling_notes',
                            companion_field_label:
                                'Allocation and apportionment rules',
                            companion_field_role: 'context',
                            answers: [
                                {
                                    definition_id: 'def-1',
                                    stakeholder: {
                                        id: 'usr-1',
                                        name: 'Lia Price',
                                    },
                                    display_value:
                                        'We allocate shared RevOps overhead by headcount.',
                                    raw_value:
                                        'We allocate shared RevOps overhead by headcount.',
                                    normalized_value:
                                        'We allocate shared RevOps overhead by headcount.',
                                    submitted_at: '2026-06-01T10:00:00Z',
                                    source_refs: [],
                                },
                            ],
                        },
                    ],
                }),
            });

        it('does not mark the deterministic conflict summary as generated', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: markerPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const conflict = fieldPrep.find(
                '[data-test="field-conflict-item"]',
            );

            // The conflict card is a deterministic restatement of submitted
            // evidence, so it carries no generated marker — that label is
            // reserved for the synthesized Workshop Cues (T045).
            expect(
                conflict.find('[data-test="generated-marker"]').exists(),
            ).toBe(false);
            expect(conflict.text()).not.toContain('✨');
            // The summary itself still renders.
            expect(
                conflict.find('[data-test="conflict-title"]').text(),
            ).toBe('Stakeholders disagree on Overhead Handling.');
        });

        it('marks Workshop Cues as generated content', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: markerPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );
            const marker = cues.find('[data-test="generated-marker"]');

            expect(marker.exists()).toBe(true);
            expect(marker.text()).toContain('Generated');
            expect(
                cues.find('[data-test="workshop-cue-provenance"]').text(),
            ).toContain('Deterministic draft');
        });

        it('does not mark raw submitted answers or companion answers as generated', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: rawEvidencePrep(),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const positions = fieldPrep.find(
                '[data-test="field-respondent-positions"]',
            );
            expect(positions.exists()).toBe(true);
            expect(
                positions.find('[data-test="generated-marker"]').exists(),
            ).toBe(false);
            expect(positions.text()).not.toContain('✨');

            // Raw evidence only — no deterministic summary or cue, so the whole
            // prep surface stays marker-free.
            expect(
                fieldPrep.findAll('[data-test="generated-marker"]'),
            ).toHaveLength(0);
        });

        it('keeps static Workshop guidance free of the generated marker', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: markerPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const guidance = fieldPrep.find(
                '[data-test="run-of-show-workshop-guidance"]',
            );
            expect(guidance.exists()).toBe(true);
            expect(
                guidance.find('[data-test="generated-marker"]').exists(),
            ).toBe(false);
            expect(guidance.text()).not.toContain('✨');
        });

        it('exposes the generated marker as accessible text, not icon only', async () => {
            const wrapper = mount(WorkshopPrepPage, { props: markerPrep() });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const marker = fieldPrep.find('[data-test="generated-marker"]');
            expect(marker.exists()).toBe(true);
            // The label renders as real text (screen-reader available), and the
            // marker carries an explanatory title — never icon-only.
            expect(marker.text()).toContain('Generated');
            expect(marker.attributes('title')).toBeTruthy();
        });
    });

    describe('T042 - Matrix-Wide Cue Context', () => {
        it('makes cue context available on the expanded field prep without dirtying the plan', async () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    cue_contexts: [cueContextFixture()],
                }),
            });
            const wrapper = mount(WorkshopPrepPage, { props });
            const child = wrapper.findComponent(FacilitationPlanPanel);

            expect(child.vm.isDirty).toBe(false);

            await openIntegratedFieldPrep(wrapper);

            const segmentContextById =
                (child.vm as any).segmentContextById?.value ??
                (child.vm as any).segmentContextById;
            const segmentContext =
                segmentContextById['segment:official_definition'];
            expect(segmentContext.fields[0].cueContext).toBeTruthy();
            expect(segmentContext.fields[0].cueContext.field_key).toBe(
                'overhead_handling',
            );
            expect(
                segmentContext.fields[0].cueContext.matrix_context[0].field_key,
            ).toBe('cac_pain_points');
            expect(
                segmentContext.fields[0].cueContext.stakeholder_positions[0]
                    .stakeholder.affiliation,
            ).toBe('Finance');
            expect(child.vm.isDirty).toBe(false);
            expect(
                wrapper.find('[data-test="run-of-show-save-status"]').text(),
            ).not.toContain('Unsaved changes');
        });
    });

    it('groups, deduplicates, and inspects field source references inside the segment', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
            metric_alignment_brief: mockBrief({
                recommended_questions: [],
                missing_evidence: [],
                material_conflicts: [
                    {
                        id: 'field:overhead_handling:material_conflict',
                        category: 'material_conflict',
                        field_key: 'overhead_handling',
                        field_label: 'Overhead Handling',
                        field_role: 'definition',
                        severity: 'medium',
                        title: 'Stakeholders disagree on Overhead Handling.',
                        rationale: 'Different normalized values were submitted.',
                        stakeholder_positions: [],
                        source_refs: [
                            {
                                id: 'sa-1',
                                type: 'submitted_answer',
                                label: 'Alice answer',
                                field_key: 'overhead_handling',
                                source_id: 'def-1',
                                excerpt: 'Head Count Ratio',
                            },
                            {
                                id: 'sa-1',
                                type: 'submitted_answer',
                                label: 'Duplicate Alice answer',
                                field_key: 'overhead_handling',
                                source_id: 'def-1',
                                excerpt: 'Duplicate ignored',
                            },
                            {
                                id: 'fg-1',
                                type: 'field_guidance',
                                label: 'Field guidance note',
                                field_key: 'overhead_handling',
                                source_id: null,
                                excerpt: false,
                            },
                            {
                                id: 'as-1',
                                type: 'alignment_signal',
                                label: 'Detector signal',
                                field_key: 'overhead_handling',
                                source_id: 'disc-1',
                                excerpt: 0,
                            },
                        ],
                    },
                ],
            }),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);
        const conflict = fieldPrep.find('[data-test="field-conflict-item"]');

        expect(conflict.find('[data-test="conflict-title"]').text()).toBe(
            'Stakeholders disagree on Overhead Handling.',
        );

        // Source refs group by type in fixed order: submitted answers then
        // field guidance. Workshop cues (alignment_signal) are no longer part of
        // the left source evidence groups — they render in the facilitation
        // column instead (T040).
        const groupLabels = conflict
            .findAll('[data-test="group-label"]')
            .map((label) => label.text());
        expect(groupLabels).toEqual(['Submitted Answers', 'Field Guidance']);
        expect(groupLabels).not.toContain('Workshop Cues');

        // The duplicate submitted-answer id collapses to a single item.
        expect(
            conflict
                .find(
                    '[data-test="group-submitted_answer"] [data-test="group-count"]',
                )
                .text(),
        ).toBe('1 item');

        // false is a valid excerpt, not a blank.
        const excerptText = conflict
            .findAll('[data-test="source-excerpt"]')
            .map((excerpt) => excerpt.text());
        expect(excerptText).toContain('false');

        // Each excerpt can be expanded for inspection.
        const toggle = conflict.find('[data-test="toggle-excerpt"]');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
    });

    it('renders facilitator questions with priority borders and 0/false source excerpts', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
            metric_alignment_brief: mockBrief({
                material_conflicts: [],
                missing_evidence: [],
                recommended_questions: [
                    {
                        id: 'q-high',
                        field_key: 'overhead_handling',
                        question: 'High priority question?',
                        reason: 'Most urgent.',
                        priority: 'high',
                        source_refs: [
                            {
                                id: 'q-zero',
                                type: 'submitted_answer',
                                label: 'Zero excerpt',
                                field_key: 'overhead_handling',
                                source_id: 'def-1',
                                excerpt: 0,
                            },
                            {
                                id: 'q-false',
                                type: 'submitted_answer',
                                label: 'False excerpt',
                                field_key: 'overhead_handling',
                                source_id: 'def-2',
                                excerpt: false,
                            },
                        ],
                    },
                    {
                        id: 'q-medium',
                        field_key: 'overhead_handling',
                        question: 'Medium priority question?',
                        reason: 'Secondary.',
                        priority: 'medium',
                        source_refs: [],
                    },
                    {
                        id: 'q-low',
                        field_key: 'overhead_handling',
                        question: 'Low priority question?',
                        reason: 'Optional.',
                        priority: 'low',
                        source_refs: [],
                    },
                ],
            }),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);

        expect(fieldPrep.text()).toContain('Supplemental prompts (3)');

        const questionItems = fieldPrep.findAll('[data-test="question-item"]');
        expect(questionItems).toHaveLength(3);
        expect(questionItems[0].classes()).toContain('border-l-destructive');
        expect(questionItems[1].classes()).toContain('border-l-amber-500');
        expect(questionItems[2].classes()).toContain(
            'border-l-muted-foreground/30',
        );

        // 0 and false render as real source titles, not dropped as empty.
        const sourceTitles = questionItems[0]
            .findAll('[data-test="question-source-ref"]')
            .map((ref) => ref.attributes('title'));
        expect(sourceTitles).toContain('Zero excerpt: 0');
        expect(sourceTitles).toContain('False excerpt: false');
    });

    it('falls back to the workshop prompt without repeating it as its own source badge', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
            metric_alignment_brief: mockBrief({
                material_conflicts: [],
                missing_evidence: [],
                recommended_questions: [],
            }),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);

        // With no brief question, the template workshop prompt becomes the single
        // facilitator question...
        expect(fieldPrep.text()).toContain('Supplemental prompts (1)');
        expect(fieldPrep.text()).toContain(
            'How should corporate overhead be allocated?',
        );

        // ...and it is not echoed back as its own source badge.
        expect(
            fieldPrep.find('[data-test="question-source-ref"]').exists(),
        ).toBe(false);
        expect(fieldPrep.text()).not.toContain('Sources');
    });

    it('hides promoted field guidance and pairs decision and missing context with dark-mode variants', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
            metric_alignment_brief: mockBrief({
                material_conflicts: [],
                recommended_questions: [],
                decision_needed: [
                    {
                        id: 'decision:overhead_handling',
                        category: 'decision_needed',
                        field_key: 'overhead_handling',
                        field_label: 'Overhead Handling',
                        field_role: 'definition',
                        severity: 'medium',
                        title: 'Confirm the overhead owner',
                        rationale: 'Ownership is unclear.',
                        stakeholder_positions: [],
                        source_refs: [
                            {
                                id: 'promoted-guidance',
                                type: 'field_guidance',
                                label: 'Workshop prompt / guidance',
                                field_key: 'overhead_handling',
                                source_id: null,
                                excerpt: 'Hidden promoted guidance excerpt',
                                meta: { guidance_kind: 'workshop_guidance' },
                            },
                            {
                                id: 'decision-answer',
                                type: 'submitted_answer',
                                label: 'Alice answer',
                                field_key: 'overhead_handling',
                                source_id: 'def-1',
                                excerpt: 'Head Count Ratio',
                            },
                        ],
                    },
                ],
                missing_evidence: [
                    {
                        id: 'missing:overhead_handling',
                        category: 'missing_evidence',
                        field_key: 'overhead_handling',
                        field_label: 'Overhead Handling',
                        field_role: 'definition',
                        severity: 'low',
                        title: 'Missing comparison answers',
                        rationale: 'Fewer than two answers were submitted.',
                        stakeholder_positions: [],
                        source_refs: [],
                    },
                ],
            }),
        });
        // Drop the template prompt so the fallback question cannot reintroduce the
        // promoted guidance text we expect to be filtered out.
        props.metric.field_roles[0] = createFieldRole({ workshop_prompt: null });

        const wrapper = mount(WorkshopPrepPage, { props });
        const fieldPrep = await openIntegratedFieldPrep(wrapper);

        const decisionTitle = fieldPrep.find('[data-test="attention-title"]');
        expect(decisionTitle.text()).toBe('Confirm the overhead owner');
        expect(decisionTitle.classes()).toContain('dark:text-blue-400');

        const missingTitle = fieldPrep.find('[data-test="missing-title"]');
        expect(missingTitle.text()).toBe('Missing comparison answers');
        expect(missingTitle.classes()).toContain('dark:text-amber-400');

        // Promoted workshop guidance is filtered out of the source context, but a
        // genuine submitted answer still shows.
        expect(fieldPrep.text()).toContain('Alice answer');
        expect(fieldPrep.text()).not.toContain(
            'Hidden promoted guidance excerpt',
        );
    });

    it('adds sticky-summary scroll offsets to in-page preparation targets', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        expect(wrapper.find('#facilitation-plan-heading').classes()).toContain(
            'scroll-mt-24',
        );
        // The field anchor now lives on the integrated run-of-show segment.
        expect(
            wrapper.find('#field-detail-overhead_handling').classes(),
        ).toContain('scroll-mt-24');
    });

    it('opens and pulses the integrated segment when a field-evidence hash targets it', async () => {
        const wrapper = mount(WorkshopPrepPage, {
            attachTo: document.body,
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        window.location.hash = '#field-detail-overhead_handling';
        window.dispatchEvent(new Event('hashchange'));
        await flushPromises();
        await wrapper.vm.$nextTick();
        await flushPromises();

        // The bound segment expands into its field-prep workspace...
        expect(
            wrapper.find('[data-test="run-of-show-field-prep"]').exists(),
        ).toBe(true);

        // ...and the integrated segment wrapper carries the brief highlight.
        const anchor = wrapper.find('#field-detail-overhead_handling');
        expect(anchor.exists()).toBe(true);
        const segment = anchor.element.closest(
            '[data-test="run-of-show-item"]',
        ) as HTMLElement | null;
        expect(segment?.getAttribute('data-pulsing')).toBe('true');
        expect(segment?.className).toContain('evidence-pulse');

        wrapper.unmount();
    });

    it('leaves the page intact when an anchor targets a field outside the run of show', async () => {
        const wrapper = mount(WorkshopPrepPage, {
            attachTo: document.body,
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const pulsingItems = () =>
            wrapper
                .findAll('[data-test="run-of-show-item"]')
                .filter((item) => item.attributes('data-pulsing') === 'true');

        // A real field with no run-of-show segment never pulses and has no anchor.
        window.location.hash = '#field-detail-missing_field';
        window.dispatchEvent(new Event('hashchange'));
        await flushPromises();
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(pulsingItems()).toHaveLength(0);
        expect(wrapper.find('#field-detail-missing_field').exists()).toBe(false);

        // An unknown field key is ignored without breaking the page.
        window.location.hash = '#field-detail-unknown_field';
        window.dispatchEvent(new Event('hashchange'));
        await flushPromises();
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(pulsingItems()).toHaveLength(0);
        expect(wrapper.find('[data-test="facilitation-plan"]').exists()).toBe(
            true,
        );

        wrapper.unmount();
    });

    it('does not render a standalone source summary when there are no must-resolve conflicts', () => {
        const emptyBrief = mockBrief({
            material_conflicts: [],
            missing_evidence: [],
            recommended_questions: [],
        });
        const props = createPageProps({
            metric_alignment_brief: emptyBrief,
        });
        const wrapper = mount(WorkshopPrepPage, { props });

        expect(wrapper.find('[data-test="alignment-brief"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Source Summary');
    });

    it('does not render the deterministic source counts as a standalone prep block', () => {
        const props = createPageProps();
        const wrapper = mount(WorkshopPrepPage, { props });

        expect(wrapper.find('[data-test="alignment-brief"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="stat-submissions"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="stat-signals"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="stat-guided"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="stat-missing"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Source Summary');
        expect(wrapper.text()).not.toContain('Alignment Summary');
    });

    it('allows preparing the run of show from the agenda without a draft', () => {
        const wrapper = mount(WorkshopPrepPage, { props: createPageProps() });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const prepare = panel.find('[data-test="facilitation-prepare"]');

        expect(panel.exists()).toBe(true);
        // Preparation is derived from the canonical agenda, not the AI draft.
        expect(panel.find('[data-test="facilitation-empty"]').text()).toContain(
            'from the workshop agenda',
        );
        expect(prepare.attributes('disabled')).toBeUndefined();
    });

    it('prepares a derived run of show from the draft', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: facilitationBriefFixture() }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps(),
        });

        await wrapper
            .find('[data-test="facilitation-prepare"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/organizations/org-123/engagements/eng-456/metrics/metric-789/prep/facilitation',
            expect.objectContaining({ method: 'POST' }),
        );

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        expect(panel.findAll('[data-test="run-of-show-item"]')).toHaveLength(2);
        expect(panel.find('[data-test="run-of-show-total"]').text()).toContain(
            '25 min',
        );
        expect(
            panel.find('[data-test="run-of-show-provenance"]').text(),
        ).toBe('Suggested');
    });

    it('regenerates the run of show from the current decisions on explicit confirm', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: facilitationBriefFixture({ revision: 2 }),
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        // Regeneration is gated behind an explicit confirmation because it
        // discards manual plan edits.
        await wrapper
            .find('[data-test="facilitation-regenerate"]')
            .trigger('click');
        await wrapper
            .find('[data-test="facilitation-regenerate-confirm"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/organizations/org-123/engagements/eng-456/metrics/metric-789/prep/facilitation',
            expect.objectContaining({ method: 'POST' }),
        );

        const body = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(body).toEqual({ regenerate: true, revision: 1 });
    });

    it('marks edited content as a consultant edit and saves with the current revision', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: facilitationBriefFixture({ revision: 2 }),
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const saveButton = panel.find('[data-test="facilitation-save"]');

        // Save is disabled until the plan is edited.
        expect(saveButton.attributes('disabled')).toBeDefined();

        const purpose = panel.find('[data-test="run-of-show-purpose"]');
        await purpose.setValue('Consultant-rewritten opening.');

        expect(
            panel.find('[data-test="run-of-show-provenance"]').text(),
        ).toBe('Your edit');
        expect(saveButton.attributes('disabled')).toBeUndefined();

        await saveButton.trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/organizations/org-123/engagements/eng-456/metrics/metric-789/prep/facilitation',
            expect.objectContaining({ method: 'PATCH' }),
        );

        const body = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(body.revision).toBe(1);
        expect(body.run_of_show[0].purpose.text).toBe(
            'Consultant-rewritten opening.',
        );
        expect(body.run_of_show[0].purpose.provenance).toBe(
            'consultant_authored',
        );
    });

    it('surfaces a stale-revision conflict without dropping local edits', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({
                error: {
                    message:
                        'This facilitation plan changed in another session. Reload before saving again.',
                },
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        await panel
            .find('[data-test="run-of-show-title"]')
            .setValue('Reordered opening');
        await panel.find('[data-test="facilitation-save"]').trigger('click');
        await flushPromises();

        expect(
            panel.find('[data-test="facilitation-save-error"]').text(),
        ).toContain('changed in another session');
        expect(
            (
                panel.find('[data-test="run-of-show-title"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('Reordered opening');
    });

    it('flags a facilitation plan that needs source review', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture({
                    status: 'needs_source_review',
                    needs_source_review: true,
                }),
            }),
        });

        expect(
            wrapper.find('[data-test="facilitation-needs-review"]').text(),
        ).toContain('source brief changed');
    });

    it('allows switching between metrics in the same engagement via the header dropdown selector', async () => {
        const routerSpy = vi.spyOn(router, 'visit').mockImplementation(async () => {});

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                engagement_metrics: [
                    { id: 'metric-789', name: 'Gross Churn Rate', status: 'InReview' },
                    { id: 'metric-101', name: 'Net Revenue Retention', status: 'Draft' },
                ],
            }),
        });

        const selector = wrapper.find('[data-test="metric-title-selector"]');
        expect(selector.exists()).toBe(true);
        expect(selector.text()).toContain('Gross Churn Rate');

        // Trigger switchMetric
        await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'metric-101');
        await flushPromises();

        expect(routerSpy).toHaveBeenCalledWith(
            '/organizations/org-123/engagements/eng-456/metrics/metric-101/prep'
        );

        routerSpy.mockRestore();
    });

    it('exposes labeled, keyboard-reachable run-of-show controls with reorder boundaries', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const items = panel.findAll('[data-test="run-of-show-item"]');

        expect(
            panel.find('[data-test="run-of-show-title"]').attributes('aria-label'),
        ).toBe('Segment title');
        expect(
            panel
                .find('[data-test="run-of-show-duration"]')
                .attributes('aria-label'),
        ).toBe('Segment duration in minutes');
        expect(
            panel
                .find('[data-test="run-of-show-purpose"]')
                .attributes('aria-label'),
        ).toBe('Segment purpose');

        // First segment cannot move up; last cannot move down.
        expect(
            items[0]
                .find('[data-test="run-of-show-move-up"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            items[0]
                .find('[data-test="run-of-show-move-down"]')
                .attributes('disabled'),
        ).toBeUndefined();
        expect(
            items[items.length - 1]
                .find('[data-test="run-of-show-move-down"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            items[items.length - 1]
                .find('[data-test="run-of-show-move-up"]')
                .attributes('disabled'),
        ).toBeUndefined();
    });

    it('shows a decorative drag handle and a destructive-hover remove on run-of-show items', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const items = panel.findAll('[data-test="run-of-show-item"]');
        expect(items.length).toBeGreaterThan(0);

        // Every segment carries a grip affordance, but it is decorative only:
        // reordering stays on the labeled move buttons, so the grip is hidden
        // from assistive tech.
        const handles = panel.findAll('[data-test="run-of-show-drag-handle"]');
        expect(handles).toHaveLength(items.length);
        handles.forEach((handle) => {
            expect(handle.attributes('aria-hidden')).toBe('true');
        });

        // The trash button reads neutral but turns destructive on hover/focus,
        // so it no longer looks identical to the grey move buttons.
        const removeClasses = items[0]
            .find('[data-test="run-of-show-remove"]')
            .classes();
        expect(removeClasses).toContain('hover:text-destructive');
        expect(removeClasses).toContain('hover:bg-destructive/10');
    });

    it('reorders, adds, and removes run-of-show segments locally', async () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');

        const titleAt = (index: number) =>
            (
                panel.findAll('[data-test="run-of-show-item"]')[index].find(
                    '[data-test="run-of-show-title"]',
                ).element as HTMLInputElement
            ).value;

        expect(titleAt(0)).toBe('Frame the metric and alignment goal');

        await panel
            .findAll('[data-test="run-of-show-item"]')[0]
            .find('[data-test="run-of-show-move-down"]')
            .trigger('click');

        expect(titleAt(0)).toBe('Lock Official Definition');

        await panel.find('[data-test="run-of-show-add"]').trigger('click');
        expect(panel.findAll('[data-test="run-of-show-item"]')).toHaveLength(3);

        await panel
            .findAll('[data-test="run-of-show-item"]')[2]
            .find('[data-test="run-of-show-remove"]')
            .trigger('click');
        await wrapper
            .find('[data-test="run-of-show-remove-confirm"]')
            .trigger('click');
        expect(panel.findAll('[data-test="run-of-show-item"]')).toHaveLength(2);

        // Reordering is enough to enable saving.
        expect(
            panel.find('[data-test="facilitation-save"]').attributes('disabled'),
        ).toBeUndefined();
    });

    it('removes the Expected Outputs surface from the visible workflow', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');

        // Expected Outputs is no longer a second editable checklist on the page.
        expect(panel.find('[data-test="expected-outputs"]').exists()).toBe(
            false,
        );
        expect(
            panel.findAll('[data-test="expected-output-item"]'),
        ).toHaveLength(0);
        expect(panel.find('[data-test="expected-output-add"]').exists()).toBe(
            false,
        );
        expect(panel.text()).not.toContain('Expected outputs');
    });

    // T060/F5: expected_outputs is hidden and now server-authoritative, so the
    // page must not send it at all — a stale carried-forward copy can no longer
    // clobber a concurrent change to the persisted outputs.
    it('omits the hidden expected outputs from the save payload', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: facilitationBriefFixture({ revision: 2 }),
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const brief = facilitationBriefFixture();
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({ facilitation_brief: brief }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');

        // An edit to a visible plan field is what unlocks Save...
        await panel
            .find('[data-test="run-of-show-title"]')
            .setValue('Reframed opening');
        await panel.find('[data-test="facilitation-save"]').trigger('click');
        await flushPromises();

        // ...and the payload carries the editable plan fields but not the hidden,
        // server-authoritative expected_outputs.
        const body = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(body.run_of_show).toBeDefined();
        expect(body.expected_outputs).toBeUndefined();
    });

    it('keeps Save disabled until a visible plan field changes, ignoring hidden outputs', async () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const saveButton = panel.find('[data-test="facilitation-save"]');
        const child = wrapper.findComponent(FacilitationPlanPanel);

        // Hidden expected_outputs exist on the brief but must not create a false
        // dirty state at rest.
        expect(child.vm.isDirty).toBe(false);
        expect(saveButton.attributes('disabled')).toBeDefined();

        // Editing the segment timebox is enough to enable Save.
        await panel.find('[data-test="run-of-show-duration"]').setValue(20);
        expect(child.vm.isDirty).toBe(true);
        expect(saveButton.attributes('disabled')).toBeUndefined();
    });

    it('enables explicit Save when any editable plan field changes (title, timebox, or notes)', async () => {
        // The plan uses classic manual Save: nothing is persisted until an
        // editable plan field is changed and the Save button is pressed.
        const mountPanel = () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });
            const panel = wrapper.find('[data-test="facilitation-plan"]');

            return {
                panel,
                child: wrapper.findComponent(FacilitationPlanPanel),
                save: panel.find('[data-test="facilitation-save"]'),
            };
        };

        // Editing a segment title enables Save.
        {
            const { panel, child, save } = mountPanel();
            expect(child.vm.isDirty).toBe(false);
            expect(save.attributes('disabled')).toBeDefined();

            await panel
                .find('[data-test="run-of-show-title"]')
                .setValue('Kickoff framing');
            expect(child.vm.isDirty).toBe(true);
            expect(save.attributes('disabled')).toBeUndefined();
        }

        // Editing a segment timebox enables Save.
        {
            const { panel, child, save } = mountPanel();
            await panel
                .find('[data-test="run-of-show-duration"]')
                .setValue(25);
            expect(child.vm.isDirty).toBe(true);
            expect(save.attributes('disabled')).toBeUndefined();
        }

        // Editing facilitator notes inside the integrated segment enables Save.
        {
            const { panel, child, save } = mountPanel();
            await panel
                .find('[data-test="run-of-show-field-prep-toggle"]')
                .trigger('click');
            await panel
                .find(
                    '[data-test="run-of-show-field-prep"] [data-test="run-of-show-purpose"]',
                )
                .setValue('Land the overhead owner.');
            expect(child.vm.isDirty).toBe(true);
            expect(save.attributes('disabled')).toBeUndefined();
        }
    });

    it('keeps the plan pristine when only read-only field context is inspected', async () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const child = wrapper.findComponent(FacilitationPlanPanel);
        const saveButton = panel.find('[data-test="facilitation-save"]');

        expect(child.vm.isDirty).toBe(false);

        // Opening the integrated field-prep workspace is navigation, not an
        // edit — the read-only field context must never mark the plan dirty.
        const toggle = panel.find(
            '[data-test="run-of-show-field-prep-toggle"]',
        );
        await toggle.trigger('click');
        expect(child.vm.isDirty).toBe(false);

        // Inspecting a read-only source excerpt is likewise non-mutating.
        const excerptToggle = panel.find(
            '[data-test="run-of-show-field-prep"] [data-test="toggle-excerpt"]',
        );
        expect(excerptToggle.exists()).toBe(true);
        await excerptToggle.trigger('click');
        expect(child.vm.isDirty).toBe(false);

        // Collapsing the workspace again leaves the plan pristine and Save off.
        await toggle.trigger('click');
        expect(child.vm.isDirty).toBe(false);
        expect(saveButton.attributes('disabled')).toBeDefined();
    });

    it('prevents navigation when there are unsaved facilitation plan edits', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const mockOn = vi.mocked(router.on);
        let onBeforeHandler: any = null;
        mockOn.mockImplementation((event: string, callback: any) => {
            if (event === 'before') {
                onBeforeHandler = callback;
            }
            return () => {};
        });

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        expect(onBeforeHandler).not.toBeNull();

        const eventNotDirty = {
            detail: { visit: { prefetch: false } },
            preventDefault: vi.fn(),
        };
        onBeforeHandler(eventNotDirty);
        expect(eventNotDirty.preventDefault).not.toHaveBeenCalled();

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        const titleInput = panel.find('[data-test="run-of-show-title"]');
        await titleInput.setValue('Dirty Title');

        const child = wrapper.findComponent(FacilitationPlanPanel);
        expect(child.vm.isDirty).toBe(true);

        const eventDirtyBlocked = {
            detail: { visit: { prefetch: false } },
            preventDefault: vi.fn(),
        };
        onBeforeHandler(eventDirtyBlocked);
        expect(confirmSpy).toHaveBeenCalled();
        expect(eventDirtyBlocked.preventDefault).toHaveBeenCalled();

        confirmSpy.mockReturnValue(true);
        const eventDirtyAllowed = {
            detail: { visit: { prefetch: false } },
            preventDefault: vi.fn(),
        };
        onBeforeHandler(eventDirtyAllowed);
        expect(eventDirtyAllowed.preventDefault).not.toHaveBeenCalled();

        confirmSpy.mockRestore();
        mockOn.mockRestore();
    });

    it('gives the facilitation panel typed field context for a field-bound segment', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.findComponent(FacilitationPlanPanel);
        const context = (panel.vm as any).segmentContextById[
            'segment:official_definition'
        ];

        // The segment links decision:0 -> overhead_handling, which resolves to
        // its template field role and the brief attention items that put it on
        // the agenda.
        expect(context.isFieldBound).toBe(true);
        expect(context.fields).toHaveLength(1);
        expect(context.fields[0].fieldKey).toBe('overhead_handling');
        expect(context.fields[0].fieldRole?.label).toBe('Overhead Handling');
        expect(context.fields[0].attentionTier).toBe('must-resolve');
        expect(context.fields[0].briefContext?.conflicts).toHaveLength(1);
        expect(context.fields[0].briefContext?.conflicts[0].field_key).toBe(
            'overhead_handling',
        );
        expect(context.fields[0].briefContext?.recommendedQuestions).toHaveLength(
            1,
        );
    });

    it('keeps general run-of-show segments free of field context and compact', () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.findComponent(FacilitationPlanPanel);
        const context = (panel.vm as any).segmentContextById['segment:framing'];

        // A segment with no decision_ids resolves to no field context...
        expect(context.isFieldBound).toBe(false);
        expect(context.fields).toEqual([]);

        // ...yet still renders as a normal editable segment (title input present).
        const items = panel.findAll('[data-test="run-of-show-item"]');
        const framingTitle = items[0].find('[data-test="run-of-show-title"]');
        expect((framingTitle.element as HTMLInputElement).value).toBe(
            'Frame the metric and alignment goal',
        );

        // A general segment exposes no field-prep toggle or expanded body.
        expect(
            items[0]
                .find('[data-test="run-of-show-field-prep-toggle"]')
                .exists(),
        ).toBe(false);
    });

    it('expands a field-bound segment into an integrated field prep workspace', async () => {
        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');

        // The field-bound segment opens on demand, matching the accordion model.
        const toggle = panel.find(
            '[data-test="run-of-show-field-prep-toggle"]',
        );
        expect(toggle.exists()).toBe(true);
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(panel.find('[data-test="run-of-show-field-prep"]').exists()).toBe(
            false,
        );

        await toggle.trigger('click');

        const fieldPrep = panel.find('[data-test="run-of-show-field-prep"]');
        expect(fieldPrep.exists()).toBe(true);
        expect(toggle.attributes('aria-expanded')).toBe('true');

        // Why this needs alignment (read-only conflict rationale).
        expect(fieldPrep.text()).toContain(
            'Stakeholders submitted different normalized values.',
        );
        // Submitted answers / source positions.
        expect(fieldPrep.text()).toContain('Alice Finance answer');
        expect(fieldPrep.text()).toContain('Bob Sales answer');
        // Read-only template workshop guidance.
        expect(
            fieldPrep
                .find('[data-test="run-of-show-workshop-guidance"]')
                .exists(),
        ).toBe(true);
        expect(fieldPrep.text()).toContain(
            'Facilitator should drive consensus on whether to use head-count or revenue ratio.',
        );
        // Editable facilitator notes live in the same workspace.
        const notes = fieldPrep.find('[data-test="run-of-show-purpose"]');
        expect(notes.exists()).toBe(true);
        expect((notes.element as HTMLTextAreaElement).value).toBe(
            'Agree the official overhead allocation method.',
        );
    });

    it('hides the workshop guidance block when the field has no guidance', async () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
        });
        props.metric.field_roles[0] = createFieldRole({
            workshop_guidance: null,
        });

        const wrapper = mount(WorkshopPrepPage, { props });
        const panel = wrapper.find('[data-test="facilitation-plan"]');
        await panel
            .find('[data-test="run-of-show-field-prep-toggle"]')
            .trigger('click');

        const fieldPrep = panel.find('[data-test="run-of-show-field-prep"]');
        expect(fieldPrep.exists()).toBe(true);
        // The conflict context still renders...
        expect(fieldPrep.text()).toContain(
            'Stakeholders submitted different normalized values.',
        );
        // ...but the read-only guidance block is omitted entirely.
        expect(
            fieldPrep
                .find('[data-test="run-of-show-workshop-guidance"]')
                .exists(),
        ).toBe(false);
        expect(fieldPrep.text()).not.toContain('Workshop guidance');
    });

    it('saves only the editable plan fields, never the read-only field context', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: facilitationBriefFixture({ revision: 2 }),
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(WorkshopPrepPage, {
            props: createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            }),
        });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        await panel
            .find('[data-test="run-of-show-field-prep-toggle"]')
            .trigger('click');

        const notes = panel.find(
            '[data-test="run-of-show-field-prep"] [data-test="run-of-show-purpose"]',
        );
        await notes.setValue('Drive consensus on the allocation method.');

        await panel.find('[data-test="facilitation-save"]').trigger('click');
        await flushPromises();

        const body = JSON.parse(fetchMock.mock.calls[0][1].body);
        const fieldBoundSegment = body.run_of_show.find(
            (segment: { id: string }) =>
                segment.id === 'segment:official_definition',
        );

        // The edited facilitator note is saved as a consultant edit...
        expect(fieldBoundSegment.purpose.text).toBe(
            'Drive consensus on the allocation method.',
        );
        expect(fieldBoundSegment.purpose.provenance).toBe('consultant_authored');
        // ...but the run-of-show item carries only the editable plan shape, never
        // the read-only field context surfaced inside the expanded body.
        expect(Object.keys(fieldBoundSegment).sort()).toEqual([
            'decision_ids',
            'duration_minutes',
            'id',
            'position',
            'purpose',
            'title',
        ]);
        const serialized = JSON.stringify(body);
        expect(serialized).not.toContain(
            'Facilitator should drive consensus on whether to use head-count or revenue ratio.',
        );
        expect(serialized).not.toContain(
            'Stakeholders submitted different normalized values.',
        );
    });

    it('adopts the refined page and panel shell (Task 31)', () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
        });
        const wrapper = mount(WorkshopPrepPage, { props });

        const panel = wrapper.find('[data-test="facilitation-plan"]');
        expect(panel.exists()).toBe(true);

        // Renders the plan title
        expect(panel.find('#facilitation-plan-heading').text()).toBe('Run of show');

        // Renders the status selector
        expect(panel.find('[data-test="facilitation-status"]').exists()).toBe(true);

        // Renders total duration
        expect(panel.find('[data-test="run-of-show-total"]').text()).toContain('25 min');

        // Renders must-resolve count
        expect(panel.find('[data-test="run-of-show-must-resolve-count"]').text()).toContain('1 must resolve');

        // Renders confirmation count (could be 0/absent since confirmationCount is 0 in the default mockBrief fixture)
        expect(panel.find('[data-test="run-of-show-confirmation-count"]').exists()).toBe(false);
    });

    it('renders the confirmation count in the refined shell when present', () => {
        const props = createPageProps({
            facilitation_brief: facilitationBriefFixture(),
            metric_alignment_brief: mockBrief({
                material_conflicts: [],
                decision_needed: [
                    {
                        id: 'field:overhead_handling:decision_needed',
                        category: 'decision_needed',
                        field_key: 'overhead_handling',
                        field_label: 'Overhead Handling',
                        field_role: 'definition',
                        severity: 'low',
                        title: 'Confirm Overhead Handling.',
                        rationale: 'Review and confirm this choice.',
                        stakeholder_positions: [],
                        source_refs: [],
                    }
                ],
                missing_evidence: [],
            }),
        });
        const wrapper = mount(WorkshopPrepPage, { props });
        const panel = wrapper.find('[data-test="facilitation-plan"]');

        expect(panel.find('[data-test="run-of-show-must-resolve-count"]').exists()).toBe(false);
        expect(panel.find('[data-test="run-of-show-confirmation-count"]').text()).toContain('1 to confirm');
    });

    describe('T032 - Segment Headers and Accessible Controls', () => {
        it('exposes and updates aria-expanded dynamically on toggle button click', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });
            const panel = wrapper.find('[data-test="facilitation-plan"]');

            // Find a field-bound segment toggle
            const toggle = panel.find('[data-test="run-of-show-field-prep-toggle"]');
            expect(toggle.exists()).toBe(true);
            expect(toggle.attributes('aria-expanded')).toBe('false');

            // Click to expand
            await toggle.trigger('click');
            expect(toggle.attributes('aria-expanded')).toBe('true');

            // Click to collapse
            await toggle.trigger('click');
            expect(toggle.attributes('aria-expanded')).toBe('false');
        });

        it('ensures segment titles remain editable and mark the plan dirty', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });
            const panel = wrapper.find('[data-test="facilitation-plan"]');
            const titleInput = panel.find('[data-test="run-of-show-title"]');

            expect(titleInput.exists()).toBe(true);
            await titleInput.setValue('New Editable Segment Title');

            const child = wrapper.findComponent(FacilitationPlanPanel);
            expect(child.vm.isDirty).toBe(true);
            expect(panel.find('[data-test="facilitation-save"]').attributes('disabled')).toBeUndefined();
        });

        it('ensures move/remove actions remain visible/keyboard-reachable to assistive tech and respect boundaries', () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });
            const panel = wrapper.find('[data-test="facilitation-plan"]');
            const items = panel.findAll('[data-test="run-of-show-item"]');

            // Reorder and remove buttons must always be in the DOM and have accessible labels
            items.forEach((item) => {
                const moveUp = item.find('[data-test="run-of-show-move-up"]');
                const moveDown = item.find('[data-test="run-of-show-move-down"]');
                const remove = item.find('[data-test="run-of-show-remove"]');

                expect(moveUp.exists()).toBe(true);
                expect(moveDown.exists()).toBe(true);
                expect(remove.exists()).toBe(true);

                expect(moveUp.attributes('aria-label')).toBe('Move segment earlier');
                expect(moveDown.attributes('aria-label')).toBe('Move segment later');
                expect(remove.attributes('aria-label')).toBe('Remove segment');

                // They should not be hidden from assistive technology
                expect(moveUp.attributes('aria-hidden')).toBeUndefined();
                expect(moveDown.attributes('aria-hidden')).toBeUndefined();
                expect(remove.attributes('aria-hidden')).toBeUndefined();
            });

            // Boundary checks: first item cannot move up, last item cannot move down
            expect(items[0].find('[data-test="run-of-show-move-up"]').attributes('disabled')).toBeDefined();
            expect(items[items.length - 1].find('[data-test="run-of-show-move-down"]').attributes('disabled')).toBeDefined();
        });

        it('renders segment headers for both field-bound and general segments without duplicate facilitator question body blocks', () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });
            const panel = wrapper.find('[data-test="facilitation-plan"]');
            const items = panel.findAll('[data-test="run-of-show-item"]');

            items.forEach((item) => {
                // The segment header element (excluding the expanded body)
                // In our component, the first child `div` is the header row
                const header = item.find('div');

                // Segment header must not contain any duplicate facilitator question text or components
                expect(header.find('[data-test="run-of-show-field-prep"]').exists()).toBe(false);
                expect(header.find('[data-test="facilitator-questions"]').exists()).toBe(false);
                expect(header.find('[data-test="facilitator-question"]').exists()).toBe(false);
            });
        });
    });

    describe('T033 - Redesign Expanded Field Prep Without Duplicate Questions', () => {
        it('shows submitted answers, source groups, workshop guidance, and facilitator notes together in a focused workspace', async () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            const wrapper = mount(WorkshopPrepPage, { props });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // Left column elements
            expect(fieldPrep.find('[data-test="field-brief-context"]').exists()).toBe(true);
            expect(fieldPrep.text()).toContain('Submitted Answers');

            // Right column elements (Workshop Cues now live here, not in the
            // left source evidence column — T040).
            expect(fieldPrep.find('[data-test="run-of-show-workshop-guidance"]').exists()).toBe(true);
            expect(fieldPrep.text()).toContain('Workshop guidance');
            expect(fieldPrep.find('[data-test="run-of-show-workshop-cues"]').exists()).toBe(true);
            expect(fieldPrep.text()).toContain('Facilitation assist');
            expect(fieldPrep.find('[data-test="run-of-show-purpose"]').exists()).toBe(true);
            expect(fieldPrep.find('[data-test="run-of-show-provenance"]').exists()).toBe(true);
        });

        it('suppresses duplicate facilitator questions when the title matches the workshop prompt', async () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    material_conflicts: [],
                    missing_evidence: [],
                    recommended_questions: [],
                }),
            });
            // We mock the first segment title to be exactly the prompt to simulate duplicate question
            const brief = props.facilitation_brief!;
            brief.document.run_of_show[1].title = 'How should corporate overhead be allocated?';

            const wrapper = mount(WorkshopPrepPage, { props });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // Since the title is exactly 'How should corporate overhead be allocated?',
            // the fallback workshop prompt (which is also 'How should corporate overhead be allocated?')
            // should be filtered out as a duplicate question.
            expect(fieldPrep.text()).not.toContain('Supplemental prompts');
            expect(fieldPrep.find('[data-test="questions-list"]').exists()).toBe(false);
        });

        it('shows supplemental prompts only when a recommended question is materially different from the segment title', async () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    material_conflicts: [],
                    missing_evidence: [],
                    recommended_questions: [
                        {
                            id: 'q:different',
                            field_key: 'overhead_handling',
                            question: 'This is a completely different recommended question?',
                            reason: 'Important detail.',
                            priority: 'medium',
                            source_refs: [],
                        }
                    ],
                }),
            });
            const wrapper = mount(WorkshopPrepPage, { props });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // The segment title is different, so we should see the supplemental prompts block
            expect(fieldPrep.text()).toContain('Supplemental prompts (1)');
            expect(fieldPrep.find('[data-test="questions-list"]').exists()).toBe(true);
            expect(fieldPrep.find('[data-test="question-text"]').text()).toBe('This is a completely different recommended question?');
        });

        it('ensures interacting with read-only context does not mark the plan dirty, while notes do', async () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            const wrapper = mount(WorkshopPrepPage, { props });
            const child = wrapper.findComponent(FacilitationPlanPanel);
            expect(child.vm.isDirty).toBe(false);

            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            // Inspecting read-only guidance or source refs
            expect(fieldPrep.find('[data-test="run-of-show-workshop-guidance"]').exists()).toBe(true);
            expect(child.vm.isDirty).toBe(false);

            // Editing facilitator notes (textarea) marks dirty
            const textarea = fieldPrep.find('[data-test="run-of-show-purpose"]');
            await textarea.setValue('Facilitator note update');
            expect(child.vm.isDirty).toBe(true);
        });
    });

    describe('T034 - Sticky Manual Save and Status Footer', () => {
        it('renders the status selector and Save plan button inside the sticky footer', () => {
            const wrapper = mount(FacilitationPlanPanel, {
                props: {
                    brief: facilitationBriefFixture(),
                    canPrepare: true,
                    preparing: false,
                    saving: false,
                    prepareError: null,
                    saveError: null,
                },
            });

            const footer = wrapper.find('.sticky.bottom-0');
            expect(footer.exists()).toBe(true);
            expect(footer.find('[data-test="facilitation-status"]').exists()).toBe(true);
            expect(footer.find('[data-test="facilitation-save"]').exists()).toBe(true);
        });

        it('enables Save when title, duration (timebox), or notes change, and keeps it disabled for clean plan', async () => {
            const mountPanel = () => {
                const wrapper = mount(FacilitationPlanPanel, {
                    props: {
                        brief: facilitationBriefFixture(),
                        canPrepare: true,
                        preparing: false,
                        saving: false,
                        prepareError: null,
                        saveError: null,
                    },
                });
                return {
                    wrapper,
                    saveButton: wrapper.find('[data-test="facilitation-save"]'),
                };
            };

            // Clean plan: Save is disabled
            {
                const { wrapper, saveButton } = mountPanel();
                expect(wrapper.vm.isDirty).toBe(false);
                expect(saveButton.attributes('disabled')).toBeDefined();
            }

            // Edit title
            {
                const { wrapper, saveButton } = mountPanel();
                await wrapper.find('[data-test="run-of-show-title"]').setValue('New Segment Title');
                expect(wrapper.vm.isDirty).toBe(true);
                expect(saveButton.attributes('disabled')).toBeUndefined();
            }

            // Edit duration (timebox)
            {
                const { wrapper, saveButton } = mountPanel();
                await wrapper.find('[data-test="run-of-show-duration"]').setValue(45);
                expect(wrapper.vm.isDirty).toBe(true);
                expect(saveButton.attributes('disabled')).toBeUndefined();
            }

            // Edit notes
            {
                const { wrapper, saveButton } = mountPanel();
                await wrapper.find('[data-test="run-of-show-field-prep-toggle"]').trigger('click');
                const notesTextarea = wrapper.find('[data-test="run-of-show-purpose"]');
                await notesTextarea.setValue('New facilitator notes text');
                expect(wrapper.vm.isDirty).toBe(true);
                expect(saveButton.attributes('disabled')).toBeUndefined();
            }
        });

        it('displays save errors in or near the sticky footer', async () => {
            const wrapper = mount(FacilitationPlanPanel, {
                props: {
                    brief: facilitationBriefFixture(),
                    canPrepare: true,
                    preparing: false,
                    saving: false,
                    prepareError: null,
                    saveError: 'Failed to save: connection timed out.',
                },
            });

            const footer = wrapper.find('.sticky.bottom-0');
            expect(footer.exists()).toBe(true);

            const errorElement = footer.find('[data-test="facilitation-save-error"]');
            expect(errorElement.exists()).toBe(true);
            expect(errorElement.text()).toBe('Failed to save: connection timed out.');
        });

        it('derives the segment save status indicator dynamically based on dirty, saving, and plan status', async () => {
            const wrapper = mount(FacilitationPlanPanel, {
                props: {
                    brief: facilitationBriefFixture(),
                    canPrepare: true,
                    preparing: false,
                    saving: false,
                    prepareError: null,
                    saveError: null,
                },
            });

            // Expand the segment so the status text is rendered
            await wrapper.find('[data-test="run-of-show-field-prep-toggle"]').trigger('click');
            const statusElement = wrapper.find('[data-test="run-of-show-save-status"]');
            expect(statusElement.exists()).toBe(true);

            // 1. Clean plan draft: shows "Saved as draft"
            expect(statusElement.text()).toBe('Saved as draft');

            // 2. Edit notes: becomes dirty and shows "Unsaved changes"
            const notesTextarea = wrapper.find('[data-test="run-of-show-purpose"]');
            await notesTextarea.setValue('New facilitator notes text');
            expect(wrapper.vm.isDirty).toBe(true);
            expect(statusElement.text()).toBe('Unsaved changes');

            // 3. Saving: shows "Saving..."
            await wrapper.setProps({ saving: true });
            expect(statusElement.text()).toBe('Saving...');

            // 4. Saved ready for workshop: shows "Ready for workshop"
            await wrapper.setProps({
                saving: false,
                brief: facilitationBriefFixture({
                    status: 'ready_for_workshop',
                }),
            });
            // Need to expand it again after brief prop changes since the working copy resets
            await wrapper.find('[data-test="run-of-show-field-prep-toggle"]').trigger('click');
            const updatedStatusElement = wrapper.find('[data-test="run-of-show-save-status"]');
            expect(updatedStatusElement.text()).toBe('Ready for workshop');
        });

        it('supports discarding unsaved changes with a confirmation modal', async () => {
            const wrapper = mount(FacilitationPlanPanel, {
                props: {
                    brief: facilitationBriefFixture(),
                    canPrepare: true,
                    preparing: false,
                    saving: false,
                    prepareError: null,
                    saveError: null,
                },
            });

            // 1. Initially clean: discard button should be disabled
            const discardButton = wrapper.find('[data-test="facilitation-discard"]');
            expect(discardButton.exists()).toBe(true);
            expect(discardButton.attributes('disabled')).toBeDefined();

            // 2. Make a change: discard button should become enabled
            await wrapper.find('[data-test="run-of-show-field-prep-toggle"]').trigger('click');
            const notesTextarea = wrapper.find('[data-test="run-of-show-purpose"]');
            await notesTextarea.setValue('Some dirty edit notes');
            expect(wrapper.vm.isDirty).toBe(true);
            expect(discardButton.attributes('disabled')).toBeUndefined();

            // 3. Click discard button to open confirmation dialog
            await discardButton.trigger('click');
            const discardDialog = wrapper.find('[data-test="facilitation-discard-dialog"]');
            expect(discardDialog.exists()).toBe(true);

            // 4. Cancel the discard: changes should be preserved
            const cancelButton = wrapper.find('[data-test="facilitation-discard-cancel"]');
            await cancelButton.trigger('click');
            expect(wrapper.vm.isDirty).toBe(true);
            expect((notesTextarea.element as HTMLTextAreaElement).value).toBe('Some dirty edit notes');

            // 5. Click discard again and confirm: changes should be reverted/reloaded
            await discardButton.trigger('click');
            const confirmButton = wrapper.find('[data-test="facilitation-discard-confirm"]');
            await confirmButton.trigger('click');

            expect(wrapper.vm.isDirty).toBe(false);
            // Re-expand to check the text value
            await wrapper.find('[data-test="run-of-show-field-prep-toggle"]').trigger('click');
            const resetNotesTextarea = wrapper.find('[data-test="run-of-show-purpose"]');
            expect((resetNotesTextarea.element as HTMLTextAreaElement).value).not.toBe('Some dirty edit notes');
        });
    });

    describe('Focus Time Regression (Non-CAC/CPL Metric)', () => {

        it('asserts that Focus Time page preserves raw casing and carries no CAC/CPL jargon', async () => {
            const props = createPageProps({
                metric_alignment_brief: focusTimeBrief(),
                facilitation_brief: focusTimeFacilitationBrief(),
                metric: {
                    id: 'focus-time-metric',
                    name: 'Focus Time Alignment',
                    status: 'in_review',
                    key: 'focus-time-alignment',
                    field_roles: [
                        {
                            key: 'focus_hours_target',
                            label: 'Ideal focus hours per workday',
                            role: 'definition',
                            required: true,
                        }
                    ],
                    unclassified: [],
                }
            });

            const wrapper = mount(WorkshopPrepPage, { props });
            await openIntegratedFieldPrep(wrapper);

            // 1. Expected number of field-bound segments: 1
            const segments = wrapper.findAll('[data-test="run-of-show-item"]');
            expect(segments.length).toBe(1);
            const titleInput = segments[0].find('input[data-test="run-of-show-title"]');
            expect((titleInput.element as HTMLInputElement).value).toBe('Lock: Ideal focus hours per workday');

            // 2. Original raw value casing is preserved (Avery Lee (Engineering) is shown)
            const textContent = wrapper.text();
            expect(textContent).toContain('Avery Lee');
            expect(textContent).toContain('Jordan Park');

            // 3. No CAC/CPL/revenue specific jargon/literal tokens exist in the text of the page
            expect(textContent).not.toContain('CAC');
            expect(textContent).not.toContain('CPL');
            expect(textContent).not.toContain('revenue');
            expect(textContent).not.toContain('Cost pool');
            expect(textContent).not.toContain('Acquisition');
        });

        // T059/F4 / T063: Focus Time's cue is thin (no probe/risk cue and only
        // a simple divergence sentence), so it must be suppressed/gated from
        // the UI rather than rendering as a filler/empty shell.
        it('suppresses the Focus Time cue because it is too thin', async () => {
            const props = createPageProps({
                metric_alignment_brief: focusTimeBrief(),
                facilitation_brief: focusTimeFacilitationBrief(),
                metric: {
                    id: 'focus-time-metric',
                    name: 'Focus Time Alignment',
                    status: 'in_review',
                    key: 'focus-time-alignment',
                    field_roles: [
                        {
                            key: 'focus_hours_target',
                            label: 'Ideal focus hours per workday',
                            role: 'definition',
                            required: true,
                        }
                    ],
                    unclassified: [],
                }
            });

            const wrapper = mount(WorkshopPrepPage, { props });
            await openIntegratedFieldPrep(wrapper);

            const cue = wrapper.find('[data-test="run-of-show-workshop-cues"]');
            expect(cue.exists()).toBe(false);
        });
    });

    describe('T067 - Detector Signal Survives The Thin-Cue Gate', () => {
        const focusTimeMetric = () => ({
            id: 'focus-time-metric',
            name: 'Focus Time Alignment',
            status: 'in_review',
            key: 'focus-time-alignment',
            field_roles: [
                {
                    key: 'focus_hours_target',
                    label: 'Ideal focus hours per workday',
                    role: 'definition',
                    required: true,
                },
            ],
            unclassified: [],
        });

        // T067: gating the thin cue must not also drop the detector signal — the
        // one source-specific sentence for the field. With the full cue shell
        // suppressed, the minimal signal-only note still surfaces that sentence.
        it('keeps the detector signal sentence when the thin cue shell is gated', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    metric_alignment_brief: focusTimeBrief(),
                    facilitation_brief: focusTimeFacilitationBrief(),
                    metric: focusTimeMetric(),
                }),
            });
            await openIntegratedFieldPrep(wrapper);

            // The full cue shell (with its decision-frame boilerplate) stays gated...
            expect(
                wrapper.find('[data-test="run-of-show-workshop-cues"]').exists(),
            ).toBe(false);

            // ...but the detector signal's one substantive sentence survives.
            const signal = wrapper.find(
                '[data-test="run-of-show-workshop-cue-signal"]',
            );
            expect(signal.exists()).toBe(true);
            expect(signal.text()).toContain(
                'The team disagrees on how many hours of uninterrupted focus time are realistic in a day.',
            );
        });

        // A dense cue still renders its full shell, and the detector signal lives
        // inside that shell's disclosure — so the minimal fallback must not also
        // render (no duplicate detector line). Enriching the same field with
        // matrix context makes the deterministic cue substantive (why/probe/risk).
        it('does not render the minimal fallback when the full cue is substantive', async () => {
            const brief = focusTimeBrief();
            brief.cue_contexts[0].matrix_context = [
                {
                    field_key: 'focus_window_preference',
                    field_label: 'Preferred focus window',
                    field_role: 'context',
                    stakeholder_positions: [],
                    source_refs: [],
                    citation_ids: [],
                },
            ];

            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    metric_alignment_brief: brief,
                    facilitation_brief: focusTimeFacilitationBrief(),
                    metric: focusTimeMetric(),
                }),
            });
            await openIntegratedFieldPrep(wrapper);

            expect(
                wrapper.find('[data-test="run-of-show-workshop-cues"]').exists(),
            ).toBe(true);
            expect(
                wrapper
                    .find('[data-test="run-of-show-workshop-cue-signal"]')
                    .exists(),
            ).toBe(false);
        });
    });

    describe('T053 - Auto-Resize Facilitator Notes Textareas', () => {
        it('attaches vAutoResize directive and mutates style height on input', async () => {
            const wrapper = mount(FacilitationPlanPanel, {
                props: {
                    brief: facilitationBriefFixture(),
                    canPrepare: true,
                    preparing: false,
                    saving: false,
                    prepareError: null,
                    saveError: null,
                },
            });

            // Expand a field-bound segment to find the notes textarea
            await wrapper.find('[data-test="run-of-show-field-prep-toggle"]').trigger('click');
            const textarea = wrapper.find('[data-test="run-of-show-purpose"]');
            expect(textarea.exists()).toBe(true);

            const el = textarea.element as HTMLTextAreaElement;

            // Wait a tick for setTimeout in directive to run
            await new Promise(resolve => setTimeout(resolve, 0));

            // Set scrollHeight manually since jsdom has default 0 scrollHeight
            Object.defineProperty(el, 'scrollHeight', {
                value: 120,
                configurable: true,
            });

            // Trigger input event
            el.dispatchEvent(new Event('input'));

            // Check that element height reflects scrollHeight
            expect(el.style.height).toBe('120px');
        });

        it('T055 - recomputes height after a programmatic value change (regenerate/reset)', async () => {
            const wrapper = mount(FacilitationPlanPanel, {
                props: {
                    brief: facilitationBriefFixture(),
                    canPrepare: true,
                    preparing: false,
                    saving: false,
                    prepareError: null,
                    saveError: null,
                },
            });

            // The general (framing) segment textarea stays mounted across a
            // reset, so it is where a stale height would survive.
            const textarea = wrapper.find('[data-test="run-of-show-purpose"]');
            const el = textarea.element as HTMLTextAreaElement;

            // Flush the mount-time setTimeout(resize).
            await new Promise((resolve) => setTimeout(resolve, 0));

            // A regenerate/discard replaces the value programmatically (no input
            // event). Stand in for the taller content the new value would have.
            Object.defineProperty(el, 'scrollHeight', {
                value: 256,
                configurable: true,
            });

            await wrapper.setProps({
                brief: facilitationBriefFixture({
                    revision: 2,
                    document: {
                        ...facilitationBriefFixture().document,
                        run_of_show: [
                            {
                                ...facilitationBriefFixture().document
                                    .run_of_show[0],
                                purpose: {
                                    text: 'A much longer regenerated framing note that would wrap across several lines in the textarea.',
                                    provenance: 'system_suggested',
                                    source_item_ids: [],
                                    citation_ids: [],
                                },
                            },
                            facilitationBriefFixture().document.run_of_show[1],
                        ],
                    },
                }),
            });

            // The `updated` hook recomputes height to match the new content.
            expect(el.style.height).toBe('256px');
        });
    });

    describe('T056 - Human Field-Key Badge Labels', () => {
        it('renders the human field label in the segment badge, not the raw machine key', () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });

            const badges = wrapper.findAll(
                '[data-test="run-of-show-field-badge"]',
            );
            expect(badges.length).toBeGreaterThan(0);

            const texts = badges.map((badge) => badge.text());
            expect(texts).toContain('Overhead Handling');
            // The raw machine key must never leak into the operator UI.
            expect(texts).not.toContain('overhead_handling');
        });

        it('drops the badge when no human label is known instead of leaking the key', () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            // Remove the role that supplies the human label, leaving the bound
            // field with no resolvable label.
            props.metric.field_roles = props.metric.field_roles.filter(
                (role) => role.key !== 'overhead_handling',
            );

            const wrapper = mount(WorkshopPrepPage, { props });

            const texts = wrapper
                .findAll('[data-test="run-of-show-field-badge"]')
                .map((badge) => badge.text());
            expect(texts).not.toContain('overhead_handling');
        });
    });

    describe('T054 - Quiet Pointer To Off-Agenda Context Fields', () => {
        it('points to the metric overview for fields with no run-of-show segment', () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({
                    facilitation_brief: facilitationBriefFixture(),
                }),
            });

            const hint = wrapper.find('[data-test="off-agenda-fields-hint"]');
            const text = hint.text().replace(/\s+/g, ' ').trim();

            expect(hint.exists()).toBe(true);
            // The fixture binds overhead_handling to a segment; missing_field is
            // not on the agenda, so exactly one field points to the overview.
            expect(text).toContain(
                '1 more field is reviewed on the Metric overview',
            );
            expect(hint.find('a').attributes('data-href')).toBe(
                '/organizations/org-123/metrics/metric-789',
            );
        });

        it('pluralizes the pointer when several fields have no segment', () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            // A context field never gets a run-of-show segment, so both it and
            // missing_field are off the agenda.
            props.metric.field_roles.push(
                createFieldRole({
                    key: 'focus_window_preference',
                    label: 'Focus Window Preference',
                    role: 'context',
                    workshop_prompt: null,
                    workshop_guidance: null,
                    alignment_signal: null,
                    values: [],
                }),
            );

            const wrapper = mount(WorkshopPrepPage, { props });
            const text = wrapper
                .find('[data-test="off-agenda-fields-hint"]')
                .text()
                .replace(/\s+/g, ' ')
                .trim();

            expect(text).toContain(
                '2 more fields are reviewed on the Metric overview',
            );
        });

        it('renders no pointer when every field has a run-of-show segment', () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            // Keep only the field the fixture binds to a segment.
            props.metric.field_roles = [createFieldRole()];

            const wrapper = mount(WorkshopPrepPage, { props });

            expect(
                wrapper.find('[data-test="off-agenda-fields-hint"]').exists(),
            ).toBe(false);
        });

        it('renders no pointer before a run of show exists', () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: createPageProps({ facilitation_brief: null }),
            });

            expect(
                wrapper.find('[data-test="off-agenda-fields-hint"]').exists(),
            ).toBe(false);
        });
    });

    describe('T057 - Off-Agenda Count Counts Only Unrepresented Fields', () => {
        it('excludes companion and cue matrix-context fields already shown on the page', () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            // overhead_handling is segmented; overhead_basis is a companion of
            // it (shown under its primary); cac_pain_points is consumed by the
            // default cue context's matrix_context. Only focus_window_preference
            // is represented nowhere on the prep surface.
            props.metric.field_roles = [
                createFieldRole(),
                createFieldRole({
                    key: 'overhead_basis',
                    label: 'Overhead Basis',
                    companion_of: 'overhead_handling',
                }),
                createFieldRole({
                    key: 'cac_pain_points',
                    label: 'CAC pain points',
                    role: 'diagnostic',
                }),
                createFieldRole({
                    key: 'focus_window_preference',
                    label: 'Focus Window Preference',
                    role: 'context',
                }),
            ];

            const wrapper = mount(WorkshopPrepPage, { props });
            const text = wrapper
                .find('[data-test="off-agenda-fields-hint"]')
                .text()
                .replace(/\s+/g, ' ')
                .trim();

            expect(text).toContain(
                '1 more field is reviewed on the Metric overview',
            );
        });

        it('renders no pointer when every field is represented somewhere on the page', () => {
            const props = createPageProps({
                facilitation_brief: facilitationBriefFixture(),
            });
            // Every field is on the surface: a segmented primary, its companion,
            // and a matrix-context field consumed by the cue.
            props.metric.field_roles = [
                createFieldRole(),
                createFieldRole({
                    key: 'overhead_basis',
                    label: 'Overhead Basis',
                    companion_of: 'overhead_handling',
                }),
                createFieldRole({
                    key: 'cac_pain_points',
                    label: 'CAC pain points',
                    role: 'diagnostic',
                }),
            ];

            const wrapper = mount(WorkshopPrepPage, { props });

            expect(
                wrapper.find('[data-test="off-agenda-fields-hint"]').exists(),
            ).toBe(false);
        });
    });

    describe('T058 - De-Duplicate The Cue Context Used Disclosure', () => {
        const cuePrep = (cueOverrides: Partial<WorkshopCue> = {}): PageProps =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    cue_contexts: [cueContextFixture()],
                    workshop_cues: [
                        workshopCueFixture('overhead_handling', cueOverrides),
                    ],
                }),
            });

        it('drops the Submitted Answers source group but keeps detector signal and field guidance', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: cuePrep({
                    source_refs: [
                        {
                            id: 'submitted_answer:def-1:overhead_handling',
                            type: 'submitted_answer',
                            label: 'Alice Finance answer',
                            field_key: 'overhead_handling',
                            source_id: 'def-1',
                            excerpt: 'Head Count Ratio',
                        },
                        {
                            id: 'alignment_signal:sig-1:overhead_handling',
                            type: 'alignment_signal',
                            label: 'Detector signal',
                            field_key: 'overhead_handling',
                            source_id: 'sig-1',
                            excerpt: 'Finance and Marketing diverge.',
                        },
                        {
                            id: 'field_guidance:overhead_handling:guidance',
                            type: 'field_guidance',
                            label: 'Workshop guidance',
                            field_key: 'overhead_handling',
                            source_id: null,
                            excerpt: 'Drive consensus on the allocation basis.',
                        },
                        {
                            id: 'field_guidance:other_field_guidance',
                            type: 'field_guidance',
                            label: 'Other field guidance',
                            field_key: 'overhead_handling',
                            source_id: null,
                            excerpt: 'Some other field guidance.',
                        },
                    ],
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            await cues
                .find('[data-test="workshop-cue-context-toggle"]')
                .trigger('click');

            // The submitted answers already live in the segment's left column, so
            // the cue disclosure must not re-list them (T058/F2)...
            expect(
                cues.find('[data-test="group-submitted_answer"]').exists(),
            ).toBe(false);
            // ...while the context the facilitator cannot see on the left stays.
            expect(
                cues.find('[data-test="group-alignment_signal"]').exists(),
            ).toBe(true);
            expect(
                cues.find('[data-test="group-field_guidance"]').exists(),
            ).toBe(true);

            // Workshop guidance is filtered out (T061)
            expect(cues.text()).not.toContain('Drive consensus on the allocation basis.');
            // Other field guidance is preserved
            expect(cues.text()).toContain('Some other field guidance.');
        });

        it('counts the labels plus the retained refs so the number does not understate the disclosure', async () => {
            const wrapper = mount(WorkshopPrepPage, {
                props: cuePrep({
                    source_refs: [
                        // Three submitted answers that get dropped...
                        {
                            id: 'submitted_answer:def-1:overhead_handling',
                            type: 'submitted_answer',
                            label: 'Alice Finance answer',
                            field_key: 'overhead_handling',
                            source_id: 'def-1',
                            excerpt: 'Head Count Ratio',
                        },
                        {
                            id: 'submitted_answer:def-2:overhead_handling',
                            type: 'submitted_answer',
                            label: 'Bob Marketing answer',
                            field_key: 'overhead_handling',
                            source_id: 'def-2',
                            excerpt: 'Revenue Ratio',
                        },
                        {
                            id: 'submitted_answer:def-1:cac_pain_points',
                            type: 'submitted_answer',
                            label: 'Alice Finance answer',
                            field_key: 'cac_pain_points',
                            source_id: 'def-1',
                            excerpt: 'Finance needs a board baseline.',
                        },
                        // ...and two refs the facilitator cannot see on the left.
                        {
                            id: 'alignment_signal:sig-1:overhead_handling',
                            type: 'alignment_signal',
                            label: 'Detector signal',
                            field_key: 'overhead_handling',
                            source_id: 'sig-1',
                            excerpt: 'Finance and Marketing diverge.',
                        },
                        {
                            id: 'field_guidance:overhead_handling:guidance',
                            type: 'field_guidance',
                            label: 'Workshop guidance',
                            field_key: 'overhead_handling',
                            source_id: null,
                            excerpt: 'Drive consensus on the allocation basis.',
                        },
                    ],
                }),
            });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);
            const cues = fieldPrep.find(
                '[data-test="run-of-show-workshop-cues"]',
            );

            // cueContextFixture supplies CAC/CPL divergence points (Workshop guidance
            // is filtered out for de-duplication); the retained refs add detector signal
            // (Workshop guidance ref is also filtered out), so the count is 2.
            const toggle = cues.find(
                '[data-test="workshop-cue-context-toggle"]',
            );
            expect(toggle.text()).toContain('(2)');
        });
    });

    describe('T063 - Gate Workshop Cues When The Remaining Cue Is Too Thin', () => {
        const thinCuePrep = (
            cueOverrides: Partial<WorkshopCue> = {},
        ): PageProps =>
            createPageProps({
                facilitation_brief: facilitationBriefFixture(),
                metric_alignment_brief: mockBrief({
                    cue_contexts: [cueContextFixture()],
                    workshop_cues: [
                        workshopCueFixture('overhead_handling', {
                            probe_cue: null,
                            risk_cue: null,
                            ...cueOverrides,
                        }),
                    ],
                }),
            });

        it('suppresses the entire Workshop Cues panel when only one substantive section remains', async () => {
            const props = thinCuePrep({
                why_this_cue: {
                    text: 'The overhead choice is a key lever for board baseline definition.',
                    draft_text:
                        'The overhead choice is a key lever for board baseline definition.',
                    provenance: 'local_fixture',
                    source_refs: [],
                    citation_ids: [],
                },
            });
            const wrapper = mount(WorkshopPrepPage, { props });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            expect(fieldPrep.find('[data-test="run-of-show-workshop-cues"]').exists()).toBe(false);
        });

        it('renders the Workshop Cues panel when at least two substantive sections remain', async () => {
            const props = thinCuePrep({
                why_this_cue: {
                    text: 'The overhead choice is a key lever for board baseline definition.',
                    draft_text:
                        'The overhead choice is a key lever for board baseline definition.',
                    provenance: 'local_fixture',
                    source_refs: [],
                    citation_ids: [],
                },
                probe_cue: {
                    text: 'Which downstream report changes the most if overhead shifts to revenue ratio?',
                    draft_text:
                        'Which downstream report changes the most if overhead shifts to revenue ratio?',
                    provenance: 'local_fixture',
                    source_refs: [],
                    citation_ids: [],
                },
            });
            const wrapper = mount(WorkshopPrepPage, { props });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            expect(fieldPrep.find('[data-test="run-of-show-workshop-cues"]').exists()).toBe(true);
        });
    });

    describe('T064 - Expanded Segment Uses A Single Side-By-Side Layout', () => {
        // The earlier thin/dense split (centered max-w-5xl + asymmetric columns)
        // inset the expanded thin segment relative to its full-width header and
        // read as a detached, floating block. The surface now uses one balanced
        // side-by-side layout for every expanded segment, thin or dense.
        it('uses a balanced lg:grid-cols-2 layout for every expanded segment', async () => {
            const props = createPageProps({
                metric_alignment_brief: focusTimeBrief(),
                facilitation_brief: focusTimeFacilitationBrief(),
                metric: {
                    id: 'focus-time-metric',
                    name: 'Focus Time Alignment',
                    status: 'in_review',
                    key: 'focus-time-alignment',
                    field_roles: [
                        {
                            key: 'focus_hours_target',
                            label: 'Ideal focus hours per workday',
                            role: 'definition',
                            required: true,
                        },
                    ],
                    unclassified: [],
                },
            });

            const wrapper = mount(WorkshopPrepPage, { props });
            const fieldPrep = await openIntegratedFieldPrep(wrapper);

            const grid = fieldPrep.find('div');
            expect(grid.exists()).toBe(true);
            expect(grid.classes()).toContain('lg:grid-cols-2');
            expect(grid.classes()).toContain('lg:items-start');
            // No thin/dense special-casing remains.
            expect(grid.classes()).not.toContain('max-w-5xl');
            expect(grid.classes()).not.toContain(
                'lg:grid-cols-[minmax(0,1.2fr)_minmax(19rem,0.8fr)]',
            );
            expect(grid.attributes('data-layout')).toBeUndefined();
        });
    });

});

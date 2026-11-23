import { computed, ref, watch } from 'vue';

import {
    buildMrrMetric,
    MRR_SNAPSHOT_OPTIONS,
    SEEDED_PDF_MRR_CHOICE,
    STATIC_METRICS,
    type DemoMetric,
    type MetricKey,
    type MrrSnapshotChoice,
} from '../data/goldenPath';

export type DemoStep = 'workshop' | 'workbench' | 'handbook';

export type HandbookStatusFilter =
    | 'all'
    | 'needs_attention'
    | 'in_progress'
    | 'finalized';

interface PersistedDemoState {
    step: DemoStep;
    mrrChoice: MrrSnapshotChoice | null;
    mrrApproved: boolean;
    mrrApprovedAt: string | null;
    workbenchMetricKey: MetricKey;
    handbookMetricKey: MetricKey;
}

const STORAGE_KEY = 'metric-harmony-static-demo-v1';

const DEFAULT_STATE: PersistedDemoState = {
    step: 'workshop',
    mrrChoice: null,
    mrrApproved: false,
    mrrApprovedAt: null,
    workbenchMetricKey: 'golden-mrr',
    handbookMetricKey: 'golden-mrr',
};

function loadInitialState(): PersistedDemoState {
    if (typeof window === 'undefined') {
        return { ...DEFAULT_STATE };
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return { ...DEFAULT_STATE };
        }
        const parsed = JSON.parse(raw) as Partial<PersistedDemoState>;
        const validSteps: DemoStep[] = ['workshop', 'workbench', 'handbook'];
        const validChoices: Array<MrrSnapshotChoice | null> = [
            'last_calendar_day',
            'rolling_daily',
            null,
        ];
        const validMetrics: MetricKey[] = [
            'golden-mrr',
            'golden-gross-churn',
            'golden-cac-cpl',
        ];

        return {
            step: validSteps.includes(parsed.step as DemoStep)
                ? (parsed.step as DemoStep)
                : DEFAULT_STATE.step,
            mrrChoice: validChoices.includes(
                (parsed.mrrChoice ?? null) as MrrSnapshotChoice | null,
            )
                ? ((parsed.mrrChoice ?? null) as MrrSnapshotChoice | null)
                : DEFAULT_STATE.mrrChoice,
            mrrApproved: Boolean(parsed.mrrApproved && parsed.mrrChoice),
            mrrApprovedAt:
                typeof parsed.mrrApprovedAt === 'string'
                    ? parsed.mrrApprovedAt
                    : null,
            workbenchMetricKey: validMetrics.includes(
                parsed.workbenchMetricKey as MetricKey,
            )
                ? (parsed.workbenchMetricKey as MetricKey)
                : DEFAULT_STATE.workbenchMetricKey,
            handbookMetricKey: validMetrics.includes(
                parsed.handbookMetricKey as MetricKey,
            )
                ? (parsed.handbookMetricKey as MetricKey)
                : DEFAULT_STATE.handbookMetricKey,
        };
    } catch {
        return { ...DEFAULT_STATE };
    }
}

const initial = loadInitialState();

const step = ref<DemoStep>(initial.step);
const mrrChoice = ref<MrrSnapshotChoice | null>(initial.mrrChoice);
const mrrApproved = ref<boolean>(initial.mrrApproved);
const mrrApprovedAt = ref<string | null>(initial.mrrApprovedAt);
const workbenchMetricKey = ref<MetricKey>(initial.workbenchMetricKey);
const handbookMetricKey = ref<MetricKey>(initial.handbookMetricKey);
const handbookStatusFilter = ref<HandbookStatusFilter>('all');

watch(
    [
        step,
        mrrChoice,
        mrrApproved,
        mrrApprovedAt,
        workbenchMetricKey,
        handbookMetricKey,
    ],
    () => {
        if (typeof window === 'undefined') {
            return;
        }
        const payload: PersistedDemoState = {
            step: step.value,
            mrrChoice: mrrChoice.value,
            mrrApproved: mrrApproved.value,
            mrrApprovedAt: mrrApprovedAt.value,
            workbenchMetricKey: workbenchMetricKey.value,
            handbookMetricKey: handbookMetricKey.value,
        };
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch {
            // Ignore storage quota / private mode errors
        }
    },
    { deep: false },
);

export function useDemoState() {
    const mrrMetric = computed<DemoMetric>(() =>
        buildMrrMetric(mrrChoice.value, mrrApproved.value, mrrApprovedAt.value),
    );

    const metrics = computed<DemoMetric[]>(() => [
        mrrMetric.value,
        STATIC_METRICS['golden-gross-churn'],
        STATIC_METRICS['golden-cac-cpl'],
    ]);

    const readyMetricsCount = computed(
        () => metrics.value.filter((metric) => metric.isSignedOff).length,
    );

    const operationalReadyCount = computed(
        () =>
            metrics.value.filter(
                (metric) =>
                    metric.isSignedOff &&
                    metric.operationalStatus === 'operationally_ready',
            ).length,
    );

    const needsFollowUpCount = computed(
        () =>
            metrics.value.filter(
                (metric) =>
                    metric.isSignedOff &&
                    metric.operationalStatus === 'needs_follow_up',
            ).length,
    );

    const implementationGapCount = computed(
        () =>
            metrics.value.filter(
                (metric) =>
                    metric.isSignedOff && metric.implementationGaps !== null,
            ).length,
    );

    const selectedMrrOption = computed(() =>
        mrrChoice.value ? MRR_SNAPSHOT_OPTIONS[mrrChoice.value] : null,
    );

    const pdfInfo = computed(() => {
        const base = import.meta.env.BASE_URL || './';
        const url = `${base.endsWith('/') ? base : `${base}/`}metric-handbook.pdf`;
        const matchesBundledPdf =
            mrrApproved.value && mrrChoice.value === SEEDED_PDF_MRR_CHOICE;
        const isSample = !matchesBundledPdf;

        let reason = '';
        if (!mrrApproved.value) {
            reason =
                'This bundled PDF is a sample rendered from the seeded month-end close baseline. Approve MRR in Handbook Workbench to finalize your chosen definition.';
        } else if (mrrChoice.value !== SEEDED_PDF_MRR_CHOICE) {
            reason =
                'This bundled PDF is labeled Sample PDF because it was rendered from the seeded month-end close MRR definition, whereas you approved the rolling daily view.';
        } else {
            reason =
                'Rendered from the seeded month-end close baseline matching your approved MRR definition.';
        }

        return {
            url,
            isSample,
            matchesBundledPdf,
            buttonLabel: isSample
                ? 'Download Sample PDF'
                : 'Download Handbook PDF',
            badgeLabel: isSample ? 'Sample PDF' : 'Matches approved baseline',
            reason,
        };
    });

    function selectMrrChoice(choice: MrrSnapshotChoice) {
        if (mrrChoice.value !== choice) {
            mrrChoice.value = choice;
            mrrApproved.value = false;
            mrrApprovedAt.value = null;
        }
    }

    function goToStep(nextStep: DemoStep) {
        step.value = nextStep;
        if (nextStep === 'workbench') {
            workbenchMetricKey.value = 'golden-mrr';
        }
        if (nextStep === 'handbook') {
            handbookMetricKey.value = 'golden-mrr';
        }
        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function approveMrrInWorkbench() {
        if (!mrrChoice.value) {
            mrrChoice.value = SEEDED_PDF_MRR_CHOICE;
        }
        mrrApproved.value = true;
        mrrApprovedAt.value = 'May 14, 2026';
    }

    function startOver() {
        step.value = 'workshop';
        mrrChoice.value = null;
        mrrApproved.value = false;
        mrrApprovedAt.value = null;
        workbenchMetricKey.value = 'golden-mrr';
        handbookMetricKey.value = 'golden-mrr';
        handbookStatusFilter.value = 'all';

        if (typeof window !== 'undefined') {
            try {
                window.localStorage.removeItem(STORAGE_KEY);
            } catch {
                // Ignore storage errors
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    return {
        step,
        mrrChoice,
        mrrApproved,
        mrrApprovedAt,
        workbenchMetricKey,
        handbookMetricKey,
        handbookStatusFilter,
        mrrMetric,
        metrics,
        readyMetricsCount,
        operationalReadyCount,
        needsFollowUpCount,
        implementationGapCount,
        selectedMrrOption,
        pdfInfo,
        selectMrrChoice,
        goToStep,
        approveMrrInWorkbench,
        startOver,
    };
}

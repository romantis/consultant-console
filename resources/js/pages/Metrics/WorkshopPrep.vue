<script setup lang="ts">
import MetricWorkshopPrepController, {
    storeFacilitation as storeMetricFacilitationBrief,
    updateFacilitation as updateMetricFacilitationBrief,
} from '@/actions/App/Http/Controllers/MetricWorkshopPrepController';
import FacilitationPlanPanel from '@/components/metrics/FacilitationPlanPanel.vue';
import FacilitatorReadinessPackPanel from '@/components/metrics/FacilitatorReadinessPackPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { useFieldBriefContext } from '@/composables/useFieldBriefContext';
import AppShell from '@/layouts/AppShell.vue';
import { resolveCsrfHeaders } from '@/lib/csrf';
import org from '@/routes/org';
import {
    index as orgEngagementsIndex,
    show as orgEngagementsShow,
} from '@/routes/org/engagements';
import { show as orgMetricShow } from '@/routes/org/metrics';
import type {
    BreadcrumbItemType,
    FacilitationBrief,
    FacilitationRunOfShowItem,
    FacilitatorReadinessPack,
    FieldRole,
    MetricAlignmentBrief,
} from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, RefreshCw } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        organization: {
            id: string;
            name: string;
        };
        engagement: {
            id: string;
            title: string;
            status: string;
        };
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
        // Private operator-only preparation briefing (spec 037). Optional so the
        // page stays resilient if the prop is ever absent; rendered in Task 5.
        facilitator_readiness_pack?: FacilitatorReadinessPack | null;
        engagement_metrics: Array<{
            id: string;
            name: string;
            status: string;
        }>;
    }>(),
    {
        fieldValueLabels: () => ({}),
        facilitation_brief: null,
        facilitator_readiness_pack: null,
    },
);

// Helpers

const resolveRouteUrl = (route: any): string => {
    if (!route) return '#';
    if (typeof route === 'string') return route;
    if (typeof route === 'object' && 'url' in route) return route.url;
    return '#';
};

const switchMetric = (metricId: any): void => {
    if (typeof metricId === 'string') {
        router.visit(
            MetricWorkshopPrepController.url({
                organization: props.organization.id,
                engagement: props.engagement.id,
                metric: metricId,
            }),
        );
    }
};

// Navigation
const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: 'Organizations', href: resolveRouteUrl(org.index()) },
    {
        title: props.organization.name,
        href: resolveRouteUrl(org.show(props.organization.id)),
    },
    {
        title: 'Engagements',
        href: resolveRouteUrl(orgEngagementsIndex(props.organization.id)),
    },
    {
        title: props.engagement.title,
        href: resolveRouteUrl(
            orgEngagementsShow([props.organization.id, props.engagement.id]),
        ),
    },
    {
        title: props.metric.name,
        href: resolveRouteUrl(
            orgMetricShow([props.organization.id, props.metric.id]),
        ),
    },
    { title: 'Workshop Prep', href: '' },
]);

const metricHref = computed(() =>
    resolveRouteUrl(orgMetricShow([props.organization.id, props.metric.id])),
);

const facilitationPanelRef = ref<InstanceType<
    typeof FacilitationPlanPanel
> | null>(null);
const isDirty = computed(() => facilitationPanelRef.value?.isDirty ?? false);

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (isDirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

let unsubscribeRouter: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('hashchange', handleHashChange);

    unsubscribeRouter = router.on('before', (event) => {
        if (event.detail.visit.prefetch) {
            return;
        }

        if (isDirty.value) {
            if (
                !confirm(
                    'You have unsaved changes. Are you sure you want to leave this page?',
                )
            ) {
                event.preventDefault();
            }
        }
    });

    void applyFieldEvidenceHash(window.location.hash);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    window.removeEventListener('hashchange', handleHashChange);
    clearPulse();
    if (unsubscribeRouter) {
        unsubscribeRouter();
    }
});

// Facilitation plan (run of show + expected outputs)
const facilitationBrief = ref<FacilitationBrief | null>(
    props.facilitation_brief,
);
const isPreparingFacilitation = ref(false);
const facilitationPrepareError = ref<string | null>(null);
const isSavingFacilitation = ref(false);
const facilitationSaveError = ref<string | null>(null);

// The plan is derived deterministically from the canonical agenda, so it no
// longer requires a generated draft — only at least one conflict, missing
// evidence, or decision to confirm.
const canPrepareFacilitation = computed<boolean>(
    () =>
        props.metric_alignment_brief.material_conflicts.length +
            props.metric_alignment_brief.missing_evidence.length +
            props.metric_alignment_brief.decision_needed.length >
        0,
);

const storeFacilitationRoute = computed(() =>
    storeMetricFacilitationBrief([
        props.organization.id,
        props.engagement.id,
        props.metric.id,
    ]),
);

const updateFacilitationRoute = computed(() =>
    updateMetricFacilitationBrief([
        props.organization.id,
        props.engagement.id,
        props.metric.id,
    ]),
);

const prepareFacilitationPlan = async (options?: { regenerate?: boolean }) => {
    if (isPreparingFacilitation.value) {
        return;
    }

    const regenerate =
        options?.regenerate === true && !!facilitationBrief.value;
    const fallbackError = regenerate
        ? 'Unable to regenerate the run of show right now.'
        : 'Unable to prepare the run of show right now.';

    isPreparingFacilitation.value = true;
    facilitationPrepareError.value = null;

    try {
        const response = await fetch(storeFacilitationRoute.value.url, {
            method: storeFacilitationRoute.value.method.toUpperCase(),
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...resolveCsrfHeaders(),
            },
            credentials: 'same-origin',
            body: regenerate
                ? JSON.stringify({
                      regenerate: true,
                      revision: facilitationBrief.value!.revision,
                  })
                : undefined,
        });
        const payload = (await response.json()) as {
            data?: FacilitationBrief;
            error?: { message?: string };
        };

        if (!response.ok || !payload.data) {
            throw new Error(payload.error?.message ?? fallbackError);
        }

        facilitationBrief.value = payload.data;
    } catch (error) {
        facilitationPrepareError.value =
            error instanceof Error ? error.message : fallbackError;
    } finally {
        isPreparingFacilitation.value = false;
    }
};

const saveFacilitationPlan = async (plan: {
    revision: number;
    status: 'draft' | 'ready_for_workshop';
    run_of_show: FacilitationRunOfShowItem[];
}) => {
    if (isSavingFacilitation.value) {
        return;
    }

    isSavingFacilitation.value = true;
    facilitationSaveError.value = null;

    try {
        const response = await fetch(updateFacilitationRoute.value.url, {
            method: updateFacilitationRoute.value.method.toUpperCase(),
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...resolveCsrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(plan),
        });
        const payload = (await response.json()) as {
            data?: FacilitationBrief;
            error?: { message?: string };
        };

        if (!response.ok || !payload.data) {
            throw new Error(
                payload.error?.message ??
                    'Unable to save the facilitation plan right now.',
            );
        }

        facilitationBrief.value = payload.data;
    } catch (error) {
        facilitationSaveError.value =
            error instanceof Error
                ? error.message
                : 'Unable to save the facilitation plan right now.';
    } finally {
        isSavingFacilitation.value = false;
    }
};

// Field briefly highlighted after an anchor scroll, so a freshly-opened field is
// easy to spot. The pulse now lands on the integrated run-of-show segment that
// prepares the field. Cleared once the pulse has run.
const pulsingFieldKey = ref<string | null>(null);
let pulseTimeoutId: ReturnType<typeof setTimeout> | null = null;

const { briefContextByKey: fieldBriefContextByKey, fieldAttentionTierByKey } =
    useFieldBriefContext(
        () => props.metric.field_roles,
        () => props.metric_alignment_brief,
    );

// T054: run-of-show segments are field-bound only for conflict/decision/missing
// fields, so context/diagnostic fields (and any fully-agreed field) get no
// segment on this page once the parallel field surfaces were retired
// (T028/T030). They stay fully reviewable on the linked Metric overview, so a
// single quiet pointer keeps them discoverable. The count is derived from the
// live brief (run_of_show -> decision -> field_keys) plus the companion and
// cue matrix-context fields already shown on the page (T057/F1), so no backend
// prop is needed; it stays 0 until a run of show exists or when every field is
// already represented somewhere on the prep surface.
const offAgendaFieldCount = computed<number>(() => {
    const brief = facilitationBrief.value;

    if (!brief) {
        return 0;
    }

    const fieldKeysByDecisionId = new Map<string, string[]>(
        brief.document.decisions_to_lock.map((decision) => [
            decision.id,
            decision.field_keys ?? [],
        ]),
    );

    const segmentedFieldKeys = new Set<string>();
    brief.document.run_of_show.forEach((segment) => {
        (segment.decision_ids ?? []).forEach((decisionId) => {
            (fieldKeysByDecisionId.get(decisionId) ?? []).forEach(
                (fieldKey) => {
                    if (fieldKey !== '') {
                        segmentedFieldKeys.add(fieldKey);
                    }
                },
            );
        });
    });

    // T057/F1: companion answers (nested under a segmented primary) and
    // matrix-context fields (consumed by a cue's context) are already on the
    // prep surface. Counting them as off-agenda would send facilitators to the
    // Metric overview for evidence already in front of them, so we exclude both
    // from the "reviewed elsewhere" pointer.
    const matrixContextFieldKeys = new Set<string>();
    props.metric_alignment_brief.cue_contexts?.forEach((cueContext) => {
        cueContext.matrix_context.forEach((field) => {
            matrixContextFieldKeys.add(field.field_key);
        });
    });

    return props.metric.field_roles.filter((field) => {
        if (segmentedFieldKeys.has(field.key)) {
            return false;
        }

        if (
            field.companion_of !== null &&
            segmentedFieldKeys.has(field.companion_of)
        ) {
            return false;
        }

        return !matrixContextFieldKeys.has(field.key);
    }).length;
});

const fieldKeyFromEvidenceHash = (hash: string): string | null => {
    const prefix = '#field-detail-';

    if (!hash.startsWith(prefix)) {
        return null;
    }

    try {
        return decodeURIComponent(hash.slice(prefix.length));
    } catch {
        return null;
    }
};

const applyFieldEvidenceHash = async (hash: string): Promise<boolean> => {
    const fieldKey = fieldKeyFromEvidenceHash(hash);

    if (!fieldKey) {
        return false;
    }

    const fieldExists = props.metric.field_roles.some(
        (field) => field.key === fieldKey,
    );

    if (!fieldExists) {
        return false;
    }

    // Reset any in-flight pulse first so re-targeting the same field restarts the
    // highlight animation rather than leaving the class already applied. Setting
    // the key drives the integrated segment to expand and highlight (T028).
    clearPulse();
    pulsingFieldKey.value = null;

    await nextTick();

    document
        .getElementById(`field-detail-${fieldKey}`)
        ?.scrollIntoView({ block: 'start' });

    pulsingFieldKey.value = fieldKey;
    pulseTimeoutId = setTimeout(() => {
        if (pulsingFieldKey.value === fieldKey) {
            pulsingFieldKey.value = null;
        }
        pulseTimeoutId = null;
    }, 1600);

    return true;
};

const clearPulse = (): void => {
    if (pulseTimeoutId !== null) {
        clearTimeout(pulseTimeoutId);
        pulseTimeoutId = null;
    }
};

const handleHashChange = (): void => {
    void applyFieldEvidenceHash(window.location.hash);
};
</script>

<template>
    <Head :title="`${metric.name} • Workshop Preparation`" />

    <AppShell
        :title="metric.name"
        description="Establish clarity and prep alignment before entering the live consensus workshop."
        :breadcrumbs="breadcrumbs"
    >
        <template #title>
            <div class="flex items-center gap-2">
                <Select
                    :model-value="metric.id"
                    @update:model-value="switchMetric"
                >
                    <SelectTrigger
                        data-test="metric-title-selector"
                        class="h-auto w-auto max-w-full border-0 bg-transparent p-0 text-left text-2xl font-semibold tracking-tight text-foreground shadow-none hover:bg-transparent focus:ring-0"
                    >
                        <span class="truncate">{{ metric.name }}</span>
                    </SelectTrigger>
                    <SelectContent class="w-80">
                        <SelectItem
                            v-for="item in engagement_metrics"
                            :key="item.id"
                            :value="item.id"
                            :text-value="item.name"
                            data-test="metric-title-selector-option"
                        >
                            <div
                                class="flex w-full items-center justify-between gap-4"
                            >
                                <span
                                    class="truncate font-medium text-foreground"
                                    >{{ item.name }}</span
                                >
                                <Badge
                                    data-test="metric-title-option-status-badge"
                                    variant="outline"
                                    class="shrink-0 text-[10px]"
                                >
                                    {{ item.status }}
                                </Badge>
                            </div>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <template #actions>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="metricHref">
                        <CheckCircle2 class="mr-1.5 size-4" />
                        Metric overview
                    </Link>
                </Button>
                <Dialog v-if="facilitationBrief">
                    <DialogTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="
                                isPreparingFacilitation || isSavingFacilitation
                            "
                            data-test="facilitation-regenerate"
                        >
                            <RefreshCw
                                class="mr-1.5 size-4"
                                :class="{
                                    'animate-spin': isPreparingFacilitation,
                                }"
                            />
                            {{
                                isPreparingFacilitation
                                    ? 'Regenerating...'
                                    : 'Regenerate'
                            }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent data-test="facilitation-regenerate-dialog">
                        <DialogHeader>
                            <DialogTitle>Regenerate run of show?</DialogTitle>
                            <DialogDescription>
                                This rebuilds the run of show from the current
                                decisions and discards your manual edits to this
                                plan. It cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="facilitation-regenerate-cancel"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    data-test="facilitation-regenerate-confirm"
                                    @click="
                                        prepareFacilitationPlan({
                                            regenerate: true,
                                        })
                                    "
                                >
                                    <RefreshCw class="mr-1.5 size-4" />
                                    Regenerate
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Private, operator-only decision script. Rendered above the Run of
            Show, which stays the primary editable surface. Hidden when there is
            nothing to prepare so no empty shell appears. -->
            <FacilitatorReadinessPackPanel
                v-if="
                    facilitator_readiness_pack &&
                    facilitator_readiness_pack.decisions.length > 0
                "
                :pack="facilitator_readiness_pack"
            />

            <FacilitationPlanPanel
                ref="facilitationPanelRef"
                :brief="facilitationBrief"
                :can-prepare="canPrepareFacilitation"
                :preparing="isPreparingFacilitation"
                :saving="isSavingFacilitation"
                :prepare-error="facilitationPrepareError"
                :save-error="facilitationSaveError"
                :field-roles="metric.field_roles"
                :brief-context-by-key="fieldBriefContextByKey"
                :field-attention-tier-by-key="fieldAttentionTierByKey"
                :pulse-field-key="pulsingFieldKey"
                :readiness-decisions="
                    facilitator_readiness_pack?.decisions ?? []
                "
                @prepare="prepareFacilitationPlan"
                @save="saveFacilitationPlan"
            />

            <p
                v-if="offAgendaFieldCount > 0"
                class="text-sm text-muted-foreground"
                data-test="off-agenda-fields-hint"
            >
                {{ offAgendaFieldCount }}
                {{
                    offAgendaFieldCount === 1
                        ? 'more field is'
                        : 'more fields are'
                }}
                reviewed on the
                <Link
                    :href="metricHref"
                    class="font-medium text-foreground underline-offset-4 hover:underline"
                    >Metric overview</Link
                >.
            </p>
        </div>
    </AppShell>
</template>

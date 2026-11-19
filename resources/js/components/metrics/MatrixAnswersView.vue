<script setup lang="ts">
import DownloadCsvButton from '@/components/ui/DownloadCsvButton.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { computed, ref } from 'vue';

type MatrixValue = {
    definition_id: string;
    raw: unknown;
    user: { id: string; name: string } | null;
};

type OptionLabels = Record<string, string>;

type MatrixRow = {
    field: string;
    label: string;
    values: MatrixValue[];
    options?: OptionLabels | null;
};

type ContextRow = Omit<MatrixRow, 'discrepancy'>;

type FieldRole = 'definition' | 'context' | 'governance' | 'diagnostic';

type FieldRoleEntry = {
    key: string;
    label: string;
    role?: FieldRole;
    exclude_from_consensus?: boolean;
    companion_of?: string | null;
};

type MetricAnswers = {
    matrix: MatrixRow[];
    context_fields?: ContextRow[];
    field_roles?: FieldRoleEntry[];
};

type CompareRow = {
    field: string;
    label: string;
    values: MatrixValue[];
    options?: OptionLabels | null;
    role: FieldRole | null;
    exclude_from_consensus: boolean;
};

type Contributor = {
    id: string;
    name: string;
};

type SelectedAnswerCell = {
    field: string;
    contributorId: string;
};

type MatrixAnswerDoubleClickPayload = {
    field: string;
    label: string;
    value: string;
};

const props = defineProps<{
    metric: MetricAnswers;
    finalDefinitionValues?: Record<string, unknown>;
    title?: string | null;
    description?: string | null;
    downloadFilename?: string | null;
}>();

const emit = defineEmits<{
    (
        event: 'definition-answer-dblclick',
        payload: MatrixAnswerDoubleClickPayload,
    ): void;
}>();

const headerTitle = computed(() => {
    const value = props.title;
    return typeof value === 'string' ? value.trim() : '';
});

const headerDescription = computed(() => {
    const value = props.description;
    return typeof value === 'string' ? value.trim() : '';
});

const showHeader = computed(
    () => headerTitle.value.length > 0 || headerDescription.value.length > 0,
);

const startCase = (value: string): string =>
    value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase()) || value;

const formatValue = (raw: unknown, options?: OptionLabels | null): string => {
    if (raw === null || raw === undefined) {
        return '—';
    }

    if (Array.isArray(raw)) {
        if (!raw.length) {
            return '—';
        }

        return raw.map((entry) => formatValue(entry, options)).join(', ');
    }

    if (typeof raw === 'boolean') {
        return raw ? 'Yes' : 'No';
    }

    if (typeof raw === 'number') {
        return Number.isInteger(raw) ? raw.toString() : raw.toFixed(2);
    }

    if (typeof raw === 'string') {
        if (options && options[raw]) {
            return options[raw];
        }

        if (/[_-]/.test(raw)) {
            return startCase(raw);
        }

        return raw;
    }

    return String(raw);
};

const compareRows = computed<CompareRow[]>(() => {
    const rowLookup = new Map<string, MatrixRow | ContextRow>();
    const fieldRoles = props.metric.field_roles ?? [];
    const contextRows = props.metric.context_fields ?? [];
    const matrixFields = new Set(props.metric.matrix.map((row) => row.field));
    const contextFields = new Set(contextRows.map((row) => row.field));

    props.metric.matrix.forEach((row) => rowLookup.set(row.field, row));
    contextRows.forEach((row) => {
        if (!rowLookup.has(row.field)) {
            rowLookup.set(row.field, row);
        }
    });

    if (fieldRoles.length > 0) {
        return fieldRoles.map((entry) => {
            const row = rowLookup.get(entry.key);
            return {
                field: entry.key,
                label: entry.label,
                values: row?.values ?? [],
                options: row?.options ?? null,
                role:
                    entry.role ??
                    (contextFields.has(entry.key) ? 'context' : 'definition'),
                exclude_from_consensus: entry.exclude_from_consensus === true,
            };
        });
    }

    return Array.from(rowLookup.values()).map((row) => ({
        field: row.field,
        label: row.label,
        values: row.values,
        options: row.options ?? null,
        role: matrixFields.has(row.field) ? 'definition' : 'context',
        exclude_from_consensus: false,
    }));
});

const contributors = computed<Contributor[]>(() => {
    const lookup = new Map<string, Contributor>();

    compareRows.value.forEach((row) => {
        row.values.forEach((value) => {
            const contributorId =
                value.user?.id ?? `unassigned:${value.definition_id}`;

            if (!lookup.has(contributorId)) {
                lookup.set(contributorId, {
                    id: contributorId,
                    name: value.user?.name ?? 'Unassigned',
                });
            }
        });
    });

    return Array.from(lookup.values()).sort((left, right) =>
        left.name.localeCompare(right.name),
    );
});

const valueForRowAndContributor = (
    row: CompareRow,
    contributorId: string,
): string => {
    const value = row.values.find((entry) => {
        const entryContributorId =
            entry.user?.id ?? `unassigned:${entry.definition_id}`;

        return entryContributorId === contributorId;
    });

    return value ? formatValue(value.raw, row.options) : '—';
};

const selectedAnswerCell = ref<SelectedAnswerCell | null>(null);

const isCapturableRow = (row: CompareRow): boolean =>
    !row.exclude_from_consensus;

const isContributorSelectedForRow = (
    row: CompareRow,
    contributorId: string,
): boolean => {
    if (!isCapturableRow(row)) {
        return false;
    }

    return (
        selectedAnswerCell.value?.field === row.field &&
        selectedAnswerCell.value.contributorId === contributorId
    );
};

const toggleContributorSelectionForRow = (
    row: CompareRow,
    contributorId: string,
): void => {
    if (!isCapturableRow(row)) {
        return;
    }

    selectedAnswerCell.value = {
        field: row.field,
        contributorId,
    };
};

const isSavedDefinitionMatchForContributor = (
    row: CompareRow,
    contributorId: string,
): boolean => {
    if (!isCapturableRow(row)) {
        return false;
    }

    const rawFinalDefinitionValue = props.finalDefinitionValues?.[row.field];

    if (
        rawFinalDefinitionValue === null ||
        rawFinalDefinitionValue === undefined
    ) {
        return false;
    }

    const contributorValue = valueForRowAndContributor(
        row,
        contributorId,
    ).trim();
    const finalDefinitionValue = formatValue(
        rawFinalDefinitionValue,
        row.options,
    ).trim();

    return contributorValue === finalDefinitionValue;
};

const emitDefinitionAnswerDoubleClick = (
    row: CompareRow,
    contributorId: string,
): void => {
    if (!isCapturableRow(row)) {
        return;
    }

    emit('definition-answer-dblclick', {
        field: row.field,
        label: row.label,
        value: valueForRowAndContributor(row, contributorId),
    });
};

const rowHasDifferences = (row: CompareRow): boolean => {
    if (contributors.value.length < 2) {
        return false;
    }

    const values = contributors.value.map((contributor) =>
        valueForRowAndContributor(row, contributor.id),
    );

    return new Set(values).size > 1;
};

const shouldShowDifferenceIndicator = (row: CompareRow): boolean =>
    isCapturableRow(row) && rowHasDifferences(row);

const csvHeaders = computed(() => [
    'Question',
    ...contributors.value.map((c) => c.name),
]);

const csvRows = computed(() =>
    compareRows.value.map((row) => [
        row.label,
        ...contributors.value.map((c) => valueForRowAndContributor(row, c.id)),
    ]),
);

const csvFilename = computed(() => {
    const dateStr = new Date().toISOString().split('T')[0];
    const titleSlug = headerTitle.value
        ? headerTitle.value.toLowerCase().replace(/[^a-z0-9]+/g, '-')
        : 'matrix-answers';

    const fallbackFilename = `${titleSlug}_matrix_${dateStr}`;
    return props.downloadFilename || fallbackFilename;
});
</script>

<template>
    <Card data-test="answers-view-compare">
        <CardHeader v-if="showHeader">
            <CardTitle v-if="headerTitle">{{ headerTitle }}</CardTitle>
            <CardDescription v-if="headerDescription">
                {{ headerDescription }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <p
                v-if="!compareRows.length"
                data-test="answers-view-compare-empty-fields"
                class="text-sm text-muted-foreground"
            >
                No fields to compare yet.
            </p>
            <p
                v-else-if="!contributors.length"
                data-test="answers-view-compare-empty-contributors"
                class="text-sm text-muted-foreground"
            >
                No contributor responses captured yet.
            </p>
            <div v-else class="relative">
                <div class="absolute top-0 right-0 z-10">
                    <DownloadCsvButton
                        :headers="csvHeaders"
                        :rows="csvRows"
                        :filename="csvFilename"
                        title="Export to CSV"
                    />
                </div>
                <div class="overflow-x-auto">
                    <table
                        data-test="answers-view-compare-table"
                        class="min-w-full border-collapse text-sm"
                    >
                        <thead>
                            <tr class="border-b border-border/70 bg-muted/30">
                                <th class="px-3 py-2 text-left font-semibold">
                                    Question
                                </th>
                                <th
                                    v-for="contributor in contributors"
                                    :key="contributor.id"
                                    class="px-3 py-2 text-left font-semibold"
                                >
                                    {{ contributor.name }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in compareRows"
                                :key="row.field"
                                data-test="answers-view-compare-row"
                                :data-different="rowHasDifferences(row)"
                                :data-role="row.role ?? 'unknown'"
                            >
                                <td
                                    :class="[
                                        'px-3 py-2 align-top',
                                        isCapturableRow(row)
                                            ? 'border-b-2 border-border/80 font-bold'
                                            : 'border-b border-border/50 font-normal text-muted-foreground',
                                    ]"
                                >
                                    <div class="flex items-start gap-2">
                                        <span
                                            v-if="
                                                shouldShowDifferenceIndicator(
                                                    row,
                                                )
                                            "
                                            data-test="answers-view-difference-indicator"
                                            class="mt-1 size-2 shrink-0 rounded-full bg-rose-400/80"
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 break-words">
                                            {{ row.label }}
                                        </span>
                                        <span
                                            v-if="
                                                shouldShowDifferenceIndicator(
                                                    row,
                                                )
                                            "
                                            class="sr-only"
                                        >
                                            Responses differ
                                        </span>
                                    </div>
                                </td>
                                <td
                                    v-for="contributor in contributors"
                                    :key="`${row.field}-${contributor.id}`"
                                    :data-selectable="
                                        isCapturableRow(row) ? 'true' : 'false'
                                    "
                                    :data-selected="
                                        isContributorSelectedForRow(
                                            row,
                                            contributor.id,
                                        )
                                            ? 'true'
                                            : 'false'
                                    "
                                    :data-saved-match="
                                        isSavedDefinitionMatchForContributor(
                                            row,
                                            contributor.id,
                                        )
                                            ? 'true'
                                            : 'false'
                                    "
                                    :class="[
                                        'px-3 py-2 align-top',
                                        isCapturableRow(row)
                                            ? 'border-b-2 border-border/80'
                                            : 'border-b border-border/50',
                                        isCapturableRow(row)
                                            ? 'cursor-pointer'
                                            : '',
                                        !isCapturableRow(row)
                                            ? 'text-muted-foreground'
                                            : '',
                                        isSavedDefinitionMatchForContributor(
                                            row,
                                            contributor.id,
                                        )
                                            ? 'border-emerald-200/70 bg-emerald-50/90 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-950/50 dark:text-emerald-50'
                                            : '',
                                        isContributorSelectedForRow(
                                            row,
                                            contributor.id,
                                        )
                                            ? 'border-amber-400 bg-amber-50/60 ring-2 ring-amber-400 ring-inset dark:border-amber-500/70 dark:bg-amber-950/40 dark:ring-amber-500/70'
                                            : '',
                                    ]"
                                    @click="
                                        toggleContributorSelectionForRow(
                                            row,
                                            contributor.id,
                                        )
                                    "
                                    @dblclick="
                                        emitDefinitionAnswerDoubleClick(
                                            row,
                                            contributor.id,
                                        )
                                    "
                                >
                                    <span
                                        class="block min-w-0 break-words whitespace-pre-wrap"
                                    >
                                        {{
                                            valueForRowAndContributor(
                                                row,
                                                contributor.id,
                                            )
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

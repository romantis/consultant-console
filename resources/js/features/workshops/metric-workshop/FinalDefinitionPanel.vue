<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatOwnerOptionLabel } from '@/features/workshops/metric-workshop/final-definition';
import type {
    EditableRow,
    FinalDefinition,
    OwnerOption,
    SummaryRow,
} from '@/features/workshops/metric-workshop/types';

const props = defineProps<{
    finalDefinition: FinalDefinition;
    showGovernanceLink: boolean;
    governanceHref: string;
    showEditDraftButton: boolean;
    ownerEligible: boolean;
    isReadyForSignOff: boolean;
    isDefinitionFieldMissing: (fieldKey: string) => boolean;
    editableRows: EditableRow[];
    notes: string;
    alignmentSummary: string;
    ownerParticipantId: string;
    isSigningOff: boolean;
    isSignOffDialogOpen: boolean;
    isSavingDraft: boolean;
    resolvedOwnerOptions: OwnerOption[];
    ownerSelectionRequired: boolean;
    selectedOwnerOption?: OwnerOption | null;
    selectedOwnerDetails: string | null;
    isLocked: boolean;
    showSignOffAction: boolean;
    signOffReadinessMessage: string | null;
    isEditingDraft: boolean;
    isEditingDisabled: boolean;
    formattedSignedOffAt: string | null;
    summaryRows: SummaryRow[];
    ownerSummaryLabel: string | null;
    isSaveDisabled: boolean;
    focusedDefinitionField?: string | null;
    focusDefinitionFieldRequest?: number;
}>();

const emit = defineEmits<{
    (event: 'edit-draft'): void;
    (event: 'cancel-draft'): void;
    (event: 'save-draft'): void;
    (event: 'confirm-sign-off'): void;
    (event: 'set-owner-participant', value: string | null): void;
    (event: 'set-notes', value: string): void;
    (event: 'set-alignment-summary', value: string): void;
    (event: 'set-sign-off-dialog-open', value: boolean): void;
}>();

const notesModel = computed({
    get: () => props.notes,
    set: (value: string) => emit('set-notes', value),
});

const alignmentSummaryModel = computed({
    get: () => props.alignmentSummary,
    set: (value: string) => emit('set-alignment-summary', value),
});

const signOffDialogOpen = computed({
    get: () => props.isSignOffDialogOpen,
    set: (value: boolean) => emit('set-sign-off-dialog-open', value),
});

const finalDefinitionForm = ref<HTMLFormElement | null>(null);

const focusRequestedDefinitionField = async (): Promise<void> => {
    if (
        !props.isEditingDraft ||
        props.isEditingDisabled ||
        !props.focusedDefinitionField
    ) {
        return;
    }

    await nextTick();

    const target = Array.from(
        finalDefinitionForm.value?.querySelectorAll<HTMLElement>(
            '[data-definition-field]',
        ) ?? [],
    ).find(
        (element) =>
            element.dataset.definitionField === props.focusedDefinitionField,
    );

    target?.focus();
};

watch(
    () => [
        props.isEditingDraft,
        props.focusedDefinitionField,
        props.focusDefinitionFieldRequest,
    ],
    () => {
        void focusRequestedDefinitionField();
    },
);

const prefersTextareaForRow = (row: EditableRow): boolean => {
    if (row.field.endsWith('_notes')) {
        return true;
    }

    const label = row.label.trim().toLowerCase();
    return label.includes('notes') || label.includes('context');
};

const startCase = (value: string): string =>
    value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase()) || value;

const formatValue = (raw: unknown): string => {
    if (raw === null || raw === undefined) {
        return '—';
    }

    if (Array.isArray(raw)) {
        if (!raw.length) {
            return '—';
        }

        return raw.map((entry) => formatValue(entry)).join(', ');
    }

    if (typeof raw === 'boolean') {
        return raw ? 'Yes' : 'No';
    }

    if (typeof raw === 'number') {
        return Number.isInteger(raw) ? raw.toString() : raw.toFixed(2);
    }

    if (typeof raw === 'string') {
        if (/[_-]/.test(raw)) {
            return startCase(raw);
        }

        return raw;
    }

    return String(raw);
};
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
        >
            <CardTitle>Final Definition</CardTitle>
            <div class="flex items-center gap-2">
                <Button
                    v-if="showGovernanceLink"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="governanceHref">Change governance</Link>
                </Button>
                <Button
                    v-if="showEditDraftButton"
                    variant="secondary"
                    size="sm"
                    data-test="final-definition-edit"
                    @click="emit('edit-draft')"
                >
                    Edit draft
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div
                v-if="!isEditingDraft"
                data-test="final-definition-summary"
                class="space-y-4"
            >
                <div data-test="final-definition" class="space-y-3">
                    <div class="grid gap-2">
                        <div class="rounded-md border border-border p-2">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="font-medium">Metric owner</p>
                                <span
                                    v-if="!ownerEligible"
                                    data-test="final-definition-owner-missing"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/60 dark:text-amber-100"
                                >
                                    Missing
                                </span>
                            </div>
                            <p
                                class="text-sm tracking-wide text-muted-foreground"
                            >
                                {{ ownerSummaryLabel ?? 'Unassigned' }}
                            </p>
                        </div>
                        <div
                            v-for="row in summaryRows"
                            :key="`c-${row.key}`"
                            class="rounded-md border border-border/60 p-2"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="text-sm font-bold text-foreground">
                                    {{ row.label }}
                                </p>
                                <span
                                    v-if="isDefinitionFieldMissing(row.key)"
                                    :data-test="`final-definition-field-missing-${row.key}`"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 uppercase dark:bg-amber-900/60 dark:text-amber-100"
                                >
                                    Missing
                                </span>
                            </div>
                            <p
                                class="text-sm tracking-wide text-muted-foreground"
                            >
                                {{ formatValue(row.value) }}
                            </p>
                        </div>

                        <div
                            v-if="finalDefinition.notes"
                            class="rounded-md border border-dashed p-2"
                        >
                            <p class="text-sm font-bold text-foreground">
                                Notes
                            </p>
                            <p class="text-sm">
                                {{ finalDefinition.notes }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <form
                v-else
                ref="finalDefinitionForm"
                data-test="final-definition-form"
                class="space-y-4"
                @submit.prevent="emit('save-draft')"
            >
                <div class="space-y-2">
                    <Label
                        class="text-sm font-bold text-foreground"
                        for="owner-participant"
                    >
                        Metric owner
                    </Label>
                    <Select
                        :model-value="ownerParticipantId || undefined"
                        :disabled="isEditingDisabled || !ownerSelectionRequired"
                        @update:model-value="
                            (value) =>
                                emit(
                                    'set-owner-participant',
                                    value as string | null,
                                )
                        "
                    >
                        <SelectTrigger
                            id="owner-participant"
                            class="h-auto w-full items-start py-2"
                            :disabled="
                                isEditingDisabled || !ownerSelectionRequired
                            "
                        >
                            <div
                                v-if="selectedOwnerOption"
                                class="flex flex-col gap-0.5 text-left"
                            >
                                <span class="text-sm leading-tight font-medium">
                                    {{ selectedOwnerOption.label }}
                                </span>
                                <span
                                    v-if="selectedOwnerDetails"
                                    class="text-xs leading-tight text-muted-foreground"
                                >
                                    {{ selectedOwnerDetails }}
                                </span>
                            </div>
                            <SelectValue
                                v-else
                                placeholder="Select a metric owner"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in resolvedOwnerOptions"
                                :key="option.id"
                                :value="option.id"
                                :disabled="option.disabled || isEditingDisabled"
                                :text-value="formatOwnerOptionLabel(option)"
                                class="pr-3 pl-6"
                            >
                                <span class="flex flex-col gap-0.5 text-left">
                                    <span
                                        class="text-sm leading-tight font-medium"
                                    >
                                        {{ option.label }}
                                    </span>
                                    <span
                                        v-if="
                                            option.responsibility ||
                                            option.affiliation
                                        "
                                        class="flex flex-wrap items-center gap-x-1.5 text-xs leading-tight text-muted-foreground"
                                    >
                                        <span v-if="option.responsibility">
                                            {{ option.responsibility }}
                                        </span>
                                        <span
                                            v-if="
                                                option.responsibility &&
                                                option.affiliation
                                            "
                                            aria-hidden="true"
                                        >
                                            •
                                        </span>
                                        <span v-if="option.affiliation">
                                            {{ option.affiliation }}
                                        </span>
                                    </span>
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="!ownerSelectionRequired"
                        class="text-xs text-muted-foreground"
                    >
                        Add participants with accounts to enable owner
                        selection.
                    </p>
                </div>
                <div
                    v-for="row in editableRows"
                    :key="row.field"
                    class="space-y-2"
                >
                    <label
                        class="text-sm font-bold text-foreground"
                        :for="`definition-${row.field}`"
                    >
                        {{ row.label }}
                    </label>
                    <Textarea
                        v-if="prefersTextareaForRow(row)"
                        :id="`definition-${row.field}`"
                        v-model="row.value"
                        :data-definition-field="row.field"
                        rows="3"
                        :disabled="isEditingDisabled"
                    />
                    <Input
                        v-else
                        :id="`definition-${row.field}`"
                        v-model="row.value"
                        :data-definition-field="row.field"
                        :disabled="isEditingDisabled"
                    />
                </div>

                <div class="space-y-2">
                    <label
                        class="text-sm font-bold text-foreground"
                        for="final-notes"
                    >
                        Notes
                    </label>
                    <Textarea
                        id="final-notes"
                        v-model="notesModel"
                        rows="4"
                        :disabled="isEditingDisabled"
                    />
                </div>
                <div class="space-y-2">
                    <Label
                        class="text-sm font-bold text-foreground"
                        for="alignment-summary"
                    >
                        Workshop Alignment Summary
                    </Label>
                    <Textarea
                        id="alignment-summary"
                        v-model="alignmentSummaryModel"
                        data-test="alignment-summary-input"
                        rows="4"
                        :disabled="isEditingDisabled"
                    />
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button
                        type="submit"
                        variant="secondary"
                        class="flex-1"
                        :disabled="isSaveDisabled"
                        :aria-busy="isSavingDraft || undefined"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <Loader2
                                v-if="isSavingDraft"
                                class="size-4 animate-spin"
                            />
                            <span>
                                {{ isSavingDraft ? 'Saving...' : 'Save' }}
                            </span>
                        </span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1"
                        data-test="final-definition-cancel"
                        @click="emit('cancel-draft')"
                    >
                        Cancel
                    </Button>
                </div>
            </form>
            <div class="mt-6 space-y-3 border-t border-border/60 pt-4">
                <p
                    v-if="isLocked"
                    class="text-sm font-medium text-green-600 dark:text-green-400"
                >
                    Signed off {{ formattedSignedOffAt }} by
                    {{ finalDefinition.signed_off_by?.name ?? 'unknown user' }}.
                </p>

                <p
                    v-if="
                        showSignOffAction &&
                        !isEditingDraft &&
                        !isReadyForSignOff &&
                        signOffReadinessMessage
                    "
                    data-test="final-definition-signoff-message"
                    class="text-xs text-muted-foreground"
                >
                    {{ signOffReadinessMessage }}
                </p>

                <Dialog
                    v-if="showSignOffAction && !isEditingDraft"
                    v-model:open="signOffDialogOpen"
                >
                    <DialogTrigger as-child>
                        <Button
                            type="button"
                            class="w-full"
                            data-test="final-definition-signoff"
                            :disabled="isSigningOff || !isReadyForSignOff"
                            :aria-busy="isSigningOff || undefined"
                            @click="emit('set-sign-off-dialog-open', true)"
                        >
                            <span
                                class="flex items-center justify-center gap-2"
                            >
                                <Loader2
                                    v-if="isSigningOff"
                                    class="size-4 animate-spin"
                                />
                                <span>
                                    {{
                                        isSigningOff
                                            ? 'Finalizing...'
                                            : 'Sign off'
                                    }}
                                </span>
                            </span>
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader class="space-y-2">
                            <DialogTitle>Sign off this metric?</DialogTitle>
                            <DialogDescription class="space-y-3">
                                <p>
                                    This finalizes the definition and locks
                                    further edits in this workshop.
                                </p>
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="button"
                                class="min-w-32"
                                :disabled="isSigningOff || !isReadyForSignOff"
                                :aria-busy="isSigningOff || undefined"
                                @click="emit('confirm-sign-off')"
                            >
                                <span
                                    class="flex items-center justify-center gap-2"
                                >
                                    <Loader2
                                        v-if="isSigningOff"
                                        class="size-4 animate-spin"
                                    />
                                    <span>
                                        {{
                                            isSigningOff
                                                ? 'Finalizing...'
                                                : 'Confirm sign off'
                                        }}
                                    </span>
                                </span>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </CardContent>
    </Card>
</template>

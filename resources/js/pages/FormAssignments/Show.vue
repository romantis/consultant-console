<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    AUTOSAVE_THROTTLE_MESSAGE,
    useAutosaveForm,
} from '@/composables/useAutosaveForm';
import { resolveCsrfHeaders } from '@/lib/csrf';
import { computed, reactive, ref, watch } from 'vue';

type FieldType = 'text' | 'number' | 'textarea' | 'select' | string;

type FieldOption = string | { label: string; value: string };

type Field = {
    name: string;
    label: string;
    form_label?: string | null;
    type?: FieldType;
    required?: boolean;
    options?: FieldOption[];
    placeholder?: string;
    help_text?: string;
};

type AssignmentDraft = {
    id: string;
    payload: Record<string, unknown>;
    submitted_at?: string | null;
    token: string;
};

type AssignmentResource = {
    id: string;
    status?: string | null;
    due_at?: string | null;
    reminder_cadence?: string[];
    form_title?: string | null;
    metric_name?: string | null;
    engagement_name?: string | null;
    organization_name?: string | null;
    schema?: {
        title?: string;
        description?: string;
        fields?: Field[];
    };
    draft: AssignmentDraft;
};

type AttestationConfig = {
    require_confirmation?: boolean;
    confirmation_label?: string;
    require_comment?: boolean;
    comment_label?: string;
    helper_text?: string;
};

const optionValue = (option: FieldOption): string => {
    if (typeof option === 'string') {
        return option;
    }

    const candidate = option?.value;
    return typeof candidate === 'string' ? candidate : '';
};

const optionLabel = (option: FieldOption): string => {
    if (typeof option === 'string') {
        return option;
    }

    const label = option?.label;
    if (typeof label === 'string' && label.trim().length > 0) {
        return label;
    }

    return optionValue(option);
};

const fieldLabel = (field: Field): string => {
    const formLabel =
        typeof field.form_label === 'string' ? field.form_label.trim() : '';

    if (formLabel.length > 0) {
        return formLabel;
    }

    return field.label;
};

const props = defineProps<{
    assignment: AssignmentResource;
    attestation?: AttestationConfig | null;
}>();

const cloneAssignment = (resource: AssignmentResource): AssignmentResource =>
    JSON.parse(JSON.stringify(resource ?? {})) as AssignmentResource;

const assignment = ref<AssignmentResource>(cloneAssignment(props.assignment));
const assignmentId = computed(
    () => assignment.value?.id ?? props.assignment.id,
);

const answers = reactive<Record<string, unknown>>({});
const touched = reactive<Record<string, boolean>>({});
const clientErrors = reactive<Record<string, string | null>>({});
const serverErrors = reactive<Record<string, string | null>>({});

const submitting = ref(false);
const submitError = ref('');
const submitSuccess = ref(false);

const confirmAccuracy = ref(false);
const attestationComment = ref('');

const fields = computed<Field[]>(() => assignment.value?.schema?.fields ?? []);
const formTitle = computed(
    () =>
        assignment.value?.form_title ??
        assignment.value?.schema?.title ??
        assignment.value?.metric_name ??
        'Metric Form',
);
const schemaDescription = computed(
    () => assignment.value?.schema?.description ?? null,
);
const metricName = computed(() => assignment.value?.metric_name ?? null);
const engagementName = computed(
    () => assignment.value?.engagement_name ?? null,
);
const organizationName = computed(
    () => assignment.value?.organization_name ?? null,
);
const reminderCadence = computed(
    () => assignment.value?.reminder_cadence ?? [],
);

const isSubmitted = computed(() =>
    Boolean(assignment.value?.draft?.submitted_at),
);
const statusDisplay = computed(
    () =>
        assignment.value?.status ??
        (isSubmitted.value ? 'Completed' : 'InProgress'),
);

const deadlineInfo = computed(() => {
    const due = assignment.value?.due_at;
    if (!due) {
        return null;
    }

    const dueDate = new Date(due);
    if (Number.isNaN(dueDate.getTime())) {
        return null;
    }

    const now = new Date();
    const diffMs = dueDate.getTime() - now.getTime();
    const diffHours = diffMs / (1000 * 60 * 60);

    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });
    let label: string;

    if (Math.abs(diffHours) < 1) {
        label =
            diffHours <= 0
                ? 'Overdue (less than 1 hour)'
                : 'Due in less than an hour';
    } else if (Math.abs(diffHours) < 24) {
        label = formatter.format(Math.round(diffHours), 'hour');
        label = diffHours <= 0 ? `Overdue (${label})` : `Due ${label}`;
    } else {
        const diffDays = diffHours / 24;
        label = formatter.format(Math.round(diffDays), 'day');
        label = diffHours <= 0 ? `Overdue (${label})` : `Due ${label}`;
    }

    const warn = diffHours <= 0 || diffHours <= 24;

    return {
        label,
        warn,
    };
});

const submittedAtLabel = computed(() => {
    if (!assignment.value?.draft?.submitted_at) {
        return null;
    }

    const submittedAt = new Date(assignment.value.draft.submitted_at);
    if (Number.isNaN(submittedAt.getTime())) {
        return assignment.value.draft.submitted_at;
    }

    return submittedAt.toLocaleString();
});

const attestationConfig = computed(() => props.attestation ?? null);
const attestationRequiresConfirmation = computed(() =>
    Boolean(attestationConfig.value?.require_confirmation),
);
const attestationRequiresComment = computed(() =>
    Boolean(attestationConfig.value?.require_comment),
);
const attestationConfirmationLabel = computed(
    () =>
        attestationConfig.value?.confirmation_label ??
        'I confirm the information provided is accurate.',
);
const attestationCommentLabel = computed(
    () =>
        attestationConfig.value?.comment_label ??
        'Add optional context or assumptions',
);
const attestationHelperText = computed(
    () => attestationConfig.value?.helper_text ?? null,
);
const attestationCommentValid = computed(
    () =>
        !attestationRequiresComment.value ||
        attestationComment.value.trim().length > 0,
);

const submitDisabled = computed(
    () =>
        submitting.value ||
        isSubmitted.value ||
        (attestationRequiresConfirmation.value && !confirmAccuracy.value) ||
        (attestationRequiresComment.value && !attestationCommentValid.value),
);

const initializeFromAssignment = (resource: AssignmentResource) => {
    cancelAutosave();

    for (const key of Object.keys(answers)) {
        delete answers[key];
    }
    for (const key of Object.keys(touched)) {
        delete touched[key];
    }
    for (const key of Object.keys(clientErrors)) {
        delete clientErrors[key];
    }
    for (const key of Object.keys(serverErrors)) {
        delete serverErrors[key];
    }

    const payload = resource?.draft?.payload ?? {};
    const schemaFields = resource?.schema?.fields ?? [];

    schemaFields.forEach((field) => {
        const value = Object.prototype.hasOwnProperty.call(payload, field.name)
            ? (payload as Record<string, unknown>)[field.name]
            : null;
        answers[field.name] = value;
    });

    Object.entries(payload).forEach(([key, value]) => {
        answers[key] = value as unknown;
    });

    autosaveState.value = 'idle';
    autosaveError.value = '';
    submitError.value = '';
    submitSuccess.value = Boolean(resource?.draft?.submitted_at);
    if (submitSuccess.value) {
        confirmAccuracy.value = true;
    } else {
        confirmAccuracy.value = false;
        attestationComment.value = '';
    }
};

const buildAssignmentUrl = (suffix = '') => {
    const id = assignmentId.value;
    if (!id) {
        throw new Error('Assignment identifier missing.');
    }

    return suffix
        ? `/api/form-assignments/${id}${suffix}`
        : `/api/form-assignments/${id}`;
};

const serializeAnswers = (): Record<string, unknown> =>
    JSON.parse(JSON.stringify(answers ?? {}));

const { autosaveState, autosaveError, scheduleAutosave, cancelAutosave } =
    useAutosaveForm({
        endpoint: () => buildAssignmentUrl('/autosave'),
        method: 'PATCH',
        debounceMs: 700,
        resetDelayMs: 2500,
        minThrottleDelayMs: 1000,
        throttleMessage: AUTOSAVE_THROTTLE_MESSAGE,
        isDisabled: () => !assignment.value || isSubmitted.value,
        buildPayload: () => ({
            draft: serializeAnswers(),
            submission_token:
                assignment.value?.draft?.token ?? props.assignment.draft.token,
        }),
        localStorageEvent: 'autosave',
        localStoragePayload: () => ({
            assignmentId: assignment.value?.id,
        }),
        onSuccess: () => {
            if (!assignment.value) {
                return;
            }

            assignment.value.draft.payload = serializeAnswers();
            if (!isSubmitted.value) {
                assignment.value.status =
                    assignment.value.status ?? 'InProgress';
            }
        },
    });

const markTouched = (field: Field) => {
    touched[field.name] = true;
    validateField(field);
};

const fieldType = (field: Field) => (field.type ?? 'text').toLowerCase();

const validateField = (field: Field) => {
    const type = fieldType(field);
    const value = answers[field.name];
    let message: string | null = null;

    if (
        field.required &&
        (value === undefined || value === null || value === '')
    ) {
        message = 'This field is required.';
    }

    if (!message && ['number', 'numeric', 'integer'].includes(type)) {
        if (value !== undefined && value !== null && value !== '') {
            const numeric = typeof value === 'number' ? value : Number(value);
            if (Number.isNaN(numeric)) {
                message = 'Enter a valid number.';
            } else {
                answers[field.name] = numeric;
            }
        }
    }

    clientErrors[field.name] = message;
    if (!message) {
        serverErrors[field.name] = null;
    }
};

const validateAll = () => {
    fields.value.forEach((field) => {
        touched[field.name] = true;
        validateField(field);
    });
};

const notifyLocalStorage = (event: 'autosave' | 'submit') => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            'mh:lastSubmissionUpdate',
            JSON.stringify({
                assignmentId: assignment.value?.id,
                event,
                ts: Date.now(),
            }),
        );
    } catch (error) {
        console.debug('Unable to persist submission update', error);
    }
};

const mapServerErrors = (errors: Record<string, string[] | string>) => {
    for (const key of Object.keys(serverErrors)) {
        serverErrors[key] = null;
    }

    for (const rawKey in errors ?? {}) {
        const messages = Array.isArray(errors[rawKey])
            ? errors[rawKey]
            : [errors[rawKey]];
        if (!messages || messages.length === 0) {
            continue;
        }

        const message = messages[0];
        if (rawKey.startsWith('payload.')) {
            const field = rawKey.replace('payload.', '');
            serverErrors[field] = message;
        } else if (rawKey.startsWith('answers_json.')) {
            const field = rawKey.replace('answers_json.', '');
            serverErrors[field] = message;
        } else {
            submitError.value = message;
        }
    }
};

const buildAttestationPayload = () => {
    if (!attestationConfig.value) {
        return undefined;
    }

    const payload: Record<string, unknown> = {};

    if (attestationRequiresConfirmation.value) {
        payload.confirm_accuracy = confirmAccuracy.value;
    }

    if (attestationRequiresComment.value) {
        payload.comment = attestationComment.value.trim();
    }

    return Object.keys(payload).length > 0 ? payload : undefined;
};

const handleSubmit = async () => {
    if (!assignment.value || submitting.value || isSubmitted.value) {
        return;
    }

    validateAll();

    const hasClientErrors = fields.value.some((field) =>
        Boolean(clientErrors[field.name]),
    );
    if (hasClientErrors) {
        submitError.value = 'Review the highlighted fields before submitting.';
        return;
    }

    if (attestationRequiresConfirmation.value && !confirmAccuracy.value) {
        submitError.value = 'Confirm accuracy before submitting.';
        return;
    }

    if (!attestationCommentValid.value) {
        submitError.value = 'Add the required comment before submitting.';
        return;
    }

    submitting.value = true;
    submitError.value = '';
    submitSuccess.value = false;

    for (const key of Object.keys(serverErrors)) {
        serverErrors[key] = null;
    }

    try {
        const response = await fetch(buildAssignmentUrl('/submit'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...resolveCsrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                payload: serializeAnswers(),
                submission_token: assignment.value.draft.token,
                attestation: buildAttestationPayload(),
            }),
        });

        if (response.status === 422) {
            const payload = await response.json().catch(() => null);
            mapServerErrors(payload?.errors ?? {});
            submitError.value =
                payload?.message ?? 'Please review the highlighted fields.';
            return;
        }

        if (!response.ok) {
            throw new Error(`Submission failed (${response.status})`);
        }

        const payload = await response.json().catch(() => null);

        assignment.value.draft.submitted_at =
            payload?.submitted_at ?? new Date().toISOString();
        assignment.value.status = 'Completed';

        submitSuccess.value = true;
        autosaveState.value = 'idle';
        notifyLocalStorage('submit');
    } catch (error) {
        console.error(error);
        submitError.value =
            error instanceof Error
                ? error.message
                : 'Unable to submit right now. Please retry.';
    } finally {
        submitting.value = false;
    }
};

watch(
    () => props.assignment,
    (next) => {
        assignment.value = cloneAssignment(next);
        initializeFromAssignment(assignment.value);
    },
    { deep: true, immediate: true },
);

watch(
    () => ({ ...answers }),
    () => {
        fields.value.forEach((field) => {
            if (touched[field.name]) {
                validateField(field);
            }
        });
    },
    { deep: true },
);

watch(confirmAccuracy, () => {
    if (confirmAccuracy.value) {
        submitError.value = '';
    }
});

watch(attestationComment, () => {
    if (attestationCommentValid.value) {
        submitError.value = '';
    }
});
</script>

<template>
    <div class="container max-w-4xl space-y-6 px-4 py-8">
        <Card>
            <CardHeader class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <CardTitle class="text-2xl font-semibold">{{
                            formTitle
                        }}</CardTitle>
                        <CardDescription v-if="schemaDescription">{{
                            schemaDescription
                        }}</CardDescription>
                        <p
                            v-if="metricName"
                            class="text-sm text-muted-foreground"
                        >
                            Metric: {{ metricName }}
                        </p>
                        <p
                            v-if="engagementName"
                            class="text-sm text-muted-foreground"
                        >
                            Engagement: {{ engagementName }}
                        </p>
                        <p
                            v-if="organizationName"
                            class="text-sm text-muted-foreground"
                        >
                            Organization: {{ organizationName }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Status:
                            <span class="font-medium">{{ statusDisplay }}</span>
                        </p>
                        <p
                            v-if="submittedAtLabel"
                            class="text-sm text-muted-foreground"
                        >
                            Submitted: {{ submittedAtLabel }}
                        </p>
                    </div>
                    <div v-if="deadlineInfo" class="text-right text-sm">
                        <p
                            :class="
                                deadlineInfo.warn
                                    ? 'font-medium text-amber-700'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ deadlineInfo.label }}
                        </p>
                        <p
                            v-if="reminderCadence.length"
                            class="text-xs text-muted-foreground"
                        >
                            Reminder cadence: {{ reminderCadence.join(', ') }}
                        </p>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <div
                    v-for="field in fields"
                    :key="field.name"
                    class="space-y-2"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <Label :for="field.name" class="font-medium">
                            {{ fieldLabel(field) }}
                            <span v-if="field.required" class="text-destructive"
                                >*</span
                            >
                        </Label>
                        <span
                            v-if="field.help_text"
                            class="text-xs text-muted-foreground"
                            >{{ field.help_text }}</span
                        >
                    </div>

                    <Input
                        v-if="
                            fieldType(field) === 'text' ||
                            fieldType(field) === 'number' ||
                            fieldType(field) === 'integer'
                        "
                        :id="field.name"
                        :type="fieldType(field) === 'text' ? 'text' : 'number'"
                        v-model="answers[field.name]"
                        :placeholder="field.placeholder ?? ''"
                        :disabled="isSubmitted"
                        @input="scheduleAutosave()"
                        @blur="markTouched(field)"
                    />

                    <Textarea
                        v-else-if="fieldType(field) === 'textarea'"
                        :id="field.name"
                        v-model="answers[field.name]"
                        :placeholder="field.placeholder ?? ''"
                        :disabled="isSubmitted"
                        rows="4"
                        @input="scheduleAutosave()"
                        @blur="markTouched(field)"
                    />

                    <select
                        v-else-if="fieldType(field) === 'select'"
                        :id="field.name"
                        v-model="answers[field.name]"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="isSubmitted"
                        @change="scheduleAutosave()"
                        @blur="markTouched(field)"
                    >
                        <option value="" disabled>Select an option</option>
                        <option
                            v-for="(option, optionIndex) in field.options ?? []"
                            :key="`${field.name}-${optionValue(option) || optionIndex}`"
                            :value="optionValue(option)"
                        >
                            {{ optionLabel(option) }}
                        </option>
                    </select>

                    <Input
                        v-else
                        :id="field.name"
                        v-model="answers[field.name]"
                        :placeholder="field.placeholder ?? ''"
                        :disabled="isSubmitted"
                        @input="scheduleAutosave()"
                        @blur="markTouched(field)"
                    />

                    <p
                        v-if="clientErrors[field.name]"
                        class="text-sm text-destructive"
                    >
                        {{ clientErrors[field.name] }}
                    </p>
                    <p
                        v-else-if="serverErrors[field.name]"
                        class="text-sm text-destructive"
                    >
                        {{ serverErrors[field.name] }}
                    </p>
                </div>

                <div
                    class="rounded-md border border-dashed border-muted-foreground/40 px-3 py-2 text-sm"
                >
                    <p
                        v-if="autosaveState === 'saving'"
                        class="text-muted-foreground"
                    >
                        Saving draft…
                    </p>
                    <p
                        v-else-if="autosaveState === 'saved'"
                        class="text-emerald-700"
                    >
                        Draft saved.
                    </p>
                    <p
                        v-else-if="autosaveState === 'error'"
                        class="text-destructive"
                    >
                        {{ autosaveError }}
                    </p>
                    <p v-else-if="submitSuccess" class="text-emerald-700">
                        Submission recorded.
                    </p>
                    <p v-else class="text-muted-foreground">
                        Draft updates save automatically while you type.
                    </p>
                </div>
            </CardContent>

            <CardFooter
                class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between"
            >
                <div
                    v-if="attestationConfig"
                    class="w-full space-y-3 rounded-md border border-muted-foreground/30 bg-muted/30 p-4 text-sm md:max-w-md"
                >
                    <p class="font-medium">Attestation</p>
                    <div
                        v-if="attestationRequiresConfirmation"
                        class="flex items-start gap-3"
                    >
                        <Checkbox
                            :id="'confirm-accuracy'"
                            :model-value="confirmAccuracy"
                            :disabled="isSubmitted"
                            class="mt-1"
                            @update:model-value="
                                (checked) =>
                                    (confirmAccuracy = Boolean(checked))
                            "
                        />
                        <Label
                            for="confirm-accuracy"
                            class="text-sm leading-relaxed font-normal"
                        >
                            {{ attestationConfirmationLabel }}
                        </Label>
                    </div>
                    <div v-if="attestationRequiresComment" class="space-y-1">
                        <Label for="attestation-comment">{{
                            attestationCommentLabel
                        }}</Label>
                        <Textarea
                            id="attestation-comment"
                            v-model="attestationComment"
                            :disabled="isSubmitted"
                            rows="3"
                            placeholder="Share clarifications or assumptions for reviewers."
                        />
                        <p
                            v-if="attestationHelperText"
                            class="text-xs text-muted-foreground"
                        >
                            {{ attestationHelperText }}
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col items-end gap-3 md:max-w-sm">
                    <p v-if="submitError" class="text-sm text-destructive">
                        {{ submitError }}
                    </p>
                    <Button
                        type="button"
                        class="w-full md:w-auto"
                        :disabled="submitDisabled"
                        @click="handleSubmit"
                    >
                        <span v-if="submitting">Submitting…</span>
                        <span v-else-if="isSubmitted">Submitted</span>
                        <span v-else>Submit for review</span>
                    </Button>
                </div>
            </CardFooter>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    AUTOSAVE_THROTTLE_MESSAGE,
    useAutosaveForm,
} from '@/composables/useAutosaveForm';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    autosave as autosaveRoute,
    show as formsShow,
    submit as submitRoute,
} from '@/routes/forms';
import type { AppPageProps } from '@/types';
import {
    Head,
    Form as InertiaForm,
    Link,
    router,
    usePage,
} from '@inertiajs/vue3';
import { MoveLeft } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type FieldOption = string | { label: string; value: string };

type Field = {
    name: string;
    type: 'text' | 'number' | 'textarea' | 'select' | 'checkbox_group';
    label: string;
    form_label?: string | null;
    required?: boolean;
    options?: FieldOption[];
    with_other?: boolean;
    other_prompt?: string | null;
};
const props = defineProps<{
    form: {
        id: string;
        title: string;
        schema_json: { fields: Field[] };
        status?: string | null;
    };
    submission: {
        id: string;
        answers_json: Record<string, unknown>;
        submitted_at?: string | null;
    } | null;
    submissionToken?: string | null;
    assignment?: { due_at?: string | null } | null;
    deadline?: {
        due_at: string;
        is_overdue: boolean;
        hours_remaining: number;
    } | null;
    backLink?: { label: string; href: string } | null;
    memberAffiliation?: string | null;
    readOnly?: boolean;
    submissionOptions?: Array<{
        id: string;
        submitted_at?: string | null;
        user?: {
            id: string;
            name?: string | null;
            email?: string | null;
        } | null;
    }>;
    activeSubmissionId?: string | null;
    submissionOwner?: {
        id: string;
        name?: string | null;
        email?: string | null;
    } | null;
}>();

const answers = reactive<Record<string, unknown>>({
    ...(props.submission?.answers_json || {}),
});
const touched = reactive<Record<string, boolean>>({});
const clientErrors = reactive<Record<string, string | null>>({});

const OTHER_OPTION_LABEL = 'Other';
const TEAM_DEPARTMENT_FIELD = 'team_department';
const isReadOnly = computed(() => Boolean(props.readOnly));
const page = usePage<AppPageProps>();
const isStaffLayout = computed(
    () =>
        Boolean(page.props.auth?.user?.is_internal_staff) ||
        Boolean(page.props.impersonation?.organization),
);
const formDescription = computed(() => {
    if (isReadOnly.value) {
        return isStaffLayout.value
            ? 'Reviewing contributor responses for this engagement.'
            : 'Reviewing submitted response.';
    }

    return 'Provide input for the active engagement.';
});
const backLinkTitle = computed(() => {
    const label = props.backLink?.label ?? '';
    const cleaned = label.replace(/^Back to\s+/i, '').trim();
    if (!cleaned) {
        return label || null;
    }

    return `${cleaned.charAt(0).toUpperCase()}${cleaned.slice(1)}`;
});
const breadcrumbs = computed(() => {
    if (!isStaffLayout.value || !props.backLink) {
        return [];
    }

    const parentTitle = backLinkTitle.value ?? props.backLink.label;

    return [
        {
            title: parentTitle,
            href: props.backLink.href,
        },
        {
            title: `Form ${props.form.title}`,
            href: resolveRouteUrl(formsShow(props.form.id)),
        },
    ];
});
const submissionOptions = computed(() => props.submissionOptions ?? []);
const selectedSubmissionId = ref(props.activeSubmissionId ?? null);

watch(
    () => props.activeSubmissionId,
    (next) => {
        selectedSubmissionId.value = next ?? null;
    },
);

const normalizeAffiliation = (value: string | null | undefined): string => {
    if (typeof value !== 'string') {
        return '';
    }

    return value.trim();
};

const hasAnswerValue = (value: unknown): boolean => {
    if (value === null || value === undefined) {
        return false;
    }

    if (typeof value === 'string') {
        return value.trim().length > 0;
    }

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return true;
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

function areStringArraysEqual(source: unknown, candidate: string[]): boolean {
    if (!Array.isArray(source)) {
        return false;
    }

    if (source.length !== candidate.length) {
        return false;
    }

    for (let i = 0; i < candidate.length; i += 1) {
        if (source[i] !== candidate[i]) {
            return false;
        }
    }

    return true;
}

function isOtherOptionValue(value: string | null | undefined): boolean {
    if (!value) {
        return false;
    }

    return value.toLowerCase() === OTHER_OPTION_LABEL.toLowerCase();
}

const isSubmitted = computed(() => Boolean(props.submission?.submitted_at));

const resetAnswers = (payload: Record<string, unknown>) => {
    Object.keys(answers).forEach((key) => {
        delete answers[key];
    });

    Object.entries(payload).forEach(([key, value]) => {
        answers[key] = value;
    });
};

watch(
    () => props.submission?.answers_json,
    (next) => {
        resetAnswers((next ?? {}) as Record<string, unknown>);
    },
    { immediate: true },
);

const maybePrefillTeamDepartment = () => {
    if (isReadOnly.value) {
        return;
    }

    const affiliation = normalizeAffiliation(props.memberAffiliation);
    if (!affiliation) {
        return;
    }

    const hasField = props.form.schema_json.fields?.some(
        (field) => field.name === TEAM_DEPARTMENT_FIELD,
    );

    if (!hasField || hasAnswerValue(answers[TEAM_DEPARTMENT_FIELD])) {
        return;
    }

    answers[TEAM_DEPARTMENT_FIELD] = affiliation;
};

maybePrefillTeamDepartment();

const deadlineText = computed(() => {
    if (!props.deadline) return null;
    const diffHours = props.deadline.hours_remaining;
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    if (props.deadline.is_overdue) {
        return `Overdue (${rtf.format(diffHours, 'hour')})`;
    }
    if (Math.abs(diffHours) < 1) {
        return 'Due in less than an hour';
    }
    if (Math.abs(diffHours) < 24) {
        return `Due ${rtf.format(diffHours, 'hour')}`;
    }
    const days = Math.round(diffHours / 24);
    return `Due ${rtf.format(days, 'day')}`;
});

const shouldWarnDeadline = computed(() =>
    props.deadline
        ? props.deadline.is_overdue || props.deadline.hours_remaining <= 24
        : false,
);

const submissionOwnerLabel = computed(() => {
    const owner = props.submissionOwner;
    if (!owner) {
        return null;
    }

    return owner.name ?? owner.email ?? null;
});

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
};

const submissionOptionLabel = (option: {
    id: string;
    submitted_at?: string | null;
    user?: { name?: string | null; email?: string | null } | null;
}) => {
    const name = option.user?.name ?? option.user?.email ?? 'Contributor';
    const submittedAt = option.submitted_at
        ? formatDateTime(option.submitted_at)
        : null;

    return submittedAt ? `${name} · ${submittedAt}` : name;
};

const visitSubmission = () => {
    if (!selectedSubmissionId.value) {
        return;
    }

    router.get(
        resolveRouteUrl(formsShow(props.form.id)),
        { submission: selectedSubmissionId.value },
        { preserveScroll: true, preserveState: true },
    );
};

function markTouched(field: Field) {
    touched[field.name] = true;
    validateField(field);
}

function validateField(field: Field) {
    if (!touched[field.name]) return;
    const value = answers[field.name];
    let message: string | null = null;

    if (field.type === 'checkbox_group') {
        const values = Array.isArray(value)
            ? value
                  .map((entry) => {
                      if (typeof entry === 'string') {
                          return entry.trim();
                      }
                      if (entry === null || entry === undefined) {
                          return '';
                      }
                      return String(entry).trim();
                  })
                  .filter((entry) => entry.length > 0)
            : [];

        if (!areStringArraysEqual(value, values)) {
            answers[field.name] = values;
        }

        if (field.required && values.length === 0) {
            message = 'Select at least one option.';
        }

        if (!message && hasOtherOption(field)) {
            const includesOther = values.some((entry) =>
                isOtherOptionValue(entry),
            );
            if (includesOther) {
                const sanitized = otherTextValue(field).trim();
                if (!sanitized.length) {
                    message = 'Enter details for Other.';
                } else {
                    setOtherTextValue(field, sanitized);
                }
            } else {
                setOtherTextValue(field, '');
            }
        }
    } else {
        if (
            field.required &&
            (value === undefined || value === null || value === '')
        ) {
            message = 'This field is required.';
        }

        if (
            !message &&
            field.type === 'number' &&
            value !== undefined &&
            value !== null &&
            value !== ''
        ) {
            const numeric = Number(value);
            if (Number.isNaN(numeric)) {
                message = 'Enter a valid number.';
            } else {
                answers[field.name] = numeric;
            }
        }

        if (!message && field.type === 'select' && hasOtherOption(field)) {
            const selection = typeof value === 'string' ? value : '';

            if (isOtherOptionValue(selection)) {
                const sanitized = otherTextValue(field).trim();
                if (!sanitized.length) {
                    message = 'Enter details for Other.';
                } else {
                    setOtherTextValue(field, sanitized);
                }
            } else {
                setOtherTextValue(field, '');
            }
        }
    }

    clientErrors[field.name] = message;
}

function validateAll() {
    props.form.schema_json.fields?.forEach((field) => {
        touched[field.name] = true;
        validateField(field);
    });
}

function resolveRouteUrl(route: unknown): string {
    if (typeof route === 'string') return route;
    if (route && typeof route === 'object') {
        if (
            'url' in route &&
            typeof (route as { url: unknown }).url === 'string'
        ) {
            return (route as { url: string }).url;
        }
        if (
            'href' in route &&
            typeof (route as { href: unknown }).href === 'string'
        ) {
            return (route as { href: string }).href;
        }
    }
    return String(route ?? '');
}

const serializeAnswers = (): Record<string, unknown> =>
    JSON.parse(JSON.stringify(answers ?? {}));

const { autosaveState, autosaveError, scheduleAutosave, cancelAutosave } =
    useAutosaveForm({
        endpoint: () => resolveRouteUrl(autosaveRoute(props.form.id)),
        method: 'PUT',
        debounceMs: 600,
        resetDelayMs: 2500,
        minThrottleDelayMs: 1000,
        throttleMessage: AUTOSAVE_THROTTLE_MESSAGE,
        isDisabled: () => isSubmitted.value || isReadOnly.value,
        buildPayload: () => ({
            answers_json: serializeAnswers(),
            submission_token: props.submissionToken ?? '',
        }),
        localStorageEvent: 'autosave',
        localStoragePayload: () => ({
            formId: props.form.id,
        }),
    });

function checkboxSelections(field: Field): string[] {
    const value = answers[field.name];
    if (Array.isArray(value)) {
        return value
            .map((entry) => {
                if (typeof entry === 'string') {
                    return entry;
                }
                if (entry === null || entry === undefined) {
                    return '';
                }
                return String(entry);
            })
            .filter((entry) => entry.length > 0);
    }

    if (typeof value === 'string' && value.length > 0) {
        return [value];
    }

    return [];
}

function isCheckboxSelected(field: Field, option: string): boolean {
    if (!option) return false;
    return checkboxSelections(field).includes(option);
}

function hasOtherOption(field: Field): boolean {
    if (!field.with_other) {
        return false;
    }

    return field.type === 'checkbox_group' || field.type === 'select';
}

function otherAnswerKey(field: Field): string {
    return `${field.name}__other_text`;
}

function otherPrompt(field: Field): string {
    const prompt =
        typeof field.other_prompt === 'string' ? field.other_prompt.trim() : '';
    return prompt.length ? prompt : 'Please specify';
}

function otherTextValue(field: Field): string {
    const key = otherAnswerKey(field);
    const value = answers[key];
    if (typeof value === 'string') {
        return value;
    }
    if (value === null || value === undefined) {
        return '';
    }
    return String(value);
}

function setOtherTextValue(field: Field, value: string) {
    answers[otherAnswerKey(field)] = value;
}

function isOtherSelected(field: Field): boolean {
    if (!hasOtherOption(field)) {
        return false;
    }

    if (field.type === 'checkbox_group') {
        return checkboxSelections(field).some((entry) =>
            isOtherOptionValue(entry),
        );
    }

    const value = answers[field.name];
    return typeof value === 'string' && isOtherOptionValue(value);
}

function handleOtherInput(field: Field) {
    if (!hasOtherOption(field)) {
        return;
    }

    scheduleAutosave();
    markTouched(field);
}

function handleSelectChange(field: Field) {
    if (hasOtherOption(field) && !isOtherSelected(field)) {
        setOtherTextValue(field, '');
    }

    scheduleAutosave();
    markTouched(field);
}

function toggleCheckboxOption(field: Field, option: string, event: Event) {
    const input = event.target as HTMLInputElement | null;
    if (!input) {
        return;
    }

    const normalized = option?.toString();
    if (!normalized) {
        return;
    }

    const values = checkboxSelections(field);

    if (input.checked) {
        if (!values.includes(normalized)) {
            values.push(normalized);
        }
    } else {
        const index = values.indexOf(normalized);
        if (index !== -1) {
            values.splice(index, 1);
        }

        if (hasOtherOption(field) && isOtherOptionValue(normalized)) {
            setOtherTextValue(field, '');
        }

        if (values.length === 0) {
            delete answers[field.name];
        }
    }

    answers[field.name] = [...values];
    scheduleAutosave();
    markTouched(field);
}

watch(
    () => ({ ...answers }),
    () => {
        props.form.schema_json.fields?.forEach((field) => {
            if (touched[field.name]) {
                validateField(field);
            }
        });
    },
    { deep: true },
);

watch(
    () => props.submission?.submitted_at,
    (submittedAt) => {
        if (submittedAt && !isReadOnly.value) {
            cancelAutosave();
            autosaveState.value = 'idle';
            window.localStorage.setItem(
                'mh:lastSubmissionUpdate',
                JSON.stringify({
                    formId: props.form.id,
                    event: 'submit',
                    ts: Date.now(),
                }),
            );
        }
    },
);
</script>

<template>
    <Head :title="`Form ${props.form.title}`" />

    <AppLayout
        :title="`Form ${props.form.title}`"
        :description="formDescription"
        :breadcrumbs="breadcrumbs"
    >
        <div class="space-y-6 p-6">
            <div v-if="props.backLink && !isStaffLayout" class="-mt-2 mb-3">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="props.backLink.href">
                        <MoveLeft class="ml-auto size-4 shrink-0 opacity-60" />
                        {{ props.backLink.label }}
                    </Link>
                </Button>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>{{ props.form.title }}</CardTitle>
                    <p
                        v-if="isStaffLayout"
                        class="text-sm text-muted-foreground"
                    >
                        {{ formDescription }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Status: {{ props.form.status || 'Open' }}
                    </p>
                    <div v-if="isReadOnly" class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            Read-only view. You’re not assigned to this form.
                        </p>
                        <div v-if="submissionOptions.length" class="grid gap-2">
                            <Label for="submission_id">Submission</Label>
                            <select
                                id="submission_id"
                                v-model="selectedSubmissionId"
                                class="w-full rounded border px-3 py-2"
                                @change="visitSubmission"
                            >
                                <option
                                    v-for="option in submissionOptions"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{ submissionOptionLabel(option) }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                <span v-if="submissionOwnerLabel">
                                    Viewing submission by
                                    {{ submissionOwnerLabel }}
                                </span>
                                <span v-else>Viewing submission</span>
                                <span v-if="props.submission?.submitted_at">
                                    ·
                                    {{
                                        formatDateTime(
                                            props.submission?.submitted_at,
                                        )
                                    }}
                                </span>
                            </p>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No submissions yet.
                        </p>
                    </div>
                    <p
                        v-if="deadlineText"
                        :class="[
                            'text-sm',
                            shouldWarnDeadline
                                ? 'text-amber-700'
                                : 'text-muted-foreground',
                        ]"
                    >
                        Deadline: {{ deadlineText }}
                    </p>
                </CardHeader>

                <!-- Inertia <Form>: posts to submit route; we inject our answers via transform -->
                <InertiaForm
                    :action="submitRoute(props.form.id)"
                    method="post"
                    :transform="
                        () => ({
                            answers_json: { ...answers },
                            submission_token: props.submissionToken ?? '',
                        })
                    "
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <CardContent class="space-y-4">
                        <div
                            v-for="f in props.form.schema_json.fields"
                            :key="f.name"
                            class="grid gap-2"
                        >
                            <Label :for="f.name">{{ fieldLabel(f) }}</Label>

                            <Input
                                v-if="f.type === 'text' || f.type === 'number'"
                                :id="f.name"
                                :type="f.type === 'number' ? 'number' : 'text'"
                                v-model="answers[f.name]"
                                :disabled="isSubmitted || isReadOnly"
                                @input="scheduleAutosave()"
                                @blur="markTouched(f)"
                            />

                            <Textarea
                                v-else-if="f.type === 'textarea'"
                                :id="f.name"
                                v-model="answers[f.name]"
                                :disabled="isSubmitted || isReadOnly"
                                @input="scheduleAutosave()"
                                @blur="markTouched(f)"
                            />

                            <div
                                v-else-if="f.type === 'select'"
                                class="space-y-2"
                            >
                                <select
                                    class="rounded border px-3 py-2"
                                    :id="f.name"
                                    v-model="answers[f.name]"
                                    :disabled="isSubmitted || isReadOnly"
                                    @change="handleSelectChange(f)"
                                    @blur="markTouched(f)"
                                >
                                    <option disabled value="">
                                        {{
                                            f.options && f.options.length
                                                ? 'Select an option'
                                                : 'Options will appear here'
                                        }}
                                    </option>
                                    <option
                                        v-for="option in f.options || []"
                                        :key="optionValue(option)"
                                        :value="optionValue(option)"
                                    >
                                        {{ optionLabel(option) }}
                                    </option>
                                    <option
                                        v-if="hasOtherOption(f)"
                                        :value="OTHER_OPTION_LABEL"
                                    >
                                        Other
                                    </option>
                                </select>
                                <div
                                    v-if="
                                        hasOtherOption(f) && isOtherSelected(f)
                                    "
                                    class="space-y-2 rounded border"
                                >
                                    <Textarea
                                        :id="`${f.name}-other-text`"
                                        v-model="answers[otherAnswerKey(f)]"
                                        rows="3"
                                        :placeholder="otherPrompt(f)"
                                        :disabled="isSubmitted || isReadOnly"
                                        @input="handleOtherInput(f)"
                                        @blur="markTouched(f)"
                                    />
                                    <p
                                        v-if="
                                            errors[
                                                `answers_json.${otherAnswerKey(f)}`
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            errors[
                                                `answers_json.${otherAnswerKey(f)}`
                                            ]
                                        }}
                                    </p>
                                </div>
                                <p
                                    v-if="!(f.options && f.options.length)"
                                    class="text-sm text-muted-foreground"
                                >
                                    No options configured.
                                </p>
                            </div>

                            <div
                                v-else-if="f.type === 'checkbox_group'"
                                class="space-y-2"
                            >
                                <div
                                    v-for="(option, optionIndex) in f.options ||
                                    []"
                                    :key="`${f.name}-${optionValue(option) || optionIndex}`"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border"
                                        :id="`${f.name}-${optionValue(option) || optionIndex}`"
                                        :checked="
                                            isCheckboxSelected(
                                                f,
                                                optionValue(option),
                                            )
                                        "
                                        :disabled="isSubmitted || isReadOnly"
                                        @change="
                                            toggleCheckboxOption(
                                                f,
                                                optionValue(option),
                                                $event,
                                            )
                                        "
                                        @blur="markTouched(f)"
                                    />
                                    <Label
                                        :for="`${f.name}-${optionValue(option) || optionIndex}`"
                                        >{{ optionLabel(option) }}</Label
                                    >
                                </div>
                                <template v-if="hasOtherOption(f)">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border"
                                            :id="`${f.name}-other`"
                                            :checked="isOtherSelected(f)"
                                            :disabled="
                                                isSubmitted || isReadOnly
                                            "
                                            @change="
                                                toggleCheckboxOption(
                                                    f,
                                                    OTHER_OPTION_LABEL,
                                                    $event,
                                                )
                                            "
                                            @blur="markTouched(f)"
                                        />
                                        <Label :for="`${f.name}-other`"
                                            >Other</Label
                                        >
                                    </div>
                                    <Textarea
                                        v-if="isOtherSelected(f)"
                                        :id="`${f.name}-other-text`"
                                        v-model="answers[otherAnswerKey(f)]"
                                        rows="3"
                                        :placeholder="otherPrompt(f)"
                                        :disabled="isSubmitted || isReadOnly"
                                        @input="handleOtherInput(f)"
                                        @blur="markTouched(f)"
                                    />
                                    <p
                                        v-if="
                                            errors[
                                                `answers_json.${otherAnswerKey(f)}`
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            errors[
                                                `answers_json.${otherAnswerKey(f)}`
                                            ]
                                        }}
                                    </p>
                                </template>
                                <p
                                    v-if="!(f.options && f.options.length)"
                                    class="text-sm text-muted-foreground"
                                >
                                    No options configured.
                                </p>
                            </div>

                            <p
                                v-if="clientErrors[f.name]"
                                class="text-sm text-red-600"
                            >
                                {{ clientErrors[f.name] }}
                            </p>
                            <p
                                v-else-if="errors[`answers_json.${f.name}`]"
                                class="text-sm text-red-600"
                            >
                                {{ errors[`answers_json.${f.name}`] }}
                            </p>
                        </div>

                        <div class="text-sm">
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
                                class="text-red-600"
                            >
                                {{ autosaveError }}
                            </p>
                            <p
                                v-else-if="recentlySuccessful"
                                class="text-emerald-700"
                            >
                                Submission sent.
                            </p>
                        </div>
                    </CardContent>

                    <CardFooter v-if="!isReadOnly" class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="processing || isSubmitted"
                            @click="validateAll"
                        >
                            <span v-if="processing">Submitting…</span>
                            <span v-else-if="isSubmitted">Submitted</span>
                            <span v-else>Submit</span>
                        </Button>
                    </CardFooter>
                </InertiaForm>
            </Card>
        </div>
    </AppLayout>
</template>

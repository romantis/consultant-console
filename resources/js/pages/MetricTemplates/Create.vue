<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

import MetricTemplateWorkshopGuidanceFields from '@/components/metrics/MetricTemplateWorkshopGuidanceFields.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import {
    defaultClassification,
    defaultExecutiveSection,
    defaultState,
    executiveSectionOptions,
    fieldRoleOptions,
    fieldTypes,
    useMetricTemplateForm,
    type FieldClassificationRole,
    type FieldExecutiveSection,
    type FieldForm,
} from '@/composables/useMetricTemplateForm';
import { useWayfinder } from '@/composables/useWayfinder';
import type { BreadcrumbItem } from '@/types';
import { computed, ref, watch } from 'vue';

const wayfinder = useWayfinder();
const metricTemplatesIndexRoute = wayfinder.metricTemplates.index();

type CanonicalMetricOption = {
    id: string;
    key: string;
    name: string;
    description: string | null;
};

const props = defineProps<{
    canonicalMetrics: CanonicalMetricOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Metric Templates', href: metricTemplatesIndexRoute.url },
    { title: 'New Template', href: '' },
];

const { form, addField, removeField } = useMetricTemplateForm(defaultState());
const roleDescriptionMap = Object.fromEntries(
    fieldRoleOptions.map((option) => [option.value, option.description]),
) as Record<FieldClassificationRole, string>;
const executiveSectionDescriptionMap = Object.fromEntries(
    executiveSectionOptions.map((option) => [option.value, option.description]),
) as Record<FieldExecutiveSection, string>;

const canonicalTouched = ref(false);
const canonicalLookup = computed(
    () => new Map(props.canonicalMetrics.map((metric) => [metric.id, metric])),
);
const canonicalKeyLookup = computed(
    () =>
        new Map(
            props.canonicalMetrics.map((metric) => [
                metric.key.toLowerCase(),
                metric,
            ]),
        ),
);

watch(
    () => form.key,
    (value) => {
        if (canonicalTouched.value) {
            return;
        }

        const normalized = (value ?? '').trim().toLowerCase();
        if (!normalized) {
            return;
        }

        const match = canonicalKeyLookup.value.get(normalized);
        if (match) {
            form.canonical_metric_id = match.id;
        }
    },
);

watch(
    () => form.canonical_metric_id,
    (value) => {
        const selected = canonicalLookup.value.get(value);
        if (!selected) {
            return;
        }

        if (!form.name.trim()) {
            form.name = selected.name;
        }

        if (!form.description.trim()) {
            form.description = selected.description ?? '';
        }
    },
);

const handleCanonicalChange = (value: string) => {
    canonicalTouched.value = true;
    form.canonical_metric_id = value;
};

function submitForm() {
    form.post(wayfinder.metricTemplates.store(), {
        preserveScroll: true,
    });
}

function handleRoleChange(field: FieldForm) {
    const defaults = defaultClassification(field.classification.role);
    field.classification.excludeFromConsensus = defaults.excludeFromConsensus;
    field.classification.companionOf = '';
    field.executiveSection = defaultExecutiveSection(field.classification.role);
}
</script>

<template>
    <Head title="New Metric Template" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    New Metric Template
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Define a reusable template and schema for future forms.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Template details</CardTitle>
                    <CardDescription
                        >Provide the foundational information for this
                        template.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submitForm">
                        <div class="grid gap-2">
                            <Label for="template-canonical"
                                >Canonical metric</Label
                            >
                            <Select
                                :model-value="form.canonical_metric_id"
                                @update:model-value="
                                    (value) =>
                                        handleCanonicalChange(String(value))
                                "
                            >
                                <SelectTrigger id="template-canonical">
                                    <SelectValue
                                        placeholder="Select a canonical metric"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="metric in props.canonicalMetrics"
                                        :key="metric.id"
                                        :value="metric.id"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">
                                                {{ metric.name }}
                                            </span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ metric.key }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="form.errors.canonical_metric_id"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.canonical_metric_id }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="template-name">Name</Label>
                            <Input
                                id="template-name"
                                v-model="form.name"
                                placeholder="Monthly Recurring Revenue"
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="template-key">Key</Label>
                            <Input
                                id="template-key"
                                v-model="form.key"
                                placeholder="mrr"
                            />
                            <p class="text-xs text-muted-foreground">
                                Use lowercase letters, numbers, hyphens, or
                                underscores.
                            </p>
                            <p
                                v-if="form.errors.key"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.key }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="template-description"
                                >Description</Label
                            >
                            <Textarea
                                id="template-description"
                                v-model="form.description"
                                placeholder="Explain what this template collects."
                            />
                            <p
                                v-if="form.errors.description"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-semibold">
                                        Fields
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Add at least one field to collect data.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addField"
                                    >Add field</Button
                                >
                            </div>
                            <p
                                v-if="form.errors['form_schema_json.fields']"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors['form_schema_json.fields'] }}
                            </p>

                            <div
                                v-for="(field, index) in form.fields"
                                :key="index"
                                class="space-y-4 rounded-lg border p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold">
                                        Field {{ index + 1 }}
                                    </h4>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600"
                                        @click="removeField(index)"
                                        :disabled="form.fields.length <= 1"
                                    >
                                        Remove
                                    </Button>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <Label :for="`field-name-${index}`"
                                            >Name</Label
                                        >
                                        <Input
                                            :id="`field-name-${index}`"
                                            v-model="field.name"
                                            placeholder="total_mrr"
                                        />
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.name`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.name`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`field-label-${index}`"
                                            >Canonical label</Label
                                        >
                                        <Input
                                            :id="`field-label-${index}`"
                                            v-model="field.label"
                                            placeholder="Active user reporting window"
                                        />
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.label`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.label`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="`field-form-label-${index}`"
                                            >Form label (optional)</Label
                                        >
                                        <Input
                                            :id="`field-form-label-${index}`"
                                            v-model="field.formLabel"
                                            placeholder="Which active user windows do you track?"
                                        />
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.form_label`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.form_label`
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label :for="`field-type-${index}`"
                                            >Type</Label
                                        >
                                        <select
                                            :id="`field-type-${index}`"
                                            v-model="field.type"
                                            class="rounded border px-3 py-2"
                                        >
                                            <option
                                                v-for="option in fieldTypes"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.type`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.type`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="flex flex-col gap-3 pt-4">
                                        <label
                                            class="flex items-center gap-2 text-sm font-medium"
                                        >
                                            <input
                                                type="checkbox"
                                                :id="`field-required-${index}`"
                                                v-model="field.required"
                                                class="size-4 rounded border"
                                            />
                                            <span>Required</span>
                                        </label>
                                        <label
                                            class="flex items-start gap-2 text-sm font-medium"
                                        >
                                            <input
                                                type="checkbox"
                                                :id="`field-meta-${index}`"
                                                v-model="field.meta"
                                                class="mt-1 size-4 rounded border"
                                            />
                                            <span>
                                                Context only (exclude from
                                                reconciliation)
                                                <p
                                                    class="text-xs font-normal text-muted-foreground"
                                                >
                                                    Use for metadata like
                                                    department names or contact
                                                    info.
                                                </p>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="space-y-4 border-t pt-4">
                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-start justify-between gap-4"
                                        >
                                            <div>
                                                <Label
                                                    :for="`field-role-${index}`"
                                                    >Role</Label
                                                >
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Classify the field so
                                                    definition, context, and
                                                    resolution views stay in
                                                    sync.
                                                </p>
                                            </div>
                                            <select
                                                :id="`field-role-${index}`"
                                                v-model="
                                                    field.classification.role
                                                "
                                                class="rounded border px-3 py-2 text-sm"
                                                @change="
                                                    handleRoleChange(field)
                                                "
                                            >
                                                <option
                                                    v-for="option in fieldRoleOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                roleDescriptionMap[
                                                    field.classification.role
                                                ]
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.classification.role`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.classification.role`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-start justify-between gap-4"
                                        >
                                            <div>
                                                <Label
                                                    :for="`field-executive-section-${index}`"
                                                    >Executive section</Label
                                                >
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Choose where this field
                                                    appears in the handbook
                                                    definition summary.
                                                </p>
                                            </div>
                                            <select
                                                :id="`field-executive-section-${index}`"
                                                v-model="field.executiveSection"
                                                class="rounded border px-3 py-2 text-sm"
                                            >
                                                <option
                                                    v-for="option in executiveSectionOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                executiveSectionDescriptionMap[
                                                    field.executiveSection
                                                ]
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.executive_section`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.executive_section`
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="`field-exclude-consensus-${index}`"
                                            >Consensus visibility</Label
                                        >
                                        <label
                                            class="flex items-start gap-2 text-sm font-medium"
                                        >
                                            <input
                                                type="checkbox"
                                                :id="`field-exclude-consensus-${index}`"
                                                class="mt-1 size-4 rounded border"
                                                v-model="
                                                    field.classification
                                                        .excludeFromConsensus
                                                "
                                            />
                                            <span>
                                                Exclude from consensus
                                                (definition + resolution)
                                                <p
                                                    class="text-xs font-normal text-muted-foreground"
                                                >
                                                    Excluded fields stay in form
                                                    capture but are omitted from
                                                    final definition and
                                                    discrepancy consensus
                                                    context.
                                                </p>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="`field-companion-of-${index}`"
                                            >Companion of (optional field
                                            key)</Label
                                        >
                                        <Input
                                            :id="`field-companion-of-${index}`"
                                            v-model="
                                                field.classification.companionOf
                                            "
                                            :disabled="
                                                field.classification.role ===
                                                    'definition' ||
                                                field.classification
                                                    .excludeFromConsensus
                                            "
                                            placeholder="active_metrics_tracked"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Link this field as companion
                                            rationale under another consensus
                                            field key in handbook and workshop
                                            surfaces.
                                        </p>
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.classification.companion_of`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.classification.companion_of`
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <MetricTemplateWorkshopGuidanceFields
                                    v-model:workshopPrompt="
                                        field.workshopPrompt
                                    "
                                    v-model:workshopGuidance="
                                        field.workshopGuidance
                                    "
                                    :index="index"
                                    :errors="
                                        form.errors as unknown as Record<
                                            string,
                                            string
                                        >
                                    "
                                />

                                <div
                                    v-if="
                                        field.type === 'select' ||
                                        field.type === 'checkbox_group'
                                    "
                                    class="grid gap-2"
                                >
                                    <Label :for="`field-options-${index}`"
                                        >Options (one per line)</Label
                                    >
                                    <Textarea
                                        :id="`field-options-${index}`"
                                        v-model="field.optionsText"
                                        placeholder="Option A\nOption B"
                                    />
                                    <p
                                        v-if="
                                            form.errors[
                                                `form_schema_json.fields.${index}.options`
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                `form_schema_json.fields.${index}.options`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-if="
                                        field.type === 'checkbox_group' ||
                                        field.type === 'select'
                                    "
                                    class="space-y-3"
                                >
                                    <div class="flex items-center gap-2 pt-2">
                                        <input
                                            type="checkbox"
                                            :id="`field-with-other-${index}`"
                                            v-model="field.withOther"
                                            class="size-4 rounded border"
                                        />
                                        <Label
                                            :for="`field-with-other-${index}`"
                                            >Include “Other” option</Label
                                        >
                                    </div>

                                    <div
                                        v-if="field.withOther"
                                        class="grid gap-2"
                                    >
                                        <Label
                                            :for="`field-other-prompt-${index}`"
                                            >Prompt for the text field</Label
                                        >
                                        <Input
                                            :id="`field-other-prompt-${index}`"
                                            v-model="field.otherPrompt"
                                            placeholder="Please specify"
                                        />
                                        <p
                                            v-if="
                                                form.errors[
                                                    `form_schema_json.fields.${index}.other_prompt`
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    `form_schema_json.fields.${index}.other_prompt`
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <Button type="button" variant="outline" as-child>
                                <Link :href="metricTemplatesIndexRoute"
                                    >Cancel</Link
                                >
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <span v-if="form.processing">Saving…</span>
                                <span v-else>Create template</span>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

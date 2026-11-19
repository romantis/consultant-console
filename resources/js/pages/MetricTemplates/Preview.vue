<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useWayfinder } from '@/composables/useWayfinder';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type FieldOption = string | { value: string; label: string };

interface FieldDefinition {
    name: string;
    label: string;
    form_label?: string | null;
    type: string;
    required?: boolean;
    options?: FieldOption[];
    with_other?: boolean;
    other_prompt?: string | null;
}

interface TemplatePayload {
    id: string;
    name: string;
    key: string;
    description?: string | null;
    form_schema_json: {
        fields: FieldDefinition[];
    };
}

const props = defineProps<{
    template: TemplatePayload;
}>();

const wayfinder = useWayfinder();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Metric Templates', href: wayfinder.metricTemplates.index().url },
    { title: props.template.name, href: '' },
]);

const placeholderText = (type: string) => {
    if (type === 'number') return '123 or 123.45';
    if (type === 'textarea') return 'Detailed answer';
    return 'Sample response';
};

const OTHER_OPTION_LABEL = 'Other';

const optionValue = (option: FieldOption): string => {
    if (typeof option === 'string') {
        return option;
    }

    return typeof option?.value === 'string' ? option.value : '';
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

const fieldLabel = (field: FieldDefinition): string => {
    const formLabel =
        typeof field.form_label === 'string' ? field.form_label.trim() : '';

    if (formLabel.length > 0) {
        return formLabel;
    }

    return field.label || field.name;
};

const previewCheckboxSelections = reactive<Record<string, string[]>>({});
const previewSelectValues = reactive<Record<string, string>>({});
const previewOtherText = reactive<Record<string, string>>({});

const selectionFor = (field: FieldDefinition): string[] =>
    previewCheckboxSelections[field.name] ?? [];

const isOptionSelected = (field: FieldDefinition, option: string): boolean =>
    selectionFor(field).includes(option);

function togglePreviewOption(
    field: FieldDefinition,
    option: string,
    checked: boolean,
) {
    const values = [...selectionFor(field)];

    if (checked) {
        if (!values.includes(option)) {
            values.push(option);
        }
    } else {
        const index = values.indexOf(option);
        if (index !== -1) {
            values.splice(index, 1);
        }

        if (option === OTHER_OPTION_LABEL) {
            previewOtherText[field.name] = '';
        }
    }

    previewCheckboxSelections[field.name] = values;
}

const isOtherSelected = (field: FieldDefinition): boolean =>
    isOptionSelected(field, OTHER_OPTION_LABEL);

const selectValueFor = (field: FieldDefinition): string =>
    previewSelectValues[field.name] ?? '';

const isSelectOtherSelected = (field: FieldDefinition): boolean =>
    field.with_other ? selectValueFor(field) === OTHER_OPTION_LABEL : false;

function handleSelectChange(field: FieldDefinition) {
    if (!field.with_other) {
        return;
    }

    if (selectValueFor(field) !== OTHER_OPTION_LABEL) {
        previewOtherText[field.name] = '';
    }
}
</script>

<template>
    <Head :title="`${template.name} Preview`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ template.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Previewing the {{ template.key }} intake form. Fields behave
                    like they do for contributors so you can interact with them
                    without saving data.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Form fields</CardTitle>
                    <CardDescription>
                        {{
                            template.description ||
                            'This template does not include a description yet.'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="field in template.form_schema_json.fields"
                        :key="field.name"
                        class="space-y-2 rounded-lg border p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <Label
                                    :for="`preview-${field.name}`"
                                    class="text-base font-medium"
                                >
                                    {{ fieldLabel(field) }}
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    Key: {{ field.name }} · {{ field.type }}
                                </p>
                            </div>
                            <Badge v-if="field.required" variant="secondary"
                                >Required</Badge
                            >
                        </div>

                        <Input
                            v-if="
                                field.type === 'text' || field.type === 'number'
                            "
                            :id="`preview-${field.name}`"
                            :type="field.type === 'number' ? 'number' : 'text'"
                            :placeholder="placeholderText(field.type)"
                        />

                        <Textarea
                            v-else-if="field.type === 'textarea'"
                            :id="`preview-${field.name}`"
                            rows="4"
                            :placeholder="placeholderText(field.type)"
                        />

                        <div
                            v-else-if="field.type === 'select'"
                            class="space-y-2"
                        >
                            <select
                                :id="`preview-${field.name}`"
                                class="w-full rounded border px-3 py-2"
                                v-model="previewSelectValues[field.name]"
                                @change="handleSelectChange(field)"
                            >
                                <option disabled value="">
                                    {{
                                        field.options?.length
                                            ? 'Select an option'
                                            : 'Options will appear here'
                                    }}
                                </option>
                                <option
                                    v-for="option in field.options || []"
                                    :key="optionValue(option)"
                                    :value="optionValue(option)"
                                >
                                    {{ optionLabel(option) }}
                                </option>
                                <option
                                    v-if="field.with_other"
                                    :value="OTHER_OPTION_LABEL"
                                >
                                    Other
                                </option>
                            </select>
                            <div
                                v-if="isSelectOtherSelected(field)"
                                class="rounded border border-dashed border-border/70 bg-muted/30"
                            >
                                <Textarea
                                    :id="`preview-${field.name}-other-text`"
                                    rows="3"
                                    v-model="previewOtherText[field.name]"
                                    :placeholder="
                                        field.other_prompt || 'Please specify'
                                    "
                                />
                            </div>
                            <p
                                v-if="!field.options?.length"
                                class="text-sm text-muted-foreground"
                            >
                                Configure at least one option for this select
                                field.
                            </p>
                        </div>

                        <div
                            v-else-if="field.type === 'checkbox_group'"
                            class="space-y-2"
                        >
                            <div
                                v-for="(option, optionIndex) in field.options ||
                                []"
                                :key="`${field.name}-${optionValue(option) || optionIndex}`"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`preview-${field.name}-${optionValue(option) || optionIndex}`"
                                    :model-value="
                                        isOptionSelected(
                                            field,
                                            optionValue(option),
                                        )
                                    "
                                    @update:model-value="
                                        (checked) =>
                                            togglePreviewOption(
                                                field,
                                                optionValue(option),
                                                checked as boolean,
                                            )
                                    "
                                />
                                <Label
                                    :for="`preview-${field.name}-${optionValue(option) || optionIndex}`"
                                    >{{ optionLabel(option) }}</Label
                                >
                            </div>
                            <template v-if="field.with_other">
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        :id="`preview-${field.name}-other`"
                                        :model-value="isOtherSelected(field)"
                                        @update:model-value="
                                            (checked) =>
                                                togglePreviewOption(
                                                    field,
                                                    OTHER_OPTION_LABEL,
                                                    checked as boolean,
                                                )
                                        "
                                    />
                                    <Label :for="`preview-${field.name}-other`"
                                        >Other</Label
                                    >
                                </div>
                                <div
                                    v-if="isOtherSelected(field)"
                                    class="rounded border border-dashed border-border/70 bg-muted/30"
                                >
                                    <Textarea
                                        :id="`preview-${field.name}-other-text`"
                                        rows="3"
                                        v-model="previewOtherText[field.name]"
                                        :placeholder="
                                            field.other_prompt ||
                                            'Please specify'
                                        "
                                    />
                                </div>
                            </template>
                            <p
                                v-if="!field.options?.length"
                                class="text-sm text-muted-foreground"
                            >
                                Configure at least one option for this checkbox
                                group.
                            </p>
                        </div>

                        <p v-else class="text-sm text-muted-foreground">
                            Unsupported field type: {{ field.type }}
                        </p>
                    </div>

                    <p
                        v-if="!template.form_schema_json.fields.length"
                        class="text-sm text-muted-foreground"
                    >
                        This template does not include any fields yet. Add at
                        least one field to preview inputs.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

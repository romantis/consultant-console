<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useWayfinder } from '@/composables/useWayfinder';

type MetricFormMode = 'create' | 'edit';

type MetricFormMetric = {
    id: string;
    key: string;
    name: string;
    description: string | null;
    default_metric_template_id?: string | null;
    is_active: boolean;
};

type TemplateOption = {
    id: string;
    key: string;
    name: string;
    description: string | null;
};

const props = defineProps<{
    mode: MetricFormMode;
    metric?: MetricFormMetric;
    templates?: TemplateOption[];
    cancelHref?: string;
}>();

const wayfinder = useWayfinder();
const isEdit = computed(() => props.mode === 'edit');
const showCancel = computed(() => isEdit.value && Boolean(props.cancelHref));
const cancelHref = computed(() => props.cancelHref ?? '');
const keyTouched = ref(false);
const templateOptions = computed(() => props.templates ?? []);
const DEFAULT_TEMPLATE_NONE = '__none__';

const form = useForm({
    key: props.metric?.key ?? '',
    name: props.metric?.name ?? '',
    description: props.metric?.description ?? '',
    default_metric_template_id:
        props.metric?.default_metric_template_id ?? DEFAULT_TEMPLATE_NONE,
    is_active: props.metric?.is_active ?? true,
});

const normalizeKey = (value: string) => {
    const normalized = value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');

    return normalized;
};

watch(
    () => form.name,
    (value) => {
        if (isEdit.value || keyTouched.value) {
            return;
        }

        form.key = normalizeKey(value ?? '');
    },
);

const handleKeyInput = (value: string | number) => {
    if (isEdit.value) {
        return;
    }

    const raw = typeof value === 'string' ? value : String(value);
    keyTouched.value = raw.trim().length > 0;
};

const submit = () => {
    if (isEdit.value) {
        if (!props.metric) return;

        form.patch(wayfinder.metricLibrary.update(props.metric.id), {
            preserveScroll: true,
        });
        return;
    }

    form.post(wayfinder.metricLibrary.store(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <div class="space-y-2">
            <Label for="metric-name">Name</Label>
            <Input
                id="metric-name"
                v-model="form.name"
                placeholder="Monthly Recurring Revenue"
            />
            <p v-if="form.errors.name" class="text-xs text-red-600">
                {{ form.errors.name }}
            </p>
        </div>
        <div class="space-y-2">
            <Label for="metric-key">Key</Label>
            <Input
                id="metric-key"
                v-model="form.key"
                :disabled="isEdit"
                placeholder="mrr"
                @update:model-value="handleKeyInput"
            />
            <p v-if="form.errors.key" class="text-xs text-red-600">
                {{ form.errors.key }}
            </p>
        </div>
        <div class="space-y-2 md:col-span-2">
            <Label for="metric-description">Description</Label>
            <Textarea
                id="metric-description"
                v-model="form.description"
                rows="3"
                placeholder="Explain how this metric should be interpreted."
            />
            <p v-if="form.errors.description" class="text-xs text-red-600">
                {{ form.errors.description }}
            </p>
        </div>
        <div
            v-if="isEdit && templateOptions.length"
            class="space-y-2 md:col-span-2"
        >
            <Label for="metric-default-template">Default template</Label>
            <Select
                :model-value="form.default_metric_template_id"
                @update:model-value="
                    (value) => (form.default_metric_template_id = String(value))
                "
            >
                <SelectTrigger id="metric-default-template">
                    <SelectValue placeholder="Select a default template" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="DEFAULT_TEMPLATE_NONE">
                        No default
                    </SelectItem>
                    <SelectItem
                        v-for="template in templateOptions"
                        :key="template.id"
                        :value="template.id"
                    >
                        <div class="flex flex-col">
                            <span class="text-sm font-medium">
                                {{ template.name }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ template.key }}
                            </span>
                        </div>
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
                Used to preselect template variants when launching new
                engagements.
            </p>
            <p
                v-if="form.errors.default_metric_template_id"
                class="text-xs text-red-600"
            >
                {{ form.errors.default_metric_template_id }}
            </p>
        </div>
        <div v-if="isEdit" class="flex items-center gap-2 md:col-span-2">
            <Checkbox id="metric-active" v-model="form.is_active" />
            <Label for="metric-active" class="text-sm">Active in catalog</Label>
        </div>
        <div class="flex justify-end gap-2 md:col-span-2">
            <Button v-if="showCancel" type="button" variant="outline" as-child>
                <Link :href="cancelHref">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ isEdit ? 'Save changes' : 'Create metric' }}
            </Button>
        </div>
    </form>
</template>

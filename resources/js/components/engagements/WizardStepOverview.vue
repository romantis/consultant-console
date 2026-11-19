<template>
    <Card>
        <CardHeader>
            <CardTitle>Engagement overview</CardTitle>
            <CardDescription
                >Set the launch basics for
                {{ organizationName }}.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-6">
            <div class="grid gap-4">
                <div class="space-y-2">
                    <Label for="wizard-overview-name">Engagement name</Label>
                    <Input
                        id="wizard-overview-name"
                        :model-value="overview.name"
                        autocomplete="off"
                        placeholder="Northwind Transformation, Q1 FY25"
                        @update:model-value="
                            (value) => updateOverviewField('name', value)
                        "
                    />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="wizard-overview-timeline-start"
                            >Timeline start</Label
                        >
                        <Input
                            id="wizard-overview-timeline-start"
                            type="date"
                            :model-value="overview.timelineStart"
                            @update:model-value="
                                (value) =>
                                    updateOverviewField('timelineStart', value)
                            "
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="wizard-overview-timeline-end"
                            >Timeline end</Label
                        >
                        <Input
                            id="wizard-overview-timeline-end"
                            type="date"
                            :model-value="overview.timelineEnd"
                            @update:model-value="
                                (value) =>
                                    updateOverviewField('timelineEnd', value)
                            "
                        />
                    </div>
                </div>
                <div v-if="repositoryEnabled" class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="wizard-overview-repository-driver"
                            >Handbook destination (optional)</Label
                        >
                        <Select
                            :model-value="repositoryDriverValue"
                            @update:model-value="
                                (driver) =>
                                    handleRepositoryDriverChange(
                                        driver as string,
                                    )
                            "
                        >
                            <SelectTrigger
                                id="wizard-overview-repository-driver"
                            >
                                <SelectValue placeholder="Select repository" />
                            </SelectTrigger>
                            <SelectContent class="max-h-64">
                                <SelectItem :value="SKIP_REPOSITORY_DRIVER"
                                    >Skip for now</SelectItem
                                >
                                <SelectItem
                                    v-for="option in repositoryOptions"
                                    :key="option.driver"
                                    :value="option.driver"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="wizard-overview-repository-identifier"
                            >Repository identifier</Label
                        >
                        <Input
                            id="wizard-overview-repository-identifier"
                            :model-value="overview.repository.identifier"
                            autocomplete="off"
                            placeholder="notion.so/workspace/northwind"
                            @update:model-value="
                                (value) =>
                                    updateRepositoryField('identifier', value)
                            "
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="wizard-overview-repository-notes"
                        >Notes for the delivery team</Label
                    >
                    <Textarea
                        id="wizard-overview-repository-notes"
                        :model-value="overview.repository.notes ?? ''"
                        rows="4"
                        placeholder="Include context for handbook contributors or reminders for the launch team."
                        @update:model-value="
                            (value) => updateRepositoryField('notes', value)
                        "
                    />
                </div>
            </div>

            <div
                class="rounded-lg border border-dashed border-muted-foreground/40 bg-muted/30 p-4 text-sm text-muted-foreground"
            >
                <p>
                    These details pre-fill the readiness checklist, set cadence
                    defaults, and determine where exported handbooks publish.
                </p>
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
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
import { computed, toRefs, watch } from 'vue';

type WizardOverviewState = {
    name: string;
    timelineStart: string;
    timelineEnd: string;
    repository: {
        driver: string;
        identifier: string;
        notes?: string | null;
    };
};

type RepositoryOption = {
    driver: string;
    label: string;
};

const props = defineProps<{
    organizationName: string;
    overview: WizardOverviewState;
    repositoryOptions: RepositoryOption[];
    repositoryEnabled: boolean;
}>();
// TODO: Remove this flag once repository adapters exist so the wizard can default to the richer selector again.
const { organizationName, overview, repositoryOptions, repositoryEnabled } =
    toRefs(props);
const SKIP_REPOSITORY_DRIVER = '__skip__';

const emit = defineEmits<{
    (event: 'change', patch: Partial<WizardOverviewState>): void;
}>();

function resolveDate(input?: string) {
    if (input) {
        const parsed = new Date(input);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed;
        }
    }

    return new Date();
}

function resolveQuarterLabel(date: Date) {
    const quarter = Math.floor(date.getMonth() / 3) + 1;
    return `Q${quarter}`;
}

const suggestedName = computed(() => {
    const date = resolveDate(overview.value.timelineStart);
    const label = resolveQuarterLabel(date);

    return `${organizationName.value} — ${label} ${date.getFullYear()}`;
});

const repositoryDriverValue = computed(() => {
    const driver = overview.value.repository.driver?.trim() ?? '';
    return driver.length > 0 ? driver : SKIP_REPOSITORY_DRIVER;
});

watch(
    () => [overview.value.name, suggestedName.value],
    ([currentName, defaultName]) => {
        if (!currentName?.trim()) {
            updateOverviewField('name', defaultName);
        }
    },
    { immediate: true },
);

function updateOverviewField(field: keyof WizardOverviewState, value: string) {
    emit('change', { [field]: value } as Partial<WizardOverviewState>);
}

function updateRepositoryField(
    field: keyof WizardOverviewState['repository'],
    value: string | null,
) {
    emit('change', {
        repository: {
            [field]: value,
        },
    } as Partial<WizardOverviewState>);
}

function handleRepositoryDriverChange(driver: string | null | undefined) {
    const normalized =
        driver === SKIP_REPOSITORY_DRIVER || driver == null ? '' : driver;

    if (!normalized) {
        emit('change', {
            repository: {
                driver: '',
                identifier: '',
                notes: null,
            },
        } as Partial<WizardOverviewState>);
        return;
    }

    updateRepositoryField('driver', normalized);
}
</script>

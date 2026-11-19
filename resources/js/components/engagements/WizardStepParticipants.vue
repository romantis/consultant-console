<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Invite stakeholders</CardTitle>
                <CardDescription
                    >Add external participants before assigning them to
                    metrics.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
                >
                    <div class="space-y-2">
                        <Label for="wizard-invite-email">Email</Label>
                        <Input
                            id="wizard-invite-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            placeholder="invitee@example.com"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="wizard-invite-role">Role</Label>
                        <Select
                            :model-value="form.role"
                            @update:model-value="
                                (value) => (form.role = value as string)
                            "
                        >
                            <SelectTrigger id="wizard-invite-role">
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in stakeholderRoles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="wizard-invite-name">Name (optional)</Label>
                        <Input
                            id="wizard-invite-name"
                            v-model="form.name"
                            autocomplete="off"
                            placeholder="Jordan Metrics"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="wizard-invite-department"
                            >Department (optional)</Label
                        >
                        <Input
                            id="wizard-invite-department"
                            v-model="form.department"
                            autocomplete="off"
                            placeholder="Operations"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Button type="button" @click="handleInviteSubmission"
                        >Add invite</Button
                    >
                    <p v-if="inviteError" class="text-sm text-destructive">
                        {{ inviteError }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Invites appear in the participant lists once added.
                    </p>
                </div>

                <div v-if="invites.length" class="space-y-3">
                    <div class="text-sm font-medium text-muted-foreground">
                        Pending invites
                    </div>
                    <ul class="space-y-2">
                        <li
                            v-for="invite in invites"
                            :key="invite.id"
                            class="flex items-center justify-between rounded-lg border p-3 text-sm"
                        >
                            <div>
                                <p class="leading-tight font-medium">
                                    {{ invite.name || invite.email }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ invite.email }} ·
                                    {{
                                        resolveStakeholderRoleLabel(invite.role)
                                    }}
                                    <span v-if="invite.department" class="ml-1">
                                        · {{ invite.department }}
                                    </span>
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:text-destructive"
                                @click="handleRemoveInvite(invite.id)"
                            >
                                Remove
                            </Button>
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Assign responsibilities</CardTitle>
                <CardDescription>
                    Each metric needs contributors. Observers are limited to
                    five per metric.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div
                    v-if="!selectedTemplates.length"
                    class="rounded-lg border border-dashed border-muted-foreground/40 bg-muted/30 p-6 text-sm text-muted-foreground"
                >
                    Select metrics first to assign participants.
                </div>

                <div
                    v-for="template in selectedTemplates"
                    :key="`assignment-${template.key}`"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p class="text-base font-semibold">
                                {{ template.name }}
                            </p>
                            <p
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                {{ template.key }}
                            </p>
                        </div>
                        <Badge variant="outline">
                            {{
                                participantsFor(template.key).contributors
                                    .length
                            }}
                            contributor{{
                                participantsFor(template.key).contributors
                                    .length === 1
                                    ? ''
                                    : 's'
                            }}
                        </Badge>
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="space-y-2">
                            <p class="text-sm font-medium">Contributors</p>
                            <p class="text-xs text-muted-foreground">
                                Own the definition, receive autosave reminders.
                            </p>
                            <div class="mt-2 space-y-2">
                                <label
                                    v-for="option in participantOptions.contributors"
                                    :key="`contributor-${template.key}-${option.id}`"
                                    class="flex items-start gap-2 rounded-md border p-2 text-sm"
                                >
                                    <Checkbox
                                        class="mt-1"
                                        :model-value="
                                            isParticipantSelected(
                                                template.key,
                                                'contributors',
                                                option.id,
                                            )
                                        "
                                        @update:model-value="
                                            (checked) =>
                                                toggleParticipant(
                                                    template.key,
                                                    'contributors',
                                                    option.id,
                                                    Boolean(checked),
                                                )
                                        "
                                    />
                                    <div>
                                        <p
                                            class="flex items-center gap-2 leading-tight font-medium"
                                        >
                                            <span>{{ option.label }}</span>
                                            <Badge
                                                v-if="option.badge"
                                                variant="secondary"
                                                class="text-[10px] tracking-wide uppercase"
                                            >
                                                {{ option.badge }}
                                            </Badge>
                                        </p>
                                        <p
                                            v-if="option.subtitle"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ option.subtitle }}
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm font-medium">Approvers</p>
                            <p class="text-xs text-muted-foreground">
                                Sign off definitions and collaborate on
                                reconciliation.
                            </p>
                            <div class="mt-2 space-y-2">
                                <label
                                    v-for="option in participantOptions.approvers"
                                    :key="`approver-${template.key}-${option.id}`"
                                    class="flex items-start gap-2 rounded-md border p-2 text-sm"
                                >
                                    <Checkbox
                                        class="mt-1"
                                        :model-value="
                                            isParticipantSelected(
                                                template.key,
                                                'approvers',
                                                option.id,
                                            )
                                        "
                                        @update:model-value="
                                            (checked) =>
                                                toggleParticipant(
                                                    template.key,
                                                    'approvers',
                                                    option.id,
                                                    Boolean(checked),
                                                )
                                        "
                                    />
                                    <div>
                                        <p
                                            class="flex items-center gap-2 leading-tight font-medium"
                                        >
                                            <span>{{ option.label }}</span>
                                            <Badge
                                                v-if="option.badge"
                                                variant="secondary"
                                                class="text-[10px] tracking-wide uppercase"
                                            >
                                                {{ option.badge }}
                                            </Badge>
                                        </p>
                                        <p
                                            v-if="option.subtitle"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ option.subtitle }}
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm font-medium">Observers</p>
                            <p class="text-xs text-muted-foreground">
                                Stay in the loop without editing
                                responsibilities.
                            </p>
                            <div class="mt-2 space-y-2">
                                <template
                                    v-for="option in participantOptions.observers"
                                    :key="`observer-${template.key}-${option.id}`"
                                >
                                    <label
                                        v-if="
                                            !shouldHideObserverOption(
                                                template.key,
                                                option.id,
                                            )
                                        "
                                        class="flex items-start gap-2 rounded-md border p-2 text-sm"
                                        :class="{
                                            'opacity-50':
                                                observersAtLimit(
                                                    template.key,
                                                ) &&
                                                !isParticipantSelected(
                                                    template.key,
                                                    'observers',
                                                    option.id,
                                                ),
                                        }"
                                    >
                                        <Checkbox
                                            class="mt-1"
                                            :model-value="
                                                isParticipantSelected(
                                                    template.key,
                                                    'observers',
                                                    option.id,
                                                )
                                            "
                                            :disabled="
                                                observersAtLimit(
                                                    template.key,
                                                ) &&
                                                !isParticipantSelected(
                                                    template.key,
                                                    'observers',
                                                    option.id,
                                                )
                                            "
                                            @update:model-value="
                                                (checked) =>
                                                    toggleParticipant(
                                                        template.key,
                                                        'observers',
                                                        option.id,
                                                        Boolean(checked),
                                                    )
                                            "
                                        />
                                        <div>
                                            <p
                                                class="flex items-center gap-2 leading-tight font-medium"
                                            >
                                                <span>{{ option.label }}</span>
                                                <Badge
                                                    v-if="option.badge"
                                                    variant="secondary"
                                                    class="text-[10px] tracking-wide uppercase"
                                                >
                                                    {{ option.badge }}
                                                </Badge>
                                            </p>
                                            <p
                                                v-if="option.subtitle"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ option.subtitle }}
                                            </p>
                                        </div>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, toRef, toRefs, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type WizardTemplate = {
    key: string;
    name: string;
    description: string;
    required_departments?: string[];
};

type WizardInviteInput = {
    email: string;
    name?: string;
    role?: string;
    department?: string;
};

type StoredInvite = WizardInviteInput & { id: string };

type ParticipantSelectionOption = {
    id: string;
    label: string;
    subtitle?: string;
    badge?: string;
    isViewer?: boolean;
    isStaff?: boolean;
};

type ParticipantBuckets = {
    contributors: string[];
    approvers: string[];
    observers: string[];
    availability: Record<string, string>;
};

type ParticipantRoleKey = keyof Pick<
    ParticipantBuckets,
    'contributors' | 'approvers' | 'observers'
>;

const props = defineProps<{
    invites: StoredInvite[];
    stakeholderRoles: Array<{ value: string; label: string }>;
    defaultInviteRole: string;
    participantOptions: {
        contributors: ParticipantSelectionOption[];
        approvers: ParticipantSelectionOption[];
        observers: ParticipantSelectionOption[];
    };
    selectedTemplates: WizardTemplate[];
    participantsFor: (metricKey: string) => ParticipantBuckets;
    isParticipantSelected: (
        metricKey: string,
        role: ParticipantRoleKey,
        participantId: string,
    ) => boolean;
    toggleParticipant: (
        metricKey: string,
        role: ParticipantRoleKey,
        participantId: string,
        selected: boolean,
    ) => void;
    observersAtLimit: (metricKey: string) => boolean;
    shouldHideObserverOption?: (
        metricKey: string,
        participantId: string,
    ) => boolean;
    addInvite: (invite: WizardInviteInput) => string;
    removeInvite: (inviteId: string) => void;
    resolveStakeholderRoleLabel: (role?: string) => string;
}>();

const { invites, stakeholderRoles, participantOptions, selectedTemplates } =
    toRefs(props);
const defaultInviteRole = toRef(props, 'defaultInviteRole');

const form = reactive<Required<WizardInviteInput>>({
    email: '',
    name: '',
    role: '',
    department: '',
});

const inviteError = ref<string | null>(null);

watch(
    () => defaultInviteRole.value,
    (role) => {
        if (!form.role) {
            form.role = role;
        }
    },
    { immediate: true },
);

function resetForm() {
    form.email = '';
    form.name = '';
    form.department = '';
    form.role = defaultInviteRole.value;
}

function handleInviteSubmission() {
    inviteError.value = null;

    try {
        props.addInvite({
            email: form.email,
            name: form.name,
            role: form.role,
            department: form.department,
        });

        resetForm();
    } catch (error) {
        inviteError.value =
            error instanceof Error
                ? error.message
                : 'Unable to add invite. Please try again.';
    }
}

function handleRemoveInvite(inviteId: string) {
    props.removeInvite(inviteId);
}

const resolveStakeholderRoleLabel = props.resolveStakeholderRoleLabel;
const shouldHideObserverOption =
    props.shouldHideObserverOption ?? (() => false);
const participantsFor = props.participantsFor;
const isParticipantSelected = props.isParticipantSelected;
const toggleParticipant = props.toggleParticipant;
const observersAtLimit = props.observersAtLimit;
</script>

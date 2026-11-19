<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import { show as orgShow } from '@/routes/org';
import { update as orgSettingsUpdate } from '@/routes/org/settings';

import type { MemberRow, PendingMemberRow } from '@/types';

type OrgSettings = {
    locale?: string;
    currency?: string;
    timezone?: string;
};

const TIMEZONE_PREFERRED_ALIASES: Record<string, string> = {
    'Europe/Kiev': 'Europe/Kyiv',
};

const toPreferredTimezoneName = (value: string): string => {
    const normalized = value.trim();

    if (normalized.length === 0) {
        return normalized;
    }

    return TIMEZONE_PREFERRED_ALIASES[normalized] ?? normalized;
};

type Organization = {
    id: string;
    name: string;
    slug: string;
    settings_json?: OrgSettings;
};

type EditableMember = MemberRow & {
    originalRole: string;
    roleDraft: string;
    originalAffiliation: string | null;
    affiliationDraft: string;
    remove: boolean;
};

type EditablePending = {
    id: string | null;
    key: string;
    email: string;
    originalRole: string;
    originalName: string | null;
    roleDraft: string;
    nameDraft: string;
    remove: boolean;
    isNew: boolean;
    originalAffiliation: string | null;
    affiliationDraft: string;
    affiliation?: string | null;
    engagementId?: string | null;
    sentAt?: string | null;
    expiresAt?: string | null;
};

const props = defineProps<{
    organization: Organization;
    members: MemberRow[];
    pending_members: PendingMemberRow[];
    roles: { value: string; label: string }[];
}>();

const browserTimezone = toPreferredTimezoneName(
    Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
);
const defaultTimezone = toPreferredTimezoneName(
    props.organization.settings_json?.timezone?.trim() || browserTimezone,
);
const supportedTimezoneValues =
    typeof Intl.supportedValuesOf === 'function'
        ? Intl.supportedValuesOf('timeZone').map(toPreferredTimezoneName)
        : [];

const timezones = [...new Set([...supportedTimezoneValues, defaultTimezone])]
    .filter((value) => value.trim().length > 0)
    .sort((left, right) => left.localeCompare(right));

const form = useForm({
    name: props.organization.name ?? '',
    settings_json: {
        locale: props.organization.settings_json?.locale ?? 'en',
        currency: props.organization.settings_json?.currency ?? 'USD',
        timezone: defaultTimezone,
    } as OrgSettings,
});

const membersState = reactive<EditableMember[]>(
    props.members.map((member) => ({
        ...member,
        originalRole: member.role,
        roleDraft: member.role,
        originalAffiliation: member.affiliation ?? null,
        affiliationDraft: member.affiliation ?? '',
        remove: false,
    })),
);

let inviteKeyCounter = 0;
const pendingState = reactive<EditablePending[]>(
    props.pending_members.map((invite) => ({
        id: invite.id,
        key: `existing-${invite.id}`,
        email: invite.email,
        originalRole: invite.role,
        originalName: invite.name ?? null,
        roleDraft: invite.role,
        nameDraft: invite.name ?? '',
        remove: false,
        isNew: false,
        originalAffiliation: invite.affiliation ?? null,
        affiliationDraft: invite.affiliation ?? '',
        affiliation: invite.affiliation ?? null,
        engagementId: invite.engagement_id ?? null,
        sentAt: invite.sent_at ?? null,
        expiresAt: invite.expires_at ?? null,
    })),
);

const defaultRole =
    props.roles.find((role) => role.value === 'member')?.value ??
    props.roles[0]?.value ??
    'member';

const newInvite = reactive({
    email: '',
    name: '',
    role: defaultRole,
    affiliation: '',
});

const memberSearch = ref('');
const pendingSearch = ref('');
const inviteFormError = ref<string | null>(null);

const page = usePage();
const flashSuccess = page.props?.flash?.success as string | undefined;

const resolveRoute = (route: unknown): string => {
    if (!route) return '';
    if (typeof route === 'string') return route;
    if (
        typeof route === 'object' &&
        'url' in route &&
        typeof (route as { url?: unknown }).url === 'string'
    ) {
        return (route as { url: string }).url;
    }

    throw new Error('Unable to resolve route target');
};

const resetNewInvite = () => {
    newInvite.email = '';
    newInvite.name = '';
    newInvite.role = defaultRole;
    newInvite.affiliation = '';
};

const formatDateTime = (value?: string | null) => {
    if (!value) return null;

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const addInvite = () => {
    const email = newInvite.email.trim().toLowerCase();

    if (!email) {
        inviteFormError.value = 'Email is required.';
        return;
    }

    const existsMember = membersState.some(
        (member) => member.email.toLowerCase() === email && !member.remove,
    );
    const existsPending = pendingState.some(
        (invite) => invite.email.toLowerCase() === email && !invite.remove,
    );

    if (existsMember || existsPending) {
        inviteFormError.value = 'Member already added.';
        return;
    }

    inviteFormError.value = null;

    pendingState.push({
        id: null,
        key: `new-${inviteKeyCounter++}`,
        email,
        originalRole: newInvite.role,
        originalName:
            newInvite.name.trim() !== '' ? newInvite.name.trim() : null,
        roleDraft: newInvite.role,
        nameDraft: newInvite.name.trim(),
        remove: false,
        isNew: true,
        originalAffiliation: null,
        affiliationDraft: newInvite.affiliation.trim(),
        affiliation:
            newInvite.affiliation.trim() !== ''
                ? newInvite.affiliation.trim()
                : null,
        sentAt: null,
        expiresAt: null,
    });

    resetNewInvite();
};

const toggleMemberRemoval = (member: EditableMember) => {
    member.remove = !member.remove;
};

const togglePendingRemoval = (invite: EditablePending) => {
    if (invite.isNew) {
        const index = pendingState.findIndex(
            (entry) => entry.key === invite.key,
        );
        if (index !== -1) {
            pendingState.splice(index, 1);
        }
        return;
    }

    invite.remove = !invite.remove;
};

const normalizeName = (value: string | null | undefined) =>
    (value ?? '').trim();
const normalizeAffiliation = (value: string | null | undefined) => {
    const normalized = (value ?? '').trim();
    return normalized === '' ? null : normalized;
};

const filteredMembers = computed(() => {
    const query = memberSearch.value.trim().toLowerCase();

    if (!query) {
        return membersState;
    }

    return membersState.filter(
        (member) =>
            member.name.toLowerCase().includes(query) ||
            member.email.toLowerCase().includes(query) ||
            member.affiliationDraft.toLowerCase().includes(query),
    );
});

const filteredPending = computed(() => {
    const query = pendingSearch.value.trim().toLowerCase();

    if (!query) {
        return pendingState;
    }

    return pendingState.filter(
        (invite) =>
            invite.email.toLowerCase().includes(query) ||
            normalizeName(invite.nameDraft).toLowerCase().includes(query) ||
            normalizeName(invite.originalName).toLowerCase().includes(query) ||
            (invite.affiliationDraft?.toLowerCase().includes(query) ?? false),
    );
});

const membershipPayloadDetails = computed(() => {
    const updateEntries = membersState
        .filter(
            (member) =>
                !member.remove &&
                (member.roleDraft !== member.originalRole ||
                    normalizeAffiliation(member.affiliationDraft) !==
                        normalizeAffiliation(member.originalAffiliation)),
        )
        .map((member) => {
            const data: {
                membership_id: string;
                role?: string;
                affiliation: string | null;
            } = {
                membership_id: member.id,
                affiliation: normalizeAffiliation(member.affiliationDraft),
            };

            if (member.roleDraft !== member.originalRole) {
                data.role = member.roleDraft;
            }

            return {
                id: member.id,
                data,
            };
        });

    const deleteEntries = membersState
        .filter((member) => member.remove)
        .map((member) => member.id);

    const pendingUpdateEntries = pendingState
        .filter(
            (invite) =>
                !invite.remove &&
                !invite.isNew &&
                (invite.roleDraft !== invite.originalRole ||
                    normalizeName(invite.nameDraft) !==
                        normalizeName(invite.originalName) ||
                    normalizeAffiliation(invite.affiliationDraft) !==
                        normalizeAffiliation(invite.originalAffiliation)),
        )
        .map((invite) => ({
            id: invite.id as string,
            data: {
                pending_id: invite.id as string,
                role: invite.roleDraft,
                name:
                    normalizeName(invite.nameDraft) !== ''
                        ? normalizeName(invite.nameDraft)
                        : null,
                affiliation: normalizeAffiliation(invite.affiliationDraft),
            },
        }));

    const pendingDeleteEntries = pendingState
        .filter((invite) => invite.remove && !invite.isNew && invite.id)
        .map((invite) => invite.id as string);

    const createEntries = pendingState
        .filter((invite) => invite.isNew && !invite.remove)
        .map((invite) => ({
            key: invite.key,
            data: {
                email: invite.email,
                name:
                    normalizeName(invite.nameDraft) !== ''
                        ? normalizeName(invite.nameDraft)
                        : null,
                role: invite.roleDraft,
                affiliation: normalizeAffiliation(invite.affiliationDraft),
            },
        }));

    return {
        payload: {
            create: createEntries.map((entry) => entry.data),
            update: updateEntries.map((entry) => entry.data),
            delete: deleteEntries,
            pending_update: pendingUpdateEntries.map((entry) => entry.data),
            pending_delete: pendingDeleteEntries,
        },
        mapping: {
            createKeys: createEntries.map((entry) => entry.key),
            updateIds: updateEntries.map((entry) => entry.id),
            deleteIds: deleteEntries,
            pendingUpdateIds: pendingUpdateEntries.map((entry) => entry.id),
            pendingDeleteIds: pendingDeleteEntries,
        },
    };
});

const membershipPayload = computed(
    () => membershipPayloadDetails.value.payload,
);

const memberErrorMap = computed(() => {
    const map: Record<
        string,
        { role?: string; delete?: string; affiliation?: string }
    > = {};
    const { mapping } = membershipPayloadDetails.value;

    Object.entries(form.errors).forEach(([key, message]) => {
        const updateMatch = key.match(/^memberships\.update\.(\d+)\.(.+)$/);
        if (updateMatch) {
            const index = Number.parseInt(updateMatch[1] ?? '', 10);
            const field = updateMatch[2] as 'role' | 'affiliation';
            const targetId = mapping.updateIds[index];
            if (targetId) {
                map[targetId] = map[targetId] ?? {};
                map[targetId][field] = message as string;
            }
            return;
        }

        const deleteMatch = key.match(/^memberships\.delete\.(\d+)$/);
        if (deleteMatch) {
            const index = Number.parseInt(deleteMatch[1] ?? '', 10);
            const targetId = mapping.deleteIds[index];
            if (targetId) {
                map[targetId] = map[targetId] ?? {};
                map[targetId].delete = message as string;
            }
        }
    });

    return map;
});

const pendingErrorMap = computed(() => {
    const map: Record<
        string,
        {
            role?: string;
            name?: string;
            delete?: string;
            email?: string;
            affiliation?: string;
        }
    > = {};
    const { mapping } = membershipPayloadDetails.value;

    Object.entries(form.errors).forEach(([key, message]) => {
        let match = key.match(/^memberships\.pending_update\.(\d+)\.(.+)$/);
        if (match) {
            const index = Number.parseInt(match[1] ?? '', 10);
            const field = match[2] as 'role' | 'name' | 'affiliation';
            const targetId = mapping.pendingUpdateIds[index];
            if (targetId) {
                map[targetId] = map[targetId] ?? {};
                map[targetId][field] = message as string;
            }
            return;
        }

        match = key.match(/^memberships\.pending_delete\.(\d+)$/);
        if (match) {
            const index = Number.parseInt(match[1] ?? '', 10);
            const targetId = mapping.pendingDeleteIds[index];
            if (targetId) {
                map[targetId] = map[targetId] ?? {};
                map[targetId].delete = message as string;
            }
            return;
        }

        match = key.match(/^memberships\.create\.(\d+)\.(.+)$/);
        if (match) {
            const index = Number.parseInt(match[1] ?? '', 10);
            const field = match[2] as 'email' | 'name' | 'role' | 'affiliation';
            const targetKey = mapping.createKeys[index];
            if (targetKey) {
                map[targetKey] = map[targetKey] ?? {};
                map[targetKey][field] = message as string;
            }
        }
    });

    return map;
});

const generalCreateError = computed(
    () => form.errors['memberships.create'] ?? null,
);

const pendingEntryKey = (invite: EditablePending) =>
    invite.isNew ? invite.key : (invite.id ?? invite.key);

const locales = ['en', 'uk', 'de', 'fr', 'es'];
const currencies = ['USD', 'EUR', 'UAH', 'GBP', 'CHF'];

const submit = () => {
    inviteFormError.value = null;

    const normalizedTimezone = toPreferredTimezoneName(
        form.settings_json.timezone ?? '',
    );

    form.transform((data) => ({
        ...data,
        settings_json: {
            ...data.settings_json,
            timezone: normalizedTimezone !== '' ? normalizedTimezone : null,
        },
        memberships: membershipPayload.value,
    }));

    form.put(resolveRoute(orgSettingsUpdate(props.organization.id)), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto w-full space-y-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Organization Settings</h1>
                <Button variant="outline" as-child>
                    <Link :href="orgShow(organization.id)">Back</Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ organization.name }}</CardTitle>
                    <CardDescription
                        >Adjust the basics used across your
                        Console.</CardDescription
                    >
                </CardHeader>

                <CardContent class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Acme, Inc."
                        />
                        <p v-if="form.errors.name" class="text-sm text-red-600">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="locale">Locale</Label>
                        <select
                            id="locale"
                            v-model="form.settings_json.locale"
                            class="w-full rounded border px-3 py-2"
                        >
                            <option v-for="l in locales" :key="l" :value="l">
                                {{ l }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors['settings_json.locale']"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors['settings_json.locale'] }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">Currency</Label>
                        <select
                            id="currency"
                            v-model="form.settings_json.currency"
                            class="w-full rounded border px-3 py-2"
                        >
                            <option v-for="c in currencies" :key="c" :value="c">
                                {{ c }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors['settings_json.currency']"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors['settings_json.currency'] }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">Scheduling timezone</Label>
                        <Input
                            id="timezone"
                            v-model="form.settings_json.timezone"
                            type="text"
                            list="timezone-options"
                            autocomplete="off"
                            placeholder="e.g. America/New_York"
                        />
                        <datalist id="timezone-options">
                            <option
                                v-for="timezone in timezones"
                                :key="timezone"
                                :value="timezone"
                            />
                        </datalist>
                        <p
                            v-if="
                                ((form.settings_json.timezone ?? '').trim()
                                    .length ?? 0) === 0
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Start typing to filter timezone options.
                        </p>
                        <p
                            v-if="form.errors['settings_json.timezone']"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors['settings_json.timezone'] }}
                        </p>
                    </div>

                    <div v-if="flashSuccess" class="text-sm text-green-700">
                        {{ flashSuccess }}
                    </div>
                </CardContent>

                <CardFooter class="flex justify-end gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="orgShow(organization.id)">Cancel</Link>
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        <span v-if="form.processing">Saving…</span>
                        <span v-else>Save</span>
                    </Button>
                </CardFooter>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Members</CardTitle>
                    <CardDescription
                        >Manage access for teammates already in this
                        organization.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label class="font-medium" for="member-search"
                                >Search members</Label
                            >
                            <Input
                                id="member-search"
                                v-model="memberSearch"
                                placeholder="Filter by name, email, or affiliation"
                            />
                        </div>
                        <p class="self-end text-sm text-muted-foreground">
                            Owners can promote other members. Removing the last
                            owner is not allowed.
                        </p>
                    </div>

                    <div
                        v-if="filteredMembers.length === 0"
                        class="rounded border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        No members found.
                    </div>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="member in filteredMembers"
                            :key="member.id"
                            :class="[
                                'flex flex-col gap-2 rounded border p-3 md:flex-row md:items-center md:justify-between',
                                member.remove
                                    ? 'border-red-200 bg-red-50/60 dark:border-red-900/50 dark:bg-red-950/40'
                                    : '',
                            ]"
                        >
                            <div class="space-y-1">
                                <p class="flex items-center gap-2 font-medium">
                                    <span
                                        :class="
                                            member.remove ? 'line-through' : ''
                                        "
                                        >{{ member.name }}</span
                                    >
                                    <span
                                        v-if="member.remove"
                                        class="text-xs font-medium text-red-600"
                                        >Will be removed</span
                                    >
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ member.email }}
                                </p>
                                <p
                                    v-if="memberErrorMap[member.id]?.delete"
                                    class="text-sm text-red-600"
                                >
                                    {{ memberErrorMap[member.id]?.delete }}
                                </p>
                            </div>
                            <div
                                class="flex flex-col items-start gap-2 md:flex-row md:items-center md:gap-4"
                            >
                                <div class="flex w-full flex-col md:w-56">
                                    <Input
                                        v-model="member.affiliationDraft"
                                        placeholder="Affiliation (optional)"
                                        :disabled="member.remove"
                                    />
                                    <p
                                        v-if="
                                            memberErrorMap[member.id]
                                                ?.affiliation
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            memberErrorMap[member.id]
                                                ?.affiliation
                                        }}
                                    </p>
                                </div>
                                <div class="flex flex-col">
                                    <select
                                        v-model="member.roleDraft"
                                        class="rounded border px-3 py-2"
                                        :disabled="member.remove"
                                    >
                                        <option
                                            v-for="role in props.roles"
                                            :key="role.value"
                                            :value="role.value"
                                        >
                                            {{ role.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="memberErrorMap[member.id]?.role"
                                        class="text-sm text-red-600"
                                    >
                                        {{ memberErrorMap[member.id]?.role }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    @click="toggleMemberRemoval(member)"
                                >
                                    {{
                                        member.remove
                                            ? 'Undo removal'
                                            : 'Remove'
                                    }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Invitations</CardTitle>
                    <CardDescription
                        >Invite collaborators who do not yet have
                        access.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label class="font-medium" for="invite-email"
                                >Email</Label
                            >
                            <Input
                                id="invite-email"
                                v-model="newInvite.email"
                                type="email"
                                placeholder="teammate@example.com"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label class="font-medium" for="invite-name"
                                >Name (optional)</Label
                            >
                            <Input
                                id="invite-name"
                                v-model="newInvite.name"
                                placeholder="Optional name"
                            />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label class="font-medium" for="invite-affiliation"
                                >Affiliation (optional)</Label
                            >
                            <Input
                                id="invite-affiliation"
                                v-model="newInvite.affiliation"
                                placeholder="Optional affiliation"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label class="font-medium" for="invite-role"
                                >Role</Label
                            >
                            <select
                                id="invite-role"
                                v-model="newInvite.role"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option
                                    v-for="role in props.roles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <Button
                                type="button"
                                :disabled="form.processing"
                                @click="addInvite"
                            >
                                <span v-if="form.processing">Adding…</span>
                                <span v-else>Add invite</span>
                            </Button>
                        </div>
                    </div>

                    <p v-if="inviteFormError" class="text-sm text-red-600">
                        {{ inviteFormError }}
                    </p>
                    <p
                        v-else-if="generalCreateError"
                        class="text-sm text-red-600"
                    >
                        {{ generalCreateError }}
                    </p>

                    <div class="space-y-3">
                        <div
                            class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="space-y-1">
                                <Label class="font-medium" for="pending-search"
                                    >Pending invites</Label
                                >
                                <Input
                                    id="pending-search"
                                    v-model="pendingSearch"
                                    placeholder="Search pending invites by name, email, or affiliation"
                                />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Pending invites can be updated or cancelled.
                            </p>
                        </div>

                        <div
                            v-if="filteredPending.length === 0"
                            class="rounded border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            No pending invites.
                        </div>

                        <ul v-else class="space-y-3">
                            <li
                                v-for="invite in filteredPending"
                                :key="invite.key"
                                :class="[
                                    'flex flex-col gap-2 rounded border p-3 md:flex-row md:items-center md:justify-between',
                                    invite.isNew
                                        ? 'border-blue-200 bg-blue-50/60 dark:border-blue-900/50 dark:bg-blue-950/40'
                                        : '',
                                    invite.remove && !invite.isNew
                                        ? 'border-red-200 bg-red-50/60 dark:border-red-900/50 dark:bg-red-950/40'
                                        : '',
                                ]"
                            >
                                <div class="space-y-1">
                                    <p
                                        class="flex items-center gap-2 font-medium"
                                    >
                                        <span
                                            :class="
                                                invite.remove && !invite.isNew
                                                    ? 'line-through'
                                                    : ''
                                            "
                                        >
                                            {{
                                                normalizeName(
                                                    invite.nameDraft,
                                                ) ||
                                                normalizeName(
                                                    invite.originalName,
                                                ) ||
                                                invite.email
                                            }}
                                        </span>
                                        <span
                                            v-if="invite.isNew"
                                            class="text-xs font-medium text-blue-600"
                                            >New</span
                                        >
                                        <span
                                            v-if="
                                                invite.remove && !invite.isNew
                                            "
                                            class="text-xs font-medium text-red-600"
                                            >Will be removed</span
                                        >
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ invite.email }}
                                    </p>
                                    <p
                                        v-if="
                                            formatDateTime(invite.sentAt) ||
                                            formatDateTime(invite.expiresAt)
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="formatDateTime(invite.sentAt)"
                                        >
                                            Sent
                                            {{ formatDateTime(invite.sentAt) }}
                                        </span>
                                        <span
                                            v-if="
                                                formatDateTime(invite.sentAt) &&
                                                formatDateTime(invite.expiresAt)
                                            "
                                        >
                                            •
                                        </span>
                                        <span
                                            v-if="
                                                formatDateTime(invite.expiresAt)
                                            "
                                        >
                                            Expires
                                            {{
                                                formatDateTime(invite.expiresAt)
                                            }}
                                        </span>
                                    </p>
                                    <p
                                        v-if="
                                            pendingErrorMap[
                                                pendingEntryKey(invite)
                                            ]?.email
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            pendingErrorMap[
                                                pendingEntryKey(invite)
                                            ]?.email
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            pendingErrorMap[
                                                pendingEntryKey(invite)
                                            ]?.name
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            pendingErrorMap[
                                                pendingEntryKey(invite)
                                            ]?.name
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-col items-start gap-2 md:flex-row md:items-center md:gap-4"
                                >
                                    <div class="flex w-full flex-col md:w-56">
                                        <Input
                                            v-model="invite.affiliationDraft"
                                            placeholder="Affiliation (optional)"
                                            :disabled="
                                                invite.remove && !invite.isNew
                                            "
                                        />
                                        <p
                                            v-if="
                                                pendingErrorMap[
                                                    pendingEntryKey(invite)
                                                ]?.affiliation
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                pendingErrorMap[
                                                    pendingEntryKey(invite)
                                                ]?.affiliation
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col">
                                        <select
                                            v-model="invite.roleDraft"
                                            class="rounded border px-3 py-2"
                                            :disabled="
                                                invite.remove && !invite.isNew
                                            "
                                        >
                                            <option
                                                v-for="role in props.roles"
                                                :key="role.value"
                                                :value="role.value"
                                            >
                                                {{ role.label }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                pendingErrorMap[
                                                    pendingEntryKey(invite)
                                                ]?.role
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                pendingErrorMap[
                                                    pendingEntryKey(invite)
                                                ]?.role
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-start">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            @click="
                                                togglePendingRemoval(invite)
                                            "
                                        >
                                            <span v-if="invite.isNew"
                                                >Remove</span
                                            >
                                            <span v-else>{{
                                                invite.remove
                                                    ? 'Undo removal'
                                                    : 'Cancel invite'
                                            }}</span>
                                        </Button>
                                        <p
                                            v-if="
                                                pendingErrorMap[
                                                    pendingEntryKey(invite)
                                                ]?.delete
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                pendingErrorMap[
                                                    pendingEntryKey(invite)
                                                ]?.delete
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

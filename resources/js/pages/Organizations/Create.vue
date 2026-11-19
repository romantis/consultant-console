<script setup lang="ts">
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import org, { index as orgIndex, store as orgStore } from '@/routes/org';
import type { BreadcrumbItem } from '@/types';

type MemberDraft = {
    email: string;
    name: string;
    role: string;
    affiliation: string;
};

const props = defineProps<{
    roles: { value: string; label: string }[];
}>();

const form = useForm({
    name: '',
    slug: '',
    memberships: {
        create: [] as MemberDraft[],
    },
});

const newMember = reactive<MemberDraft>({
    email: '',
    name: '',
    role:
        props.roles.find((role) => role.value === 'member')?.value ??
        props.roles[0]?.value ??
        'member',
    affiliation: '',
});

const memberSearch = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: org.index().url,
    },
];

const addMember = () => {
    if (!newMember.email) {
        return;
    }

    const normalizedEmail = newMember.email.trim().toLowerCase();
    const exists = form.memberships.create.some(
        (member) => member.email.toLowerCase() === normalizedEmail,
    );

    if (exists) {
        form.setError('memberships.create', 'Member already added.');
        return;
    }

    form.clearErrors('memberships.create');

    form.memberships.create.push({
        email: normalizedEmail,
        name: newMember.name.trim(),
        role: newMember.role,
        affiliation: newMember.affiliation.trim(),
    });

    newMember.email = '';
    newMember.name = '';
    newMember.role =
        props.roles.find((role) => role.value === 'member')?.value ??
        newMember.role;
    newMember.affiliation = '';
};

const removeMember = (index: number) => {
    form.memberships.create.splice(index, 1);
};

const filteredMembers = computed(() => {
    const entries = form.memberships.create.map((member, index) => ({
        member,
        index,
    }));

    if (!memberSearch.value) {
        return entries;
    }

    const query = memberSearch.value.toLowerCase();
    return entries.filter(
        ({ member }) =>
            member.email.toLowerCase().includes(query) ||
            (member.name?.toLowerCase()?.includes(query) ?? false) ||
            (member.affiliation?.toLowerCase().includes(query) ?? false),
    );
});

const memberErrors = computed(() => {
    const errors: Record<
        number,
        Partial<Record<keyof MemberDraft, string>>
    > = {};

    Object.entries(form.errors).forEach(([key, message]) => {
        const match = key.match(/^memberships\.create\.(\d+)\.(.+)$/);
        if (!match) return;

        const index = Number.parseInt(match[1] ?? '', 10);
        const field = match[2] as keyof MemberDraft;

        if (!Number.isNaN(index)) {
            errors[index] = errors[index] ?? {};
            errors[index][field] = message as string;
        }
    });

    return errors;
});

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

const submit = () => {
    form.post(resolveRoute(orgStore()), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Organization" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>Create Organization</CardTitle>
                    <CardDescription
                        >Give it a name; slug is optional.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Acme, Inc."
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug (optional)</Label>
                        <Input
                            id="slug"
                            v-model="form.slug"
                            placeholder="acme"
                        />
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <h2 class="text-lg font-semibold">Team</h2>
                            <p class="text-sm text-muted-foreground">
                                Add organization members now or invite them
                                later.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-3">
                                <Label class="font-medium"
                                    >Search members</Label
                                >
                                <Input
                                    v-model="memberSearch"
                                    placeholder="Filter by name, email, or affiliation"
                                />
                                <p
                                    v-if="form.errors['memberships.create']"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors['memberships.create'] }}
                                </p>
                            </div>
                            <div class="space-y-3">
                                <Label class="font-medium">Add member</Label>
                                <div class="space-y-2">
                                    <Input
                                        v-model="newMember.email"
                                        placeholder="teammate@example.com"
                                    />
                                    <Input
                                        v-model="newMember.name"
                                        placeholder="Optional name"
                                    />
                                    <Input
                                        v-model="newMember.affiliation"
                                        placeholder="Optional affiliation"
                                    />
                                    <select
                                        v-model="newMember.role"
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
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="addMember"
                                    >Add</Button
                                >
                            </div>
                        </div>

                        <div class="space-y-2">
                            <h3
                                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Pending members
                            </h3>
                            <div
                                v-if="filteredMembers.length === 0"
                                class="rounded border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                No additional members yet.
                            </div>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="entry in filteredMembers"
                                    :key="entry.member.email"
                                    class="flex flex-col gap-2 rounded border p-3 md:flex-row md:items-center md:justify-between"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{
                                                entry.member.name ||
                                                entry.member.email
                                            }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ entry.member.email }}
                                        </p>
                                        <p
                                            v-if="
                                                memberErrors[entry.index]?.email
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                memberErrors[entry.index]?.email
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                memberErrors[entry.index]?.name
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                memberErrors[entry.index]?.name
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex flex-col items-start gap-2 md:flex-row md:items-center md:gap-4"
                                    >
                                        <div class="w-full md:w-48">
                                            <Input
                                                v-model="
                                                    form.memberships.create[
                                                        entry.index
                                                    ].affiliation
                                                "
                                                placeholder="Optional affiliation"
                                            />
                                            <p
                                                v-if="
                                                    memberErrors[entry.index]
                                                        ?.affiliation
                                                "
                                                class="text-sm text-red-600"
                                            >
                                                {{
                                                    memberErrors[entry.index]
                                                        ?.affiliation
                                                }}
                                            </p>
                                        </div>
                                        <select
                                            v-model="
                                                form.memberships.create[
                                                    entry.index
                                                ].role
                                            "
                                            class="rounded border px-3 py-2"
                                        >
                                            <option
                                                v-for="role in props.roles"
                                                :key="role.value"
                                                :value="role.value"
                                            >
                                                {{ role.label }}
                                            </option>
                                        </select>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            @click="removeMember(entry.index)"
                                            >Remove</Button
                                        >
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button variant="outline" as-child>
                            <Link :href="orgIndex()">Cancel</Link>
                        </Button>
                        <Button :disabled="form.processing" @click="submit">
                            <span v-if="form.processing">Saving…</span>
                            <span v-else>Save</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

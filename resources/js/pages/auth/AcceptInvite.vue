<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    email: string;
    organization?: { name?: string | null } | null;
    acceptUrl: string;
    expiresAt?: string | null;
    sso?: {
        hint?: string | null;
        providers?: Array<{
            label: string;
            loginUrl: string;
            provider?: string | null;
            help?: string | null;
        }>;
    } | null;
}>();

const organizationName = computed(
    () => props.organization?.name ?? 'this organization',
);

const description = computed(
    () => `Join ${organizationName.value} on the MH Console.`,
);

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const ssoProviders = computed(() => props.sso?.providers ?? []);
const hasSso = computed(() => ssoProviders.value.length > 0);
const ssoHint = computed(() => props.sso?.hint ?? null);

const submit = () => {
    form.post(props.acceptUrl);
};
</script>

<template>
    <AuthBase title="Complete your invitation" :description="description">
        <Head title="Accept invitation" />

        <div class="space-y-4 text-sm text-muted-foreground">
            <p>
                You've been invited to join
                <strong class="text-foreground">{{ organizationName }}</strong>
                as <strong>{{ email }}</strong
                >.
            </p>
            <p>
                Create your account below to continue.
                <template v-if="hasSso">
                    Prefer using your organization's identity provider? Choose
                    the single sign-on option below.
                </template>
                If you already have an account with this email, you can
                <TextLink :href="login()">sign in</TextLink>
                instead.
            </p>
            <p v-if="expiresAt">
                This invitation link expires on {{ expiresAt }}.
            </p>
        </div>

        <section v-if="hasSso" class="mt-8 space-y-4">
            <div class="grid gap-3">
                <div
                    v-for="provider in ssoProviders"
                    :key="provider.loginUrl"
                    class="space-y-2"
                >
                    <Button
                        as="a"
                        :href="provider.loginUrl"
                        variant="outline"
                        class="w-full justify-center"
                        :data-provider="provider.provider ?? undefined"
                    >
                        {{ provider.label }}
                    </Button>
                    <p
                        v-if="provider.help"
                        class="text-center text-xs text-muted-foreground"
                    >
                        {{ provider.help }}
                    </p>
                </div>
            </div>

            <p v-if="ssoHint" class="text-center text-xs text-muted-foreground">
                {{ ssoHint }}
            </p>

            <div
                class="flex items-center gap-3 text-xs tracking-wide text-muted-foreground uppercase"
            >
                <span class="grow border-t border-border" />
                <span>Or create an account</span>
                <span class="grow border-t border-border" />
            </div>
        </section>

        <form
            class="mt-8 flex flex-col gap-6"
            @submit.prevent="submit"
            novalidate
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        name="name"
                        autocomplete="name"
                        placeholder="Full name"
                        required
                        autofocus
                        :tabindex="1"
                        v-model="form.name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="Password"
                        required
                        :tabindex="2"
                        v-model="form.password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        required
                        :tabindex="3"
                        v-model="form.password_confirmation"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                    :tabindex="4"
                >
                    Create account and join
                </Button>
            </div>
        </form>
    </AuthBase>
</template>

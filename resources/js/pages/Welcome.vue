<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { login as loginRoute, register as registerRoute } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const flags = page.props.flags;
</script>

<template>
    <div class="grid min-h-screen place-items-center p-6">
        <Card class="w-full max-w-xl">
            <CardHeader>
                <CardTitle>Metric Harmony Console</CardTitle>
                <CardDescription
                    >Align metrics. Ship the Handbook. Stay in
                    sync.</CardDescription
                >
            </CardHeader>
            <CardContent class="flex gap-3">
                <Button as-child
                    ><Link :href="loginRoute()">Log in</Link></Button
                >
                <Button
                    v-if="flags?.allow_public_registration"
                    variant="outline"
                    as-child
                    ><Link :href="registerRoute()">Register</Link></Button
                >
            </CardContent>
        </Card>
    </div>
</template>

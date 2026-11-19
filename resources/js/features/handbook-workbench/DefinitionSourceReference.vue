<script setup lang="ts">
interface Item {
    key: string;
    label: string;
    value: string | number | boolean | null;
    companions?: {
        key: string;
        label: string;
        value: string | number | boolean | null;
    }[];
}

const props = defineProps<{
    items?: Item[] | null;
    emptyText: string;
}>();

function formatValue(value: string | number | boolean | null): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    return String(value);
}
</script>

<template>
    <div class="space-y-2.5 text-foreground/90">
        <template v-if="props.items && props.items.length > 0">
            <div
                v-for="item in props.items"
                :key="item.key"
                class="space-y-0.5"
            >
                <div class="font-semibold text-muted-foreground/80">
                    {{ item.label }}
                </div>
                <div class="whitespace-pre-line text-foreground/90">
                    {{ formatValue(item.value) || '—' }}
                </div>
                <template v-if="item.companions && item.companions.length > 0">
                    <div
                        v-for="companion in item.companions"
                        :key="companion.key"
                        class="mt-1 border-l-2 border-border/40 pl-2 text-[11px]"
                    >
                        <div class="font-semibold text-muted-foreground/70">
                            {{ companion.label }}
                        </div>
                        <div class="whitespace-pre-line text-foreground/80">
                            {{ formatValue(companion.value) || '—' }}
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <div v-else class="text-muted-foreground/60 italic">
            {{ props.emptyText }}
        </div>
    </div>
</template>

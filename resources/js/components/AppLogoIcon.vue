<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
        <!-- Three vertical bars -->
        <rect x="30" y="120" width="35" height="50" fill="#0280be" />
        <rect x="85" y="90" width="35" height="80" fill="#0280be" />
        <rect x="140" y="70" width="35" height="100" fill="#0280be" />

        <!-- Line connecting dots -->
        <!-- <circle cx="70" cy="17" r="4" fill="red" /> -->
        <path
            d="M47 90 C 70 17, 155 42, 155 42"
            stroke="#0280be"
            stroke-width="20"
            fill="none"
        />

        <!-- Two dots -->
        <circle cx="47" cy="90" r="18" fill="#0280be" />
        <circle cx="155" cy="42" r="18" fill="#0280be" />
    </svg>
</template>

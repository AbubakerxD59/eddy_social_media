<script setup lang="ts">
import { CircleCheck, Circle } from '@lucide/vue';
import { computed } from 'vue';
import { passwordChecks } from '@/lib/passwordStrength';

const { password, confirmation = '' } = defineProps<{
    password: string;
    confirmation?: string;
}>();

const checks = computed(() => [
    ...passwordChecks(password),
    {
        id: 'match',
        passed: confirmation.length > 0 && password === confirmation,
        label: 'Match',
    },
]);
</script>

<template>
    <ul
        class="mt-2 grid grid-cols-3 gap-x-2 gap-y-1.5"
        aria-live="polite"
    >
        <li
            v-for="check in checks"
            :key="check.id"
            class="flex min-w-0 items-center gap-1.5 text-sm leading-tight"
            :class="check.passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
        >
            <CircleCheck v-if="check.passed" class="size-3.5 shrink-0" />
            <Circle v-else class="size-3.5 shrink-0" />
            <span class="truncate">{{ check.label }}</span>
        </li>
    </ul>
</template>

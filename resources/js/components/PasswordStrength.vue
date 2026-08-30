<script setup lang="ts">
import { CircleCheck, Circle } from '@lucide/vue';
import { computed } from 'vue';
import { passwordChecks } from '@/lib/passwordStrength';

const { password, confirmation = '' } = defineProps<{
    password: string;
    confirmation?: string;
}>();

const checks = computed(() => passwordChecks(password));

const confirmationCheck = computed(() => ({
    passed: confirmation.length > 0 && password === confirmation,
    label: 'Passwords match',
}));
</script>

<template>
    <ul class="mt-2 space-y-1.5" aria-live="polite">
        <li
            v-for="check in checks"
            :key="check.id"
            class="flex items-center gap-2 text-xs"
            :class="check.passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
        >
            <CircleCheck v-if="check.passed" class="size-3.5 shrink-0" />
            <Circle v-else class="size-3.5 shrink-0" />
            {{ check.label }}
        </li>
        <li
            class="flex items-center gap-2 text-xs"
            :class="
                confirmationCheck.passed
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-muted-foreground'
            "
        >
            <CircleCheck v-if="confirmationCheck.passed" class="size-3.5 shrink-0" />
            <Circle v-else class="size-3.5 shrink-0" />
            {{ confirmationCheck.label }}
        </li>
    </ul>
</template>

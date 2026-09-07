<script setup lang="ts">
import { computed } from 'vue';
import type { SignalNeed, SignalOpportunity } from '@/types/social';

const { need, opportunity } = defineProps<{
    need?: SignalNeed | null;
    opportunity?: SignalOpportunity | null;
}>();

const fields = computed(() => {
    if (need) {
        return [
            { label: 'Budget', value: need.budget },
            { label: 'Timeline', value: need.timeline },
            { label: 'Location', value: need.location },
            { label: 'Skills', value: need.skills.join(', ') || null },
        ].filter((field) => field.value);
    }

    if (opportunity) {
        return [
            { label: 'Project value', value: opportunity.project_value },
            { label: 'Timeline', value: opportunity.timeline },
            { label: 'Location', value: opportunity.location },
            { label: 'Trades needed', value: opportunity.trades.join(', ') || null },
        ].filter((field) => field.value);
    }

    return [];
});
</script>

<template>
    <div
        v-if="fields.length"
        class="grid grid-cols-2 gap-2"
        data-no-nav
    >
        <div
            v-for="field in fields"
            :key="field.label"
            class="bg-background/50 rounded-xl border px-3 py-2.5"
        >
            <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
                {{ field.label }}
            </p>
            <p class="mt-0.5 text-[13px] leading-snug font-medium">
                {{ field.value }}
            </p>
        </div>
    </div>
</template>

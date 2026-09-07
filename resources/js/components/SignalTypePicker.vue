<script setup lang="ts">
import { BarChart3, Briefcase, Sparkles, UserPlus } from '@lucide/vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';
import { SIGNAL_TYPES } from '@/lib/signalTypes';
import type { SignalType } from '@/types/social';

const { modelValue = null, compact = false } = defineProps<{
    modelValue?: SignalType | null;
    compact?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SignalType];
}>();

const icons: Record<SignalType, Component> = {
    drop: Sparkles,
    need: Briefcase,
    opportunity: UserPlus,
    poll: BarChart3,
};

const iconWrap: Record<SignalType, string> = {
    drop: 'bg-drop/15 text-drop',
    need: 'bg-need/15 text-need',
    opportunity: 'bg-opportunity/15 text-opportunity',
    poll: 'bg-poll/15 text-poll',
};
</script>

<template>
    <div class="space-y-3">
        <p
            v-if="!compact"
            class="text-[15px] font-semibold"
        >
            What's your mission today?
        </p>

        <div
            class="grid gap-2"
            :class="compact ? 'grid-cols-4' : 'grid-cols-2 sm:grid-cols-4'"
            role="radiogroup"
            aria-label="Signal type"
        >
            <button
                v-for="item in SIGNAL_TYPES"
                :key="item.value"
                type="button"
                role="radio"
                class="flex cursor-pointer flex-col items-start gap-2 rounded-2xl border px-3 py-3 text-left transition-all"
                :class="
                    cn(
                        modelValue === item.value
                            ? item.selectedClass + ' shadow-[0_0_22px_hsl(252_56%_57%/0.12)]'
                            : 'border-border bg-background/40 hover:bg-accent/40',
                        compact && 'items-center px-1.5 py-2',
                    )
                "
                :aria-checked="modelValue === item.value"
                @click="emit('update:modelValue', item.value)"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-xl"
                    :class="iconWrap[item.value]"
                >
                    <component
                        :is="icons[item.value]"
                        class="size-4 shrink-0"
                    />
                </span>
                <span>
                    <span class="block text-[13px] font-semibold" :class="item.accentClass">
                        {{ item.label }}
                    </span>
                    <span
                        v-if="!compact"
                        class="text-muted-foreground mt-0.5 line-clamp-2 block text-[11px] leading-snug"
                    >
                        {{ item.description }}
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>

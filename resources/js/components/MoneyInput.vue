<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    class?: HTMLAttributes['class'];
    defaultValue?: string | number;
}>();

const model = defineModel<string | number>();
</script>

<template>
    <div class="relative min-w-0">
        <span
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 z-10 -translate-y-1/2 text-sm font-medium"
            aria-hidden="true"
        >
            $
        </span>
        <Input
            v-bind="$attrs"
            v-model="model"
            :default-value="defaultValue"
            :class="cn('pl-7', props.class)"
        />
    </div>
</template>

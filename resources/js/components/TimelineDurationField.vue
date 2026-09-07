<script setup lang="ts">
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TIMELINE_UNIT_OPTIONS, type TimelineUnit } from '@/lib/timeline';

const amount = defineModel<string>('amount', { default: '' });
const unit = defineModel<TimelineUnit>('unit', { default: 'days' });
</script>

<template>
    <div class="flex min-w-0">
        <Input
            v-model="amount"
            type="number"
            min="1"
            step="1"
            inputmode="numeric"
            placeholder="Timeline"
            aria-label="Timeline"
            class="h-9 min-w-0 rounded-r-none"
        />
        <Select v-model="unit">
            <SelectTrigger
                aria-label="Duration"
                class="h-9 w-[7.25rem] shrink-0 cursor-pointer rounded-l-none border-l-0"
            >
                <SelectValue placeholder="Duration" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="option in TIMELINE_UNIT_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>

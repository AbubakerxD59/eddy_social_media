<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

const props = defineProps<{
    name: string;
    avatar?: string | null;
    href?: string;
    showLine?: boolean;
    class?: string;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'flex w-9 shrink-0 flex-col items-center self-stretch',
                props.class,
            )
        "
    >
        <component
            :is="href ? Link : 'div'"
            v-bind="href ? { href } : {}"
            class="shrink-0"
        >
            <Avatar class="size-9">
                <AvatarImage v-if="avatar" :src="avatar" :alt="name" />
                <AvatarFallback class="text-xs">
                    {{ getInitials(name) }}
                </AvatarFallback>
            </Avatar>
        </component>
        <div
            v-if="showLine"
            class="bg-border mt-2 w-px flex-1 rounded-full"
        />
    </div>
</template>

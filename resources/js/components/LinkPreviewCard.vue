<script setup lang="ts">
import type { SignalLink } from '@/types/social';

defineProps<{
    link: SignalLink;
}>();
</script>

<template>
    <a
        :href="link.url"
        target="_blank"
        rel="noopener noreferrer"
        class="block cursor-pointer overflow-hidden rounded-2xl border transition-colors hover:bg-accent/40"
        data-no-nav
        @click.stop
    >
        <img
            v-if="link.image"
            :src="link.image"
            :alt="link.title ?? ''"
            class="aspect-[1.91/1] w-full object-cover"
        />
        <div class="space-y-1 p-3">
            <p class="text-muted-foreground truncate text-xs">
                {{ link.url }}
            </p>
            <p v-if="link.title" class="font-medium">{{ link.title }}</p>
            <p
                v-if="link.description"
                class="text-muted-foreground line-clamp-2 text-sm"
            >
                {{ link.description }}
            </p>
        </div>
    </a>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { ChatReplyQuote } from '@/types/messages';

const props = defineProps<{
    quote: ChatReplyQuote;
    author: string;
    own?: boolean;
}>();

const emit = defineEmits<{
    select: [];
}>();

const preview = computed(() => {
    if (props.quote.deleted) {
        return 'Deleted message';
    }

    if (props.quote.body) {
        return props.quote.body;
    }

    if (props.quote.kind === 'image') {
        return 'Photo';
    }

    if (props.quote.kind === 'video') {
        return 'Video';
    }

    return props.quote.original_name || 'File';
});
</script>

<template>
    <button
        type="button"
        class="mb-1 flex w-full cursor-pointer items-stretch gap-2 overflow-hidden rounded-lg border-0 px-2 py-1.5 text-left outline-none"
        :class="
            own
                ? 'bg-black/15 text-primary-foreground'
                : 'bg-background/70 text-foreground'
        "
        @click="emit('select')"
    >
        <span
            class="w-0.5 shrink-0 rounded-full"
            :class="own ? 'bg-primary-foreground' : 'bg-primary'"
        />
        <span class="min-w-0 flex-1">
            <span class="block truncate text-xs font-semibold">
                {{ author }}
            </span>
            <span
                class="block truncate text-xs"
                :class="own ? 'opacity-80' : 'text-muted-foreground'"
            >
                {{ preview }}
            </span>
        </span>
        <img
            v-if="quote.kind === 'image' && quote.url"
            :src="quote.url"
            alt=""
            class="size-10 shrink-0 rounded object-cover"
        />
        <video
            v-else-if="quote.kind === 'video' && quote.url"
            :src="quote.url"
            muted
            preload="metadata"
            class="size-10 shrink-0 rounded object-cover"
        />
    </button>
</template>

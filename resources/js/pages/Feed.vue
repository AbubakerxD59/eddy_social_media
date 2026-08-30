<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SignalCard from '@/components/SignalCard.vue';
import SignalComposer from '@/components/SignalComposer.vue';
import type { FeedSignal, Paginator } from '@/types/social';

const props = defineProps<{
    signals?: Paginator<FeedSignal> | null;
    highlight?: string | null;
}>();

const page = computed<Paginator<FeedSignal>>(() => props.signals ?? {
    data: [],
    next_page_url: null,
    prev_page_url: null,
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const items = computed(() => page.value.data);

const continuesSignal = (index: number): boolean => {
    const current = items.value[index];
    const next = items.value[index + 1];

    return Boolean(current && next && current.author.id === next.author.id);
};
</script>

<template>
    <Head title="Home" />

    <div
        class="mx-auto flex min-h-0 w-full max-w-3xl flex-1 flex-col overflow-hidden px-4 pb-6"
    >
        <div class="shrink-0 py-3">
            <h1 class="text-[17px] font-semibold">Home</h1>
        </div>

        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain rounded-2xl border [&>:last-child]:border-b-0"
        >
            <SignalComposer />

            <SignalCard
                v-for="(signal, index) in items"
                :key="signal.id"
                :signal="signal"
                :highlighted="props.highlight === signal.id"
                :show-line="continuesSignal(index)"
            />

            <div
                v-if="items.length === 0"
                class="text-muted-foreground px-4 py-16 text-center text-sm"
            >
                No signals yet. Start the conversation.
            </div>

            <div
                v-if="page.next_page_url"
                class="flex justify-center py-6"
            >
                <Link
                    :href="page.next_page_url"
                    class="text-sm font-medium underline underline-offset-4"
                >
                    Load more
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import SpotlightStories from '@/components/dashboard/SpotlightStories.vue';
import SignalCard from '@/components/SignalCard.vue';
import SignalComposer from '@/components/SignalComposer.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { FeedSignal, Paginator, SignalType } from '@/types/social';

type FeedFilter = 'for-you' | 'connections' | 'opportunity' | 'need';

const PAGE_SIZE = 20;
const PREFETCH_AT = 15;

const props = defineProps<{
    signals?: Paginator<FeedSignal> | null;
    highlight?: string | null;
    compose?: SignalType | null;
    activeFilter?: FeedFilter;
    activeType?: SignalType | null;
}>();

const replacing = ref(false);
const pendingFilter = ref<FeedFilter | null>(null);
const prefetchSentinel = ref<HTMLElement | null>(null);
let replacingVisitId: string | null = null;

const activeFilter = computed<FeedFilter>(() => pendingFilter.value ?? props.activeFilter ?? 'for-you');
const items = computed(() => (replacing.value ? [] : (props.signals?.data ?? [])));

const prefetchIndex = computed(() => {
    const count = items.value.length;

    if (count === 0) {
        return -1;
    }

    const lastPageStart = Math.floor((count - 1) / PAGE_SIZE) * PAGE_SIZE;

    return Math.min(lastPageStart + PREFETCH_AT - 1, count - 1);
});

const prefetchEndElement = (): HTMLElement | null => prefetchSentinel.value;

const setPrefetchSentinel = (el: Element | null) => {
    prefetchSentinel.value = el instanceof HTMLElement ? el : null;
};

const filters = computed(() => [
    { label: 'For You', value: 'for-you' as const, href: '/dashboard' },
    { label: 'Connections', value: 'connections' as const, href: '/dashboard?filter=connections' },
    { label: 'Opportunities', value: 'opportunity' as const, href: '/dashboard?filter=opportunity' },
    { label: 'Needs', value: 'need' as const, href: '/dashboard?filter=need' },
]);

const emptyMessage = computed(() => {
    switch (activeFilter.value) {
        case 'connections':
            return 'Posts from your connections will show up here.';
        case 'opportunity':
            return 'No opportunities yet.';
        case 'need':
            return 'No needs yet.';
        default:
            return 'No signals yet. Start the conversation.';
    }
});

const feedFilterFromUrl = (url: URL): FeedFilter => {
    const filter = url.searchParams.get('filter');

    if (filter === 'connections' || filter === 'opportunity' || filter === 'need' || filter === 'for-you') {
        return filter;
    }

    return 'for-you';
};

const isDashboardUrl = (url: URL): boolean => url.pathname.replace(/\/+$/, '') === '/dashboard';

const stopBefore = router.on('before', (event) => {
    const visit = event.detail.visit;

    if (visit.method !== 'get' || !isDashboardUrl(visit.url)) {
        return;
    }

    const currentUrl = new URL(window.location.href);
    const nextFilter = feedFilterFromUrl(visit.url);
    const currentFilter = feedFilterFromUrl(currentUrl);

    if (nextFilter === currentFilter) {
        return;
    }

    pendingFilter.value = nextFilter;
    replacing.value = true;
    replacingVisitId = visit.id;
    router.remember(undefined, 'inertia:infinite-scroll-data:signals');

    if (!visit.reset.includes('signals')) {
        visit.reset = [...visit.reset, 'signals'];
    }

    if (visit.only.length === 0) {
        visit.only = ['signals', 'activeFilter', 'activeType', 'highlight', 'compose'];
    }
});

const stopFinish = router.on('finish', (event) => {
    if (event.detail.visit.id !== replacingVisitId) {
        return;
    }

    if (event.detail.visit.cancelled || event.detail.visit.interrupted) {
        return;
    }

    replacingVisitId = null;
    replacing.value = false;
    pendingFilter.value = null;
});

onBeforeUnmount(() => {
    stopBefore();
    stopFinish();
});
</script>

<template>
    <Head title="Universe" />

    <div class="flex flex-col gap-4">
        <SignalComposer id="composer" :initial-type="compose" />

        <SpotlightStories />

        <nav
            class="glass-panel flex rounded-2xl px-1"
            role="tablist"
            aria-label="Feed filters"
        >
            <Link
                v-for="filter in filters"
                :key="filter.value"
                :href="filter.href"
                :only="['signals', 'activeFilter', 'activeType']"
                :reset="['signals']"
                preserve-scroll
                preserve-state
                role="tab"
                :aria-selected="activeFilter === filter.value"
                class="relative flex min-w-0 flex-1 cursor-pointer items-center justify-center px-2 py-3 text-[13px] font-semibold"
                :class="
                    cn(
                        activeFilter === filter.value
                            ? 'text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
            >
                <span class="truncate">{{ filter.label }}</span>
                <span
                    v-if="activeFilter === filter.value"
                    class="bg-primary absolute inset-x-3 -bottom-px h-0.5 rounded-full"
                />
            </Link>
        </nav>

        <div v-if="replacing" class="flex flex-col gap-4">
            <div
                v-for="n in 3"
                :key="n"
                class="glass-panel space-y-3 rounded-2xl px-4 py-4"
            >
                <div class="flex items-center gap-3">
                    <Skeleton class="size-10 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-3 w-32" />
                        <Skeleton class="h-3 w-20" />
                    </div>
                </div>
                <Skeleton class="h-16 w-full" />
            </div>
        </div>

        <Deferred v-else data="signals">
            <template #fallback>
                <div class="flex flex-col gap-4">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="glass-panel space-y-3 rounded-2xl px-4 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <Skeleton class="size-10 rounded-full" />
                            <div class="flex-1 space-y-2">
                                <Skeleton class="h-3 w-32" />
                                <Skeleton class="h-3 w-20" />
                            </div>
                        </div>
                        <Skeleton class="h-16 w-full" />
                    </div>
                </div>
            </template>

            <InfiniteScroll
                :key="activeFilter"
                data="signals"
                :buffer="1"
                :end-element="prefetchEndElement"
            >
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(signal, index) in items"
                        :key="signal.id"
                        :ref="index === prefetchIndex ? setPrefetchSentinel : undefined"
                    >
                        <SignalCard
                            :signal="signal"
                            :highlighted="props.highlight === signal.id"
                        />
                    </div>

                    <div
                        v-if="items.length === 0"
                        class="glass-panel text-muted-foreground rounded-2xl px-4 py-16 text-center text-sm"
                    >
                        {{ emptyMessage }}
                    </div>
                </div>
            </InfiniteScroll>
        </Deferred>
    </div>
</template>

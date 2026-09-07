<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount } from 'vue';
import AppTopBar from '@/components/dashboard/AppTopBar.vue';
import FeedNav from '@/components/dashboard/FeedNav.vue';
import FeedRail from '@/components/dashboard/FeedRail.vue';
import { Toaster } from '@/components/ui/sonner';
import { useLiveLocation } from '@/composables/useLiveLocation';

const page = usePage();
const showRail = computed(() => !String(page.component).startsWith('settings/'));

useLiveLocation();

const lockPageScroll = () => {
    document.documentElement.classList.add('dashboard-locked');
};

const unlockPageScroll = () => {
    document.documentElement.classList.remove('dashboard-locked');
};

if (typeof document !== 'undefined') {
    lockPageScroll();
}

onBeforeUnmount(unlockPageScroll);
</script>

<template>
    <div class="flex h-full max-h-full min-h-0 flex-col overflow-hidden bg-transparent">
        <AppTopBar />

        <div class="mx-auto flex min-h-0 w-full max-w-[1600px] flex-1 items-stretch gap-4 overflow-hidden px-3 pt-20 pb-4 lg:px-5">
            <div class="scrollbar-none hidden min-h-0 w-[260px] shrink-0 overflow-y-auto lg:block">
                <FeedNav />
            </div>

            <main class="scrollbar-thin-app min-h-0 min-w-0 flex-1 overflow-y-auto overscroll-contain pr-1">
                <slot />
            </main>

            <div
                v-if="showRail"
                class="scrollbar-none hidden min-h-0 w-[320px] shrink-0 overflow-y-auto xl:block"
            >
                <FeedRail />
            </div>
        </div>

        <Toaster />
    </div>
</template>

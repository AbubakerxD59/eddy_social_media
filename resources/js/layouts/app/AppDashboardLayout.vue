<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount } from 'vue';
import AppTopBar from '@/components/dashboard/AppTopBar.vue';
import FeedNav from '@/components/dashboard/FeedNav.vue';
import FeedRail from '@/components/dashboard/FeedRail.vue';
import NotificationToasts from '@/components/dashboard/NotificationToasts.vue';
import { Toaster } from '@/components/ui/sonner';
import { useLiveLocation } from '@/composables/useLiveLocation';

const page = usePage();
const isMessages = computed(() => String(page.component).startsWith('Messages/'));
const isProfile = computed(() => page.component === 'Profile/Show');
const showNav = computed(() => !isMessages.value && !isProfile.value);
const showRail = computed(
    () => !String(page.component).startsWith('settings/') && !isMessages.value && !isProfile.value,
);

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

        <div
            class="flex min-h-0 w-full flex-1 items-stretch overflow-hidden"
            :class="
                isProfile
                    ? 'pt-16'
                    : 'mx-auto max-w-[1600px] gap-4 px-3 pt-20 pb-4 lg:px-5'
            "
        >
            <div
                v-if="showNav"
                class="scrollbar-none hidden min-h-0 w-[260px] shrink-0 overflow-y-auto lg:block"
            >
                <FeedNav />
            </div>

            <main
                class="min-h-0 min-w-0 flex-1 overscroll-contain"
                :class="
                    isMessages
                        ? 'overflow-hidden pr-0'
                        : isProfile
                          ? 'overflow-y-auto overflow-x-hidden pr-0'
                          : 'scrollbar-thin-app overflow-y-auto pr-1'
                "
            >
                <slot />
            </main>

            <div
                v-if="showRail"
                class="scrollbar-none hidden min-h-0 w-[320px] shrink-0 overflow-y-auto xl:block"
            >
                <FeedRail />
            </div>
        </div>

        <NotificationToasts />
        <Toaster />
    </div>
</template>

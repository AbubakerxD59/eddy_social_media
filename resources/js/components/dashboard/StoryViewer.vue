<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Trash2, X } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { formatRelativeTime } from '@/lib/relativeTime';
import type { StoryGroup, StoryItem } from '@/types/dashboard';

const IMAGE_MS = 5000;

const props = defineProps<{
    groups: StoryGroup[];
}>();

const open = defineModel<boolean>('open', { default: false });
const groupIndex = defineModel<number>('groupIndex', { default: 0 });
const itemIndex = defineModel<number>('itemIndex', { default: 0 });

const video = ref<HTMLVideoElement | null>(null);
const paused = ref(false);
const progress = ref(0);
const deleting = ref(false);
const confirmOpen = ref(false);
let frame = 0;
let startedAt = 0;
let elapsed = 0;

const group = computed(() => props.groups[groupIndex.value] ?? null);
const item = computed<StoryItem | null>(() => group.value?.items[itemIndex.value] ?? null);
const author = computed(() => group.value?.user ?? null);

const durationMs = computed(() => {
    if (item.value?.kind === 'video' && video.value?.duration) {
        return video.value.duration * 1000;
    }

    return IMAGE_MS;
});

const stopLoop = () => {
    if (frame) {
        cancelAnimationFrame(frame);
        frame = 0;
    }
};

const tick = () => {
    if (!open.value || paused.value || !item.value) {
        return;
    }

    const total = durationMs.value;
    const current = elapsed + (performance.now() - startedAt);
    progress.value = Math.min(100, (current / total) * 100);

    if (progress.value >= 100) {
        next();
        return;
    }

    frame = requestAnimationFrame(tick);
};

const startLoop = () => {
    stopLoop();
    elapsed = (progress.value / 100) * durationMs.value;
    startedAt = performance.now();
    frame = requestAnimationFrame(tick);
};

const resetProgress = () => {
    stopLoop();
    progress.value = 0;
    elapsed = 0;
    paused.value = false;
};

const close = () => {
    stopLoop();
    confirmOpen.value = false;
    open.value = false;
};

const show = (nextGroup: number, nextItem = 0) => {
    groupIndex.value = nextGroup;
    itemIndex.value = nextItem;
    resetProgress();

    if (item.value?.kind !== 'video') {
        startLoop();
    }
};

const next = () => {
    if (!group.value) {
        close();
        return;
    }

    if (itemIndex.value < group.value.items.length - 1) {
        show(groupIndex.value, itemIndex.value + 1);
        return;
    }

    if (groupIndex.value < props.groups.length - 1) {
        show(groupIndex.value + 1, 0);
    }
};

const previous = () => {
    if (itemIndex.value > 0) {
        show(groupIndex.value, itemIndex.value - 1);
        return;
    }

    if (groupIndex.value > 0) {
        const previousGroup = props.groups[groupIndex.value - 1];
        show(groupIndex.value - 1, Math.max(0, (previousGroup?.items.length ?? 1) - 1));
        return;
    }

    show(groupIndex.value, 0);
};

const pause = () => {
    if (paused.value) {
        return;
    }

    paused.value = true;
    elapsed += performance.now() - startedAt;
    stopLoop();
    video.value?.pause();
};

const resume = () => {
    if (!paused.value || confirmOpen.value) {
        return;
    }

    paused.value = false;
    startedAt = performance.now();
    void video.value?.play();
    startLoop();
};

const onVideoReady = () => {
    resetProgress();
    void video.value?.play();
    startLoop();
};

const onKeydown = (event: KeyboardEvent) => {
    if (!open.value) {
        return;
    }

    if (event.key === 'ArrowRight') {
        next();
    } else if (event.key === 'ArrowLeft') {
        previous();
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        window.addEventListener('keydown', onKeydown);
        show(groupIndex.value, itemIndex.value);
        return;
    }

    window.removeEventListener('keydown', onKeydown);
    stopLoop();
});

watch(confirmOpen, (isOpen) => {
    if (!open.value) {
        return;
    }

    if (isOpen) {
        pause();
        return;
    }

    resume();
});

const remove = () => {
    if (!item.value) {
        return;
    }

    deleting.value = true;

    router.delete(`/stories/${item.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;

            const currentGroup = props.groups[groupIndex.value];

            if (!currentGroup || currentGroup.items.length === 0) {
                if (props.groups.length === 0) {
                    close();
                    return;
                }

                show(Math.min(groupIndex.value, props.groups.length - 1), 0);
                return;
            }

            show(
                groupIndex.value,
                Math.min(itemIndex.value, currentGroup.items.length - 1),
            );
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    stopLoop();
});

const barWidth = (index: number): string => {
    if (!group.value) {
        return '0%';
    }

    if (index < itemIndex.value) {
        return '100%';
    }

    if (index > itemIndex.value) {
        return '0%';
    }

    return `${progress.value}%`;
};
</script>

<template>
    <Teleport v-if="open && group && item && author" to="body">
        <div
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90"
            role="dialog"
            aria-modal="true"
            aria-label="Story"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="absolute top-4 right-4 z-10 text-white hover:bg-white/10 hover:text-white"
                @click="close"
            >
                <X class="size-5" />
                <span class="sr-only">Close</span>
            </Button>

            <div class="relative flex h-[min(92svh,780px)] w-full max-w-md flex-col px-3 py-4">
                <div class="mb-3 flex gap-1">
                    <div
                        v-for="(story, index) in group.items"
                        :key="story.id"
                        class="h-0.5 flex-1 overflow-hidden rounded-full bg-white/25"
                    >
                        <div
                            class="h-full rounded-full bg-white"
                            :style="{ width: barWidth(index) }"
                        />
                    </div>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <Avatar class="size-9">
                        <AvatarImage v-if="author.avatar" :src="author.avatar" :alt="author.name" />
                        <AvatarFallback>{{ getInitials(author.name) }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">
                            {{ author.name }}
                        </p>
                        <p class="text-xs text-white/70">
                            {{ formatRelativeTime(item.created_at) }}
                        </p>
                    </div>
                    <Button
                        v-if="item.can_delete"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="text-white hover:bg-white/10 hover:text-white"
                        @click="confirmOpen = true"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Delete story</span>
                    </Button>
                </div>

                <div
                    class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-2xl bg-black"
                    @pointerdown="pause"
                    @pointerup="resume"
                    @pointerleave="resume"
                >
                    <button
                        type="button"
                        class="absolute inset-y-0 left-0 z-10 w-1/3 cursor-pointer"
                        aria-label="Previous story"
                        @click="previous"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 z-10 w-2/3 cursor-pointer"
                        aria-label="Next story"
                        @click="next"
                    />

                    <img
                        v-if="item.kind === 'image'"
                        :src="item.url"
                        :alt="item.caption || author.name"
                        class="max-h-full max-w-full object-contain"
                        draggable="false"
                    >
                    <video
                        v-else
                        :key="item.id"
                        ref="video"
                        :src="item.url"
                        class="max-h-full max-w-full object-contain"
                        playsinline
                        autoplay
                        @loadedmetadata="onVideoReady"
                        @ended="next"
                    />

                    <p
                        v-if="item.caption"
                        class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pt-10 pb-4 text-center text-sm text-white"
                    >
                        {{ item.caption }}
                    </p>
                </div>
            </div>

            <Button
                v-if="groupIndex > 0 || itemIndex > 0"
                type="button"
                variant="ghost"
                size="icon"
                class="absolute top-1/2 left-2 hidden -translate-y-1/2 text-white hover:bg-white/10 hover:text-white md:inline-flex"
                @click="previous"
            >
                <ChevronLeft class="size-6" />
                <span class="sr-only">Previous</span>
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="absolute top-1/2 right-2 hidden -translate-y-1/2 text-white hover:bg-white/10 hover:text-white md:inline-flex"
                @click="next"
            >
                <ChevronRight class="size-6" />
                <span class="sr-only">Next</span>
            </Button>
        </div>
    </Teleport>

    <ConfirmDeleteDialog
        v-model:open="confirmOpen"
        title="Delete story?"
        description="This story will be removed right away."
        confirm-label="Delete"
        :loading="deleting"
        @confirm="remove"
    />
</template>

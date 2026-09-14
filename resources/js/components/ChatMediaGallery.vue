<script setup lang="ts">
import { ChevronLeft, ChevronRight, Play, X } from '@lucide/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import VideoJsPlayer from '@/components/VideoJsPlayer.vue';
import { cn } from '@/lib/utils';
import type { MessageKind } from '@/types/messages';

export type ChatGalleryItem = {
    id: number | string;
    kind: Extract<MessageKind, 'image' | 'video'>;
    url: string;
    mime_type: string | null;
};

const open = defineModel<boolean>('open', { default: false });
const index = defineModel<number>('index', { default: 0 });

const { items } = defineProps<{
    items: ChatGalleryItem[];
}>();

const strip = ref<HTMLElement | null>(null);
let openedAt = 0;

const current = computed(() => items[index.value] ?? null);
const hasMany = computed(() => items.length > 1);

const clampIndex = (value: number): number => {
    if (items.length === 0) {
        return 0;
    }

    return Math.max(0, Math.min(items.length - 1, value));
};

const goTo = (next: number) => {
    if (items.length === 0) {
        return;
    }

    index.value = (next + items.length) % items.length;
};

const previous = () => goTo(index.value - 1);
const next = () => goTo(index.value + 1);

const scrollActiveThumb = async () => {
    await nextTick();
    const active = strip.value?.querySelector<HTMLElement>(`[data-gallery-index="${index.value}"]`);
    active?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
};

watch(open, (isOpen) => {
    if (isOpen) {
        openedAt = Date.now();
        index.value = clampIndex(index.value);
        void scrollActiveThumb();
    }
});

watch(index, () => {
    if (open.value) {
        void scrollActiveThumb();
    }
});

watch(
    () => items.length,
    () => {
        if (open.value) {
            index.value = clampIndex(index.value);
        }
    },
);

const onPointerDownOutside = (event: Event) => {
    if (Date.now() - openedAt < 350) {
        event.preventDefault();
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (!open.value || hasMany.value === false) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        previous();
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        next();
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        window.addEventListener('keydown', onKeydown);
        return;
    }

    window.removeEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :glass="false"
            :show-close-button="false"
            class="border-0 bg-transparent p-0 shadow-none sm:max-w-[min(96vw,80rem)]"
            @pointer-down-outside="onPointerDownOutside"
        >
            <DialogTitle class="sr-only">
                Chat media gallery
            </DialogTitle>
            <DialogDescription class="sr-only">
                View photos and videos from this chat. Use next and previous to move between files.
            </DialogDescription>

            <div class="relative flex max-h-[92vh] w-full flex-col">
                <button
                    type="button"
                    class="absolute top-1 right-1 z-20 flex size-9 cursor-pointer items-center justify-center rounded-full border-0 bg-black/55 text-white outline-none"
                    aria-label="Close"
                    @click="open = false"
                >
                    <X class="size-4" />
                </button>

                <div class="relative flex min-h-0 flex-1 items-center justify-center px-12 py-2">
                    <button
                        v-if="hasMany"
                        type="button"
                        class="absolute left-0 z-10 flex size-10 cursor-pointer items-center justify-center rounded-full border-0 bg-black/55 text-white outline-none"
                        aria-label="Previous"
                        @click="previous"
                    >
                        <ChevronLeft class="size-6" />
                    </button>

                    <img
                        v-if="open && current?.kind === 'image'"
                        :src="current.url"
                        alt=""
                        class="max-h-[calc(92vh-7.5rem)] w-auto max-w-full rounded-lg object-contain"
                    />
                    <div
                        v-else-if="open && current?.kind === 'video'"
                        class="mx-auto w-full max-w-4xl overflow-hidden rounded-xl bg-black"
                    >
                        <VideoJsPlayer
                            :key="`${current.id}-${current.url}`"
                            :src="current.url"
                            :type="current.mime_type"
                            aspect-ratio="16:9"
                        />
                    </div>

                    <button
                        v-if="hasMany"
                        type="button"
                        class="absolute right-0 z-10 flex size-10 cursor-pointer items-center justify-center rounded-full border-0 bg-black/55 text-white outline-none"
                        aria-label="Next"
                        @click="next"
                    >
                        <ChevronRight class="size-6" />
                    </button>
                </div>

                <div
                    v-if="items.length > 0"
                    ref="strip"
                    class="mt-2 flex max-w-full justify-center gap-1.5 overflow-x-auto px-1 pb-1"
                >
                    <button
                        v-for="(item, itemIndex) in items"
                        :key="item.id"
                        type="button"
                        :data-gallery-index="itemIndex"
                        class="relative size-14 shrink-0 cursor-pointer overflow-hidden rounded-md border-0 outline-none"
                        :class="
                            cn(
                                'ring-offset-0',
                                itemIndex === index
                                    ? 'ring-2 ring-white'
                                    : 'opacity-70 hover:opacity-100',
                            )
                        "
                        :aria-label="item.kind === 'video' ? 'Open video' : 'Open photo'"
                        :aria-current="itemIndex === index ? 'true' : undefined"
                        @click="index = itemIndex"
                    >
                        <img
                            v-if="item.kind === 'image'"
                            :src="item.url"
                            alt=""
                            class="size-full border-0 object-cover"
                        />
                        <video
                            v-else
                            :src="item.url"
                            muted
                            preload="metadata"
                            class="size-full border-0 object-cover"
                        />
                        <span
                            v-if="item.kind === 'video'"
                            class="absolute inset-0 flex items-center justify-center bg-black/25"
                        >
                            <Play class="size-3 fill-white text-white" />
                        </span>
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

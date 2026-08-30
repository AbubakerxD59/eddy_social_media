<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import VideoJsPlayer from '@/components/VideoJsPlayer.vue';
import type { SignalMedia } from '@/types/social';

const open = defineModel<boolean>('open', { default: false });
let openedAt = 0;

watch(open, (isOpen) => {
    if (isOpen) {
        openedAt = Date.now();
    }
});

const onPointerDownOutside = (event: Event) => {
    if (Date.now() - openedAt < 350) {
        event.preventDefault();
    }
};

const { item, knownWidth = null, knownHeight = null } = defineProps<{
    item: SignalMedia | null;
    knownWidth?: number | null;
    knownHeight?: number | null;
}>();

const videoWidth = ref(16);
const videoHeight = ref(9);

const applySize = (width: number | null | undefined, height: number | null | undefined) => {
    if (typeof width === 'number' && typeof height === 'number' && width > 0 && height > 0) {
        videoWidth.value = width;
        videoHeight.value = height;
        return;
    }

    videoWidth.value = 16;
    videoHeight.value = 9;
};

watch(
    () => [open.value, item?.kind, knownWidth, knownHeight] as const,
    ([isOpen, kind, width, height]) => {
        if (!isOpen || kind !== 'video') {
            return;
        }

        applySize(width, height);
    },
    { immediate: true },
);

const isPortrait = computed(() => videoHeight.value > videoWidth.value);

const playerFrameStyle = computed(() => {
    const aspectRatio = `${videoWidth.value} / ${videoHeight.value}`;

    if (isPortrait.value) {
        return {
            aspectRatio,
            width: `min(96vw, calc(90vh * ${videoWidth.value} / ${videoHeight.value}))`,
        };
    }

    return {
        aspectRatio,
        width: '100%',
    };
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="true"
            class="border-0 bg-transparent p-0 shadow-none"
            @pointer-down-outside="onPointerDownOutside"
            :class="
                isPortrait
                    ? 'w-auto sm:max-w-fit'
                    : 'sm:max-w-[min(96vw,72rem)]'
            "
        >
            <DialogTitle class="sr-only">
                {{ item?.kind === 'video' ? 'Video player' : 'Image preview' }}
            </DialogTitle>
            <DialogDescription class="sr-only">
                {{
                    item?.kind === 'video'
                        ? 'Playing the selected signal video.'
                        : 'Magnified view of the selected signal image.'
                }}
            </DialogDescription>

            <img
                v-if="open && item?.kind === 'image'"
                :src="item.url"
                alt=""
                class="mx-auto max-h-[90vh] w-auto max-w-full cursor-zoom-out rounded-lg object-contain"
                @click="open = false"
            />

            <div
                v-else-if="open && item?.kind === 'video'"
                class="mx-auto overflow-hidden rounded-xl bg-black"
                :style="playerFrameStyle"
            >
                <VideoJsPlayer
                    :key="item.url"
                    :src="item.url"
                    :type="item.mime_type"
                    :aspect-ratio="`${videoWidth}:${videoHeight}`"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>

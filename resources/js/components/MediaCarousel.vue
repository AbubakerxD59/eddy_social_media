<script setup lang="ts">
import { Maximize2, Play, Volume2, VolumeX } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MediaViewer from '@/components/MediaViewer.vue';
import { cn } from '@/lib/utils';
import type { SignalMedia } from '@/types/social';

const { media } = defineProps<{
    media: SignalMedia[];
}>();

const emit = defineEmits<{
    page: [value: { page: number; pages: number }];
}>();

const scroller = ref<HTMLElement | null>(null);
const index = ref(0);
const dragging = ref(false);
const viewerOpen = ref(false);
const activeItem = ref<SignalMedia | null>(null);
const viewerVideoWidth = ref<number | null>(null);
const viewerVideoHeight = ref<number | null>(null);
const playingIds = ref<number[]>([]);
const unmutedIds = ref<number[]>([]);
const userPausedIds = ref<number[]>([]);
const dimensions = ref<Record<number, { width: number; height: number }>>({});

const cardHeight = 320;

const videos = new Map<number, HTMLVideoElement>();
const visibleIds = new Set<number>();
let observer: IntersectionObserver | null = null;

const isUnmuted = (id: number) => unmutedIds.value.includes(id);

const isUserPaused = (id: number) => userPausedIds.value.includes(id);

const setUserPaused = (id: number, paused: boolean) => {
    if (paused) {
        if (!isUserPaused(id)) {
            userPausedIds.value = [...userPausedIds.value, id];
        }

        return;
    }

    userPausedIds.value = userPausedIds.value.filter((current) => current !== id);
};

const setDimensions = (id: number, width: number, height: number) => {
    if (width <= 0 || height <= 0) {
        return;
    }

    const current = dimensions.value[id];

    if (current?.width === width && current?.height === height) {
        return;
    }

    dimensions.value = {
        ...dimensions.value,
        [id]: { width, height },
    };
};

const cardStyle = (id: number) => {
    const size = dimensions.value[id];
    const ratio = size ? size.width / size.height : 3 / 4;

    return {
        height: `${cardHeight}px`,
        width: `${cardHeight * ratio}px`,
    };
};

const bindImage = (id: number, el: unknown) => {
    const image = el instanceof HTMLImageElement ? el : null;

    if (image?.complete && image.naturalWidth > 0) {
        setDimensions(id, image.naturalWidth, image.naturalHeight);
    }
};

const onImageLoad = (id: number, event: Event) => {
    const image = event.target;

    if (image instanceof HTMLImageElement) {
        setDimensions(id, image.naturalWidth, image.naturalHeight);
    }
};

const setPlaying = (id: number, playing: boolean) => {
    const next = new Set(playingIds.value);

    if (playing) {
        next.add(id);
    } else {
        next.delete(id);
    }

    playingIds.value = [...next];
};

const applyMute = (id: number, muted: boolean) => {
    const video = videos.get(id);

    if (video) {
        video.muted = muted;
        video.volume = 1;
    }

    if (muted) {
        unmutedIds.value = unmutedIds.value.filter((current) => current !== id);
        return;
    }

    videos.forEach((other, otherId) => {
        if (otherId !== id) {
            other.muted = true;
        }
    });

    unmutedIds.value = [id];
};

const toggleMute = (id: number) => {
    applyMute(id, isUnmuted(id));
};

const mediaSrc = (video: HTMLVideoElement): string => {
    return video.getAttribute('src') || video.currentSrc || video.src;
};

const reviveVideo = (id: number) => {
    const video = videos.get(id);

    if (!video) {
        return;
    }

    const src = mediaSrc(video).split('#')[0];

    if (!src) {
        return;
    }

    if (video.error || video.readyState === HTMLMediaElement.HAVE_NOTHING) {
        video.src = src;
        video.load();
    }
};

const playVideo = (id: number) => {
    const video = videos.get(id);

    if (!video) {
        return;
    }

    video.muted = !isUnmuted(id);
    video.volume = 1;
    video.loop = true;

    void video.play().then(() => {
        setPlaying(id, true);
    }).catch(() => {
        reviveVideo(id);
        const retry = videos.get(id);

        if (!retry) {
            setPlaying(id, false);
            return;
        }

        void retry.play().then(() => {
            setPlaying(id, true);
        }).catch(() => {
            setPlaying(id, false);
        });
    });
};

const syncPlayback = (id: number) => {
    const video = videos.get(id);

    if (!video) {
        return;
    }

    const shouldPlay =
        visibleIds.has(id)
        && !viewerOpen.value
        && document.visibilityState === 'visible'
        && !isUserPaused(id);

    if (!shouldPlay) {
        video.pause();
        setPlaying(id, false);
        return;
    }

    playVideo(id);
};

const bindVideo = (id: number, el: unknown) => {
    const video = el instanceof HTMLVideoElement ? el : null;
    const previous = videos.get(id);

    if (previous && previous !== video) {
        observer?.unobserve(previous);
        videos.delete(id);
        visibleIds.delete(id);
        setPlaying(id, false);
    }

    if (!video) {
        return;
    }

    videos.set(id, video);
    observer?.observe(video);

    const applyVideoSize = () => {
        if (video.videoWidth > 0 && video.videoHeight > 0) {
            setDimensions(id, video.videoWidth, video.videoHeight);
        }
    };

    if (video.readyState >= HTMLMediaElement.HAVE_METADATA) {
        applyVideoSize();
    } else {
        video.addEventListener('loadedmetadata', applyVideoSize, { once: true });
    }

    syncPlayback(id);
};

const onVisibilityChange = () => {
    videos.forEach((_, id) => syncPlayback(id));
};

const multiple = computed(() => media.length > 1);
const pages = computed(() => Math.max(1, Math.ceil(media.length / 2)));
const page = computed(() => Math.min(pages.value, Math.floor(index.value / 2) + 1));

const dragThreshold = 8;
const slideThreshold = 48;

let startX = 0;
let startScroll = 0;
let startIndex = 0;
let pointerId: number | null = null;
let dragged = false;
let suppressClick = false;

const emitPage = () => {
    emit('page', { page: page.value, pages: pages.value });
};

const slideEls = (): HTMLElement[] => {
    if (!scroller.value) {
        return [];
    }

    return [...scroller.value.children] as HTMLElement[];
};

const snapTo = (nextIndex: number) => {
    const el = scroller.value;
    const slides = slideEls();

    if (!el || !slides.length) {
        return;
    }

    index.value = Math.max(0, Math.min(slides.length - 1, nextIndex));
    el.scrollTo({ left: slides[index.value].offsetLeft, behavior: 'smooth' });
    emitPage();
};

const onPointerDown = (event: PointerEvent) => {
    if (!multiple.value || !scroller.value || event.button !== 0) {
        return;
    }

    dragging.value = true;
    dragged = false;
    startX = event.clientX;
    startScroll = scroller.value.scrollLeft;
    startIndex = index.value;
    pointerId = event.pointerId;
    scroller.value.setPointerCapture(event.pointerId);
};

const onPointerMove = (event: PointerEvent) => {
    if (!dragging.value || !scroller.value) {
        return;
    }

    const delta = event.clientX - startX;

    if (Math.abs(delta) > dragThreshold) {
        dragged = true;
        suppressClick = true;
        event.preventDefault();
    }

    if (dragged) {
        scroller.value.scrollLeft = startScroll - delta;
    }
};

const onPointerUp = (event: PointerEvent) => {
    if (!dragging.value) {
        return;
    }

    dragging.value = false;

    if (scroller.value && pointerId !== null) {
        try {
            scroller.value.releasePointerCapture(pointerId);
        } catch {
            // Capture may already have been released.
        }
    }

    pointerId = null;

    if (!scroller.value || !slideEls().length) {
        return;
    }

    const delta = event.clientX - startX;

    if (dragged && Math.abs(delta) >= slideThreshold) {
        snapTo(startIndex + (delta < 0 ? 1 : -1));
        return;
    }

    snapTo(startIndex);
};

const onScroll = () => {
    if (dragging.value) {
        return;
    }

    const el = scroller.value;
    const slides = slideEls();

    if (!el || !slides.length) {
        return;
    }

    let closest = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    slides.forEach((slide, slideIndex) => {
        const distance = Math.abs(slide.offsetLeft - el.scrollLeft);

        if (distance < closestDistance) {
            closestDistance = distance;
            closest = slideIndex;
        }
    });

    index.value = closest;
    emitPage();
};

const onClickCapture = (event: MouseEvent) => {
    if (!suppressClick) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    suppressClick = false;
};

const openItem = (item: SignalMedia) => {
    const video = videos.get(item.id);
    const size = dimensions.value[item.id];

    activeItem.value = item;
    viewerVideoWidth.value = video && video.videoWidth > 0
        ? video.videoWidth
        : size?.width ?? null;
    viewerVideoHeight.value = video && video.videoHeight > 0
        ? video.videoHeight
        : size?.height ?? null;
    viewerOpen.value = true;
};

const openFullView = (event: MouseEvent, item: SignalMedia) => {
    event.preventDefault();
    event.stopPropagation();
    openItem(item);
};

const onCardClick = (item: SignalMedia) => {
    if (suppressClick || dragged) {
        return;
    }

    if (item.kind === 'image') {
        openItem(item);
        return;
    }

    const video = videos.get(item.id);

    if (!video) {
        openItem(item);
        return;
    }

    if (!video.paused) {
        video.pause();
        setUserPaused(item.id, true);
        setPlaying(item.id, false);
        return;
    }

    setUserPaused(item.id, false);
    playVideo(item.id);
};

watch(viewerOpen, (isOpen) => {
    if (!isOpen) {
        videos.forEach((_, id) => reviveVideo(id));
    }

    videos.forEach((_, id) => syncPlayback(id));
});

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            const id = Number((entry.target as HTMLElement).dataset.mediaId);

            if (!Number.isFinite(id)) {
                continue;
            }

            if (entry.isIntersecting && entry.intersectionRatio >= 0.45) {
                visibleIds.add(id);
            } else {
                visibleIds.delete(id);

                if (entry.intersectionRatio === 0) {
                    setUserPaused(id, false);
                }
            }

            syncPlayback(id);
        }
    }, {
        threshold: [0, 0.45, 0.75, 1],
    });

    videos.forEach((video) => observer?.observe(video));
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    observer?.disconnect();
    observer = null;
    videos.forEach((video) => video.pause());
    videos.clear();
    visibleIds.clear();
});

</script>

<template>
    <div
        v-if="media.length"
        class="relative -mr-4"
        data-no-nav
    >
        <div
            ref="scroller"
            class="flex gap-2 overflow-x-auto overscroll-x-contain pr-4 select-none [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            :class="cn(multiple && 'touch-pan-y', dragging && dragged && 'cursor-grabbing')"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @lostpointercapture="onPointerUp"
            @scroll.passive="onScroll"
            @click.capture="onClickCapture"
            @dragstart.prevent
        >
            <div
                v-for="item in media"
                :key="item.id"
                class="relative h-80 shrink-0 overflow-hidden rounded-2xl bg-neutral-950"
                :style="cardStyle(item.id)"
            >
                <button
                    type="button"
                    class="absolute inset-0"
                    :class="
                        cn(
                            item.kind === 'image' ? 'cursor-zoom-in' : 'cursor-pointer',
                            dragging && dragged && 'cursor-grabbing',
                        )
                    "
                    :aria-label="
                        item.kind === 'image'
                            ? 'Magnify image'
                            : playingIds.includes(item.id)
                              ? 'Pause video'
                              : 'Play video'
                    "
                    @click="onCardClick(item)"
                >
                    <img
                        v-if="item.kind === 'image'"
                        :ref="(el) => bindImage(item.id, el)"
                        :src="item.url"
                        alt=""
                        draggable="false"
                        class="pointer-events-none absolute inset-0 size-full object-cover select-none"
                        @load="onImageLoad(item.id, $event)"
                    />
                    <template v-else>
                        <video
                            :ref="(el) => bindVideo(item.id, el)"
                            :data-media-id="item.id"
                            :src="item.url"
                            class="pointer-events-none absolute inset-0 size-full object-cover"
                            :muted="!isUnmuted(item.id)"
                            loop
                            playsinline
                            preload="auto"
                        />
                        <span
                            v-if="!playingIds.includes(item.id)"
                            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/30"
                        >
                            <span
                                class="flex size-12 items-center justify-center rounded-full bg-black/70 text-white"
                            >
                                <Play class="size-5 fill-current" />
                            </span>
                        </span>
                    </template>
                </button>

                <div
                    v-if="item.kind === 'video'"
                    class="absolute right-2 bottom-2 z-10 flex items-center gap-1.5"
                >
                    <button
                        type="button"
                        class="flex size-8 cursor-pointer items-center justify-center rounded-full bg-black/70 text-white"
                        aria-label="Open full video player"
                        @click="openFullView($event, item)"
                        @pointerdown.stop
                    >
                        <Maximize2 class="size-4" />
                    </button>
                    <button
                        v-if="playingIds.includes(item.id)"
                        type="button"
                        class="flex size-8 cursor-pointer items-center justify-center rounded-full bg-black/70 text-white"
                        :aria-label="isUnmuted(item.id) ? 'Mute video' : 'Unmute video'"
                        @click.stop="toggleMute(item.id)"
                        @pointerdown.stop
                    >
                        <Volume2
                            v-if="isUnmuted(item.id)"
                            class="size-4"
                        />
                        <VolumeX
                            v-else
                            class="size-4"
                        />
                    </button>
                </div>
            </div>
        </div>

        <MediaViewer
            v-model:open="viewerOpen"
            :item="activeItem"
            :known-width="viewerVideoWidth"
            :known-height="viewerVideoHeight"
        />
    </div>
</template>

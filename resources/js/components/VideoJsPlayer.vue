<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const { src, type = 'video/mp4', aspectRatio } = defineProps<{
    src: string;
    type?: string | null;
    aspectRatio?: string | null;
}>();

const videoEl = ref<HTMLVideoElement | null>(null);
let player: { dispose: () => void; play: () => Promise<void> | void } | null = null;

const playerSrc = src.includes('#') ? src : `${src}#vjs`;

onMounted(async () => {
    if (!videoEl.value) {
        return;
    }

    const { default: videojs } = await import('video.js');
    await import('video.js/dist/video-js.css');

    player = videojs(videoEl.value, {
        controls: true,
        fill: true,
        fluid: false,
        aspectRatio: aspectRatio || '16:9',
        preload: 'auto',
        playbackRates: [0.5, 1, 1.5, 2],
        userActions: {
            click(event: Event) {
                const target = event.target;

                if (
                    target instanceof Element
                    && target.closest('.vjs-control-bar, .vjs-big-play-button, .vjs-modal-dialog')
                ) {
                    return;
                }

                if (this.paused()) {
                    void this.play();
                } else {
                    this.pause();
                }
            },
        },
        sources: [
            {
                src: playerSrc,
                type: type || 'video/mp4',
            },
        ],
    });

    void Promise.resolve(player.play()).catch(() => undefined);
});

onBeforeUnmount(() => {
    try {
        player?.dispose();
    } catch {
        // Player may already have been torn down with the dialog.
    }

    player = null;
});
</script>

<template>
    <div class="size-full">
        <video
            ref="videoEl"
            class="video-js vjs-big-play-centered vjs-fill size-full overflow-hidden rounded-xl"
            playsinline
        />
    </div>
</template>

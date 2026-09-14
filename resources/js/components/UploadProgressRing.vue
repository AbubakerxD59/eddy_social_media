<script setup lang="ts">
const RING = 2 * Math.PI * 14;

defineProps<{
    progress: number;
}>();
</script>

<template>
    <div class="absolute inset-0 z-10 flex items-center justify-center bg-black/50">
        <div class="relative size-14">
            <svg class="size-14 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle
                    cx="18"
                    cy="18"
                    r="14"
                    fill="none"
                    stroke="rgb(255 255 255 / 0.25)"
                    stroke-width="2.5"
                />
                <circle
                    cx="18"
                    cy="18"
                    r="14"
                    fill="none"
                    stroke="white"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    :stroke-dasharray="RING"
                    :stroke-dashoffset="RING * (1 - Math.max(0, Math.min(100, progress)) / 100)"
                />
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-[11px] font-semibold text-white">
                {{ Math.round(progress) }}%
            </span>
        </div>
    </div>
</template>

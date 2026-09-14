<script setup lang="ts">
import { Play } from '@lucide/vue';
import { computed } from 'vue';
import UploadProgressRing from '@/components/UploadProgressRing.vue';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/messages';

export type AlbumItem = ChatMessage & {
    client_id?: string;
    progress?: number | null;
    status?: 'sent' | 'pending' | 'failed';
};

const props = defineProps<{
    items: AlbumItem[];
}>();

const emit = defineEmits<{
    open: [item: AlbumItem];
}>();

const visible = computed(() => props.items.slice(0, 4));
const overflow = computed(() => Math.max(0, props.items.length - 4));
const count = computed(() => Math.min(props.items.length, 4));

const tileClass = (index: number): string => {
    if (count.value === 1) {
        return 'col-span-2 row-span-2 min-h-40';
    }

    if (count.value === 3 && index === 0) {
        return 'row-span-2';
    }

    return '';
};

const isUploading = (item: AlbumItem): boolean => item.status === 'pending';
</script>

<template>
    <div
        class="grid overflow-hidden rounded-xl"
        :class="
            count === 1
                ? 'grid-cols-1'
                : 'h-52 w-[min(100%,17rem)] grid-cols-2 grid-rows-2 gap-0.5'
        "
    >
        <button
            v-for="(item, index) in visible"
            :key="item.id || item.url || index"
            type="button"
            class="relative overflow-hidden border-0 bg-black/20 outline-none"
            :class="cn(tileClass(index), count === 1 && 'max-h-72', isUploading(item) ? 'cursor-default' : 'cursor-pointer')"
            :aria-label="item.kind === 'video' ? 'Play video' : 'View photo'"
            :disabled="isUploading(item)"
            @click="!isUploading(item) && emit('open', item)"
        >
            <img
                v-if="item.kind === 'image' && item.url"
                :src="item.url"
                :alt="item.original_name ?? 'Photo'"
                class="size-full border-0 object-cover"
                :class="count === 1 && 'max-h-72 w-full object-cover'"
            />
            <video
                v-else-if="item.kind === 'video' && item.url"
                :src="item.url"
                muted
                preload="metadata"
                class="size-full border-0 object-cover"
            />
            <span
                v-if="item.kind === 'video' && !isUploading(item)"
                class="absolute inset-0 flex items-center justify-center bg-black/25"
            >
                <span class="flex size-10 items-center justify-center rounded-full bg-black/60 text-white">
                    <Play class="size-5 fill-current" />
                </span>
            </span>
            <UploadProgressRing
                v-if="isUploading(item)"
                :progress="item.progress ?? 0"
            />
            <span
                v-if="overflow > 0 && index === visible.length - 1 && !isUploading(item)"
                class="absolute inset-0 flex items-center justify-center bg-black/55 text-lg font-semibold text-white"
            >
                +{{ overflow }}
            </span>
        </button>
    </div>
</template>

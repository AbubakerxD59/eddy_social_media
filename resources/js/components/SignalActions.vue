<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart, MessageCircle, Share2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError, notifySuccess } from '@/lib/notify';
import type { FeedSignal } from '@/types/social';

const { signal } = defineProps<{
    signal: FeedSignal;
}>();

const liked = ref(signal.liked);
const likesCount = ref(signal.likes_count);
const liking = ref(false);

const shareUrl = computed(() => {
    if (typeof window === 'undefined') {
        return `/s/${signal.id}`;
    }

    return `${window.location.origin}/s/${signal.id}`;
});

const toggleLike = async () => {
    if (liking.value) {
        return;
    }

    liking.value = true;
    const nextLiked = !liked.value;
    liked.value = nextLiked;
    likesCount.value += nextLiked ? 1 : -1;

    try {
        const response = await fetch(`/signals/${signal.id}/like`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';
            return;
        }

        if (!response.ok) {
            throw new Error('Like failed');
        }

        const data = (await response.json()) as {
            liked: boolean;
            likes_count: number;
        };

        liked.value = data.liked;
        likesCount.value = data.likes_count;
    } catch {
        liked.value = !nextLiked;
        likesCount.value += nextLiked ? -1 : 1;
        notifyError('Could not update that reaction.');
    } finally {
        liking.value = false;
    }
};

const share = async () => {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        notifySuccess('Link copied.');
    } catch {
        notifyError('Could not copy the link.');
    }
};

const formatCount = (value: number): string => {
    if (value <= 0) {
        return '';
    }

    return String(value);
};
</script>

<template>
    <div class="flex items-center gap-3 pt-1">
        <button
            type="button"
            class="text-muted-foreground hover:text-foreground inline-flex min-w-12 cursor-pointer items-center gap-1.5 rounded-full px-2 py-1.5 text-sm hover:bg-accent"
            :class="liked && 'text-red-500 hover:bg-red-500/10 hover:text-red-500'"
            :aria-pressed="liked"
            :aria-label="liked ? 'Remove heart' : 'Heart'"
            @click="toggleLike"
        >
            <Heart
                class="size-6 shrink-0"
                :class="liked && 'fill-current'"
            />
            <span class="min-w-4 tabular-nums">
                {{ formatCount(likesCount) }}
            </span>
        </button>

        <Link
            :href="`/s/${signal.id}`"
            class="text-muted-foreground hover:text-foreground inline-flex min-w-12 cursor-pointer items-center gap-1.5 rounded-full px-2 py-1.5 text-sm hover:bg-accent"
            aria-label="Reply"
        >
            <MessageCircle class="size-6 shrink-0" />
            <span class="min-w-4 tabular-nums">
                {{ formatCount(signal.replies_count) }}
            </span>
        </Link>

        <button
            type="button"
            class="text-muted-foreground hover:text-foreground inline-flex min-w-12 cursor-pointer items-center justify-center gap-1.5 rounded-full px-2 py-1.5 text-sm hover:bg-accent"
            aria-label="Share"
            @click="share"
        >
            <Share2 class="size-6 shrink-0" />
        </button>
    </div>
</template>

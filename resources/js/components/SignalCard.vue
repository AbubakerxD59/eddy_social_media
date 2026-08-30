<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import LinkifiedText from '@/components/LinkifiedText.vue';
import LinkPreviewCard from '@/components/LinkPreviewCard.vue';
import MediaCarousel from '@/components/MediaCarousel.vue';
import SignalActions from '@/components/SignalActions.vue';
import SignalMoreMenu from '@/components/SignalMoreMenu.vue';
import ThreadAvatar from '@/components/ThreadAvatar.vue';
import { formatRelativeTime } from '@/lib/relativeTime';
import { cn } from '@/lib/utils';
import type { FeedSignal } from '@/types/social';

const { signal, highlighted = false, showLine = false } = defineProps<{
    signal: FeedSignal;
    highlighted?: boolean;
    showLine?: boolean;
}>();

const article = ref<HTMLElement | null>(null);
const confirmOpen = ref(false);
const deleting = ref(false);
const mediaPage = ref(1);
const mediaPages = computed(() => Math.max(1, Math.ceil(signal.media.length / 2)));

const page = usePage();
const profileHref = computed(() => `/@${signal.author.username}`);
const signalHref = computed(() => `/s/${signal.id}`);
const isCurrentSignal = computed(() => page.url.split('?')[0] === signalHref.value);
const createdAt = computed(() => formatRelativeTime(signal.created_at));

const shouldIgnoreClick = (event: MouseEvent): boolean => {
    if (event.defaultPrevented || event.button !== 0) {
        return true;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
        return true;
    }

    if (target.closest('a, button, input, textarea, select, [role="menuitem"], [data-no-nav]')) {
        return true;
    }

    const selection = window.getSelection();

    return Boolean(selection && selection.toString().length > 0);
};

const openSignal = (event: MouseEvent) => {
    if (isCurrentSignal.value || shouldIgnoreClick(event)) {
        return;
    }

    if (event.metaKey || event.ctrlKey) {
        window.open(signalHref.value, '_blank', 'noopener');
        return;
    }

    router.visit(signalHref.value);
};

const onCarouselPage = (value: { page: number; pages: number }) => {
    mediaPage.value = value.page;
};

onMounted(() => {
    if (highlighted) {
        article.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

const remove = () => {
    deleting.value = true;

    router.delete(`/signals/${signal.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
        },
    });
};
</script>

<template>
    <article
        ref="article"
        class="border-b px-4 transition-colors hover:bg-accent/30"
        :class="[highlighted && 'bg-accent/50', !isCurrentSignal && 'cursor-pointer']"
        @click="openSignal"
    >
        <div class="flex gap-3">
            <ThreadAvatar
                class="pt-3"
                :name="signal.author.name"
                :avatar="signal.author.avatar"
                :href="profileHref"
                :show-line="showLine"
            />

            <div class="min-w-0 flex-1 space-y-2 py-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 text-[15px]">
                        <Link
                            :href="profileHref"
                            class="truncate font-semibold hover:underline"
                        >
                            {{ signal.author.name }}
                        </Link>
                        <span
                            v-if="createdAt"
                            class="text-muted-foreground shrink-0"
                        >
                            · {{ createdAt }}
                        </span>
                    </div>

                    <SignalMoreMenu
                        :signal="signal"
                        @delete="confirmOpen = true"
                    />
                </div>

                <div
                    v-if="signal.body || signal.media.length > 1"
                    class="flex items-start gap-2"
                >
                    <p
                        v-if="signal.body"
                        :class="
                            cn(
                                'min-w-0 flex-1 whitespace-pre-wrap text-[15px] leading-relaxed',
                                signal.type === 'quote' && 'text-[16px]',
                            )
                        "
                    >
                        <LinkifiedText :text="signal.body" />
                    </p>
                    <span
                        v-if="signal.media.length > 1"
                        class="bg-muted text-muted-foreground mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[11px] tabular-nums"
                    >
                        {{ mediaPage }}/{{ mediaPages }}
                    </span>
                </div>

                <MediaCarousel
                    v-if="signal.media.length"
                    :media="signal.media"
                    @page="onCarouselPage"
                />

                <LinkPreviewCard v-if="signal.link" :link="signal.link" />

                <SignalActions :signal="signal" />
            </div>
        </div>

        <ConfirmDeleteDialog
            v-if="signal.can_delete"
            v-model:open="confirmOpen"
            title="Delete signal?"
            description="If you delete this signal, you won't be able to restore it."
            confirm-label="Delete"
            :loading="deleting"
            @confirm="remove"
        />
    </article>
</template>

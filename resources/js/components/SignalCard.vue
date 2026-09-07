<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import FormattedBody from '@/components/FormattedBody.vue';
import LinkPreviewCard from '@/components/LinkPreviewCard.vue';
import MediaCarousel from '@/components/MediaCarousel.vue';
import SignalActions from '@/components/SignalActions.vue';
import SignalComposer from '@/components/SignalComposer.vue';
import SignalDetails from '@/components/SignalDetails.vue';
import SignalMoreMenu from '@/components/SignalMoreMenu.vue';
import SignalPoll from '@/components/SignalPoll.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { formatRelativeTime } from '@/lib/relativeTime';
import { signalTypeMeta } from '@/lib/signalTypes';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import type { FeedSignal } from '@/types/social';

const { signal, highlighted = false, variant = 'card' } = defineProps<{
    signal: FeedSignal;
    highlighted?: boolean;
    variant?: 'card' | 'thread';
}>();

const article = ref<HTMLElement | null>(null);
const confirmOpen = ref(false);
const editing = ref(false);
const deleting = ref(false);
const mediaPage = ref(1);
const mediaPages = computed(() => Math.max(1, Math.ceil(signal.media.length / 2)));

const page = usePage();
const profileHref = computed(() => `/@${signal.author.username}`);
const signalHref = computed(() => `/s/${signal.id}`);
const isCurrentSignal = computed(() => page.url.split('?')[0] === signalHref.value);
const createdAt = computed(() => formatRelativeTime(signal.created_at));
const typeMeta = computed(() => signalTypeMeta(signal.type));
const showTypeBadge = computed(() => !signal.is_reply);

const shouldIgnoreClick = (event: MouseEvent): boolean => {
    if (event.defaultPrevented || event.button !== 0) {
        return true;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
        return true;
    }

    if (target.closest('a, button, input, textarea, select, [contenteditable], [role="menuitem"], [data-no-nav]')) {
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

const openEditor = () => {
    editing.value = true;
};

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
        class="transition-colors"
        :class="[
            'glass-panel rounded-2xl px-4 py-4',
            highlighted && 'ring-primary/40 ring-2',
            !isCurrentSignal && 'cursor-pointer hover:bg-accent/10',
        ]"
        @click="openSignal"
    >
        <div class="flex items-start justify-between gap-3">
            <Link :href="profileHref" class="flex min-w-0 items-center gap-3">
                <Avatar class="size-11">
                    <AvatarImage
                        v-if="signal.author.avatar"
                        :src="signal.author.avatar"
                        :alt="signal.author.name"
                    />
                    <AvatarFallback>{{ getInitials(signal.author.name) }}</AvatarFallback>
                </Avatar>
                <span class="min-w-0">
                    <span class="block truncate text-[15px] font-semibold">
                        {{ signal.author.name }}
                    </span>
                    <span class="text-muted-foreground block truncate text-[12px]">
                        {{ signal.author.headline || `@${signal.author.username}` }}
                        <span v-if="createdAt"> · {{ createdAt }}</span>
                    </span>
                </span>
            </Link>

            <div class="shrink-0" @click.stop>
                <SignalMoreMenu
                    :signal="signal"
                    @edit="openEditor"
                    @delete="confirmOpen = true"
                />
            </div>
        </div>

        <div class="mt-3 space-y-3">
            <span
                v-if="showTypeBadge"
                class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold tracking-wide uppercase"
                :class="typeMeta.badgeClass"
            >
                {{ signal.type === 'drop' ? 'Drop' : typeMeta.label }}
            </span>

            <h2
                v-if="signal.title"
                class="text-[17px] leading-snug font-semibold"
            >
                {{ signal.title }}
            </h2>

            <div
                v-if="signal.body || signal.media.length > 1"
                class="flex items-start gap-2"
            >
                <div
                    v-if="signal.body"
                    :class="
                        cn(
                            'text-muted-foreground min-w-0 flex-1 text-[15px] leading-relaxed',
                            signal.type === 'drop' && 'text-foreground',
                        )
                    "
                >
                    <FormattedBody :html="signal.body" />
                </div>
                <span
                    v-if="signal.media.length > 1"
                    class="bg-muted text-muted-foreground mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[11px] tabular-nums"
                >
                    {{ mediaPage }}/{{ mediaPages }}
                </span>
            </div>

            <SignalDetails
                v-if="signal.need || signal.opportunity"
                :need="signal.need"
                :opportunity="signal.opportunity"
            />

            <SignalPoll
                v-if="signal.poll"
                :signal-id="signal.id"
                :poll="signal.poll"
            />

            <MediaCarousel
                v-if="signal.media.length"
                :media="signal.media"
                @page="onCarouselPage"
            />

            <LinkPreviewCard v-if="signal.link" :link="signal.link" />

            <SignalActions :signal="signal" />
        </div>

        <ConfirmDeleteDialog
            v-model:open="confirmOpen"
            title="Delete signal?"
            description="If you delete this signal, you won't be able to restore it."
            confirm-label="Delete"
            :loading="deleting"
            @confirm="remove"
        />
    </article>

    <SignalComposer
        v-if="editing"
        :editing="signal"
        @close="editing = false"
        @created="editing = false"
    />
</template>

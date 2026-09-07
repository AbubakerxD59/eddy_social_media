<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import StoryComposer from '@/components/dashboard/StoryComposer.vue';
import StoryViewer from '@/components/dashboard/StoryViewer.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import type { StoryGroup, StoryItem } from '@/types/dashboard';

const page = usePage();
const user = computed(() => page.props.auth.user);
const storiesPending = computed(() => page.props.stories === undefined);
const groups = computed<StoryGroup[]>(() => page.props.stories ?? []);

const composeOpen = ref(false);
const viewerOpen = ref(false);
const viewerGroupIndex = ref(0);
const viewerItemIndex = ref(0);

const tiles = computed(() =>
    groups.value.map((group) => {
        const preview: StoryItem | null = group.items.at(-1) ?? null;
        const isOwn = group.user.id === user.value?.id;

        return {
            group,
            preview,
            isOwn,
            label: isOwn ? 'Your Story' : group.user.name?.split(' ')[0] || group.user.username,
        };
    }),
);

const openComposer = () => {
    composeOpen.value = true;
};

const openGroup = (group: StoryGroup) => {
    const index = groups.value.findIndex((entry) => entry.user?.id === group.user.id);

    if (index < 0) {
        return;
    }

    viewerGroupIndex.value = index;
    viewerItemIndex.value = 0;
    viewerOpen.value = true;
};
</script>

<template>
    <section class="glass-panel overflow-hidden rounded-2xl px-4 py-3">
        <div class="scrollbar-none flex gap-3 overflow-x-auto pb-1">
            <button
                v-if="user"
                type="button"
                class="bg-card relative h-40 w-[6.5rem] shrink-0 cursor-pointer overflow-hidden rounded-2xl border border-white/10 text-left"
                aria-label="Add to your story"
                @click="openComposer"
            >
                <img
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                    class="size-full object-cover"
                >
                <span
                    v-else
                    class="bg-muted text-muted-foreground flex size-full items-center justify-center text-lg font-semibold"
                >
                    {{ getInitials(user.name) }}
                </span>
                <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent px-2 pt-10 pb-2">
                    <span
                        class="bg-primary mb-1.5 flex size-6 items-center justify-center rounded-full text-white shadow-md"
                    >
                        <Plus class="size-3.5" />
                    </span>
                    <span class="block text-[11px] font-semibold text-white">Add story</span>
                </span>
            </button>

            <template v-if="storiesPending">
                <Skeleton
                    v-for="n in 3"
                    :key="n"
                    class="h-40 w-[6.5rem] shrink-0 rounded-2xl"
                />
            </template>

            <button
                v-for="tile in tiles"
                :key="tile.group.user.id"
                type="button"
                class="relative h-40 w-[6.5rem] shrink-0 cursor-pointer overflow-hidden rounded-2xl bg-black text-left"
                :aria-label="`View ${tile.label} story`"
                @click="openGroup(tile.group)"
            >
                <img
                    v-if="tile.preview?.kind === 'image'"
                    :src="tile.preview.url"
                    :alt="tile.preview.caption || tile.label"
                    class="size-full object-cover"
                >
                <video
                    v-else-if="tile.preview?.kind === 'video'"
                    :src="`${tile.preview.url}#t=0.1`"
                    class="size-full object-cover"
                    muted
                    playsinline
                    preload="metadata"
                />
                <span
                    v-else
                    class="bg-muted flex size-full items-center justify-center text-sm font-semibold"
                >
                    {{ getInitials(tile.group.user.name) }}
                </span>

                <span class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-black/25" />

                <span
                    class="from-primary absolute top-2 left-2 rounded-full bg-gradient-to-br to-violet-400 p-[2px]"
                >
                    <Avatar class="size-7 border border-black/40">
                        <AvatarImage
                            v-if="tile.group.user.avatar"
                            :src="tile.group.user.avatar"
                            :alt="tile.group.user.name"
                        />
                        <AvatarFallback class="text-[10px]">
                            {{ getInitials(tile.group.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                </span>

                <span class="absolute inset-x-0 bottom-0 px-2 pb-2 text-[11px] leading-tight font-semibold text-white">
                    {{ tile.label }}
                </span>
            </button>
        </div>

        <StoryComposer v-model:open="composeOpen" />
        <StoryViewer
            v-model:open="viewerOpen"
            v-model:group-index="viewerGroupIndex"
            v-model:item-index="viewerItemIndex"
            :groups="groups"
        />
    </section>
</template>

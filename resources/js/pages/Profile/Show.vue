<script setup lang="ts">
import { Deferred, Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SignalCard from '@/components/SignalCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';
import type { FeedSignal, Paginator, PublicUser } from '@/types/social';

const props = defineProps<{
    profile: PublicUser & { is_mentor: boolean; is_own: boolean };
    signals?: Paginator<FeedSignal> | null;
}>();

const items = computed(() => props.signals?.data ?? []);
</script>

<template>
    <Head :title="profile.name" />

    <div class="mx-auto w-full max-w-2xl">
        <div class="border-b px-4 py-8">
            <div class="flex items-start justify-between gap-4">
                <Avatar class="size-20">
                    <AvatarImage
                        v-if="profile.avatar"
                        :src="profile.avatar"
                        :alt="profile.name"
                    />
                    <AvatarFallback class="text-xl">
                        {{ getInitials(profile.name) }}
                    </AvatarFallback>
                </Avatar>
                <Button v-if="profile.is_own" as-child variant="outline" size="sm">
                    <Link :href="edit()">Edit profile</Link>
                </Button>
            </div>
            <h1 class="mt-4 text-2xl font-semibold">{{ profile.name }}</h1>
            <p class="text-muted-foreground">@{{ profile.username }}</p>
            <p v-if="profile.headline" class="mt-2 font-medium">
                {{ profile.headline }}
            </p>
            <p v-if="profile.bio" class="mt-2 text-sm whitespace-pre-wrap">
                {{ profile.bio }}
            </p>
            <a
                v-if="profile.website"
                :href="profile.website"
                class="text-primary mt-2 inline-block text-sm underline underline-offset-4"
                target="_blank"
                rel="noopener noreferrer"
            >
                {{ profile.website }}
            </a>
            <p v-if="profile.is_mentor" class="mt-3 text-sm font-medium">
                Open to mentoring
            </p>
        </div>

        <Deferred data="signals">
            <template #fallback>
                <div class="space-y-4 px-4 py-6">
                    <Skeleton v-for="n in 3" :key="n" class="h-28 w-full rounded-2xl" />
                </div>
            </template>

            <InfiniteScroll data="signals" :buffer="400">
                <SignalCard
                    v-for="(signal, index) in items"
                    :key="signal.id"
                    :signal="signal"
                    :show-line="index < items.length - 1"
                />

                <div
                    v-if="items.length === 0"
                    class="text-muted-foreground px-4 py-16 text-center text-sm"
                >
                    No signals yet.
                </div>
            </InfiniteScroll>
        </Deferred>
    </div>
</template>

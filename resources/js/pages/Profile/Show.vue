<script setup lang="ts">
import { Deferred, Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/vue3';
import { Camera, Check, Globe, MessageSquare, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import SignalCard from '@/components/SignalCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import { csrfHeaders } from '@/lib/csrf';
import { formatUsdHourly } from '@/lib/currency';
import { notifyError, notifySuccess } from '@/lib/notify';
import { uploadProfilePhoto } from '@/lib/profilePhoto';
import { edit } from '@/routes/profile';
import type { FeedSignal, Paginator, PublicUser } from '@/types/social';

type ConnectionState = 'none' | 'pending_outgoing' | 'pending_incoming' | 'accepted';

const props = defineProps<{
    profile: PublicUser & {
        is_mentor: boolean;
        is_talent: boolean;
        is_own: boolean;
        hourly_rate_cents?: number | null;
        connection?: ConnectionState;
    };
    signals?: Paginator<FeedSignal> | null;
}>();

const page = usePage();
const items = computed(() => props.signals?.data ?? []);
const connecting = ref(false);
const uploading = ref<'avatar' | 'cover' | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
const coverInput = ref<HTMLInputElement | null>(null);
const connection = ref<ConnectionState>(props.profile.connection ?? 'none');

const joined = computed(() => {
    if (!props.profile.created_at) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(props.profile.created_at));
});

const connectLabel = computed(() => {
    if (connection.value === 'accepted') {
        return 'Connected';
    }

    if (connection.value === 'pending_outgoing') {
        return 'Request sent';
    }

    if (connection.value === 'pending_incoming') {
        return 'Respond in notifications';
    }

    return 'Connect';
});

const pickPhoto = async (kind: 'avatar' | 'cover', event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    uploading.value = kind;
    await uploadProfilePhoto(kind, file);
    uploading.value = null;
};

const connect = async () => {
    if (!page.props.auth.user) {
        router.visit('/login');
        return;
    }

    if (
        props.profile.is_own ||
        connection.value === 'accepted' ||
        connection.value === 'pending_outgoing' ||
        connecting.value
    ) {
        return;
    }

    connecting.value = true;

    try {
        const response = await fetch(`/users/${props.profile.id}/connect`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: '{}',
        });

        const payload = (await response.json()) as { message?: string; status?: ConnectionState };

        if (!response.ok) {
            notifyError(payload.message ?? 'Could not send the connection request.');
            return;
        }

        connection.value = payload.status ?? 'pending_outgoing';
        notifySuccess(payload.message ?? 'Connection request sent.');
    } catch {
        notifyError('Could not send the connection request.');
    } finally {
        connecting.value = false;
    }
};

const message = () => {
    if (!page.props.auth.user) {
        router.visit('/login');
        return;
    }

    router.visit(`/messages/with/${props.profile.id}`);
};
</script>

<template>
    <Head :title="profile.name" />

    <div class="-mx-0 min-h-full bg-transparent">
        <div class="bg-card/40 relative">
            <div class="relative h-[220px] overflow-hidden sm:h-[280px] lg:h-[350px]">
                <img
                    v-if="profile.cover"
                    :src="profile.cover"
                    :alt="`${profile.name}'s cover`"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="from-primary/70 via-primary/40 to-need/50 size-full bg-gradient-to-br"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/25 to-transparent" />

                <div
                    v-if="profile.is_own"
                    class="absolute right-4 bottom-4 sm:right-8"
                >
                    <input
                        ref="coverInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        class="hidden"
                        @change="pickPhoto('cover', $event)"
                    />
                    <Button
                        type="button"
                        size="sm"
                        class="rounded-lg bg-black/55 text-white hover:bg-black/70"
                        :loading="uploading === 'cover'"
                        @click="coverInput?.click()"
                    >
                        <Camera class="size-4" />
                        {{ profile.cover ? 'Edit cover photo' : 'Add cover photo' }}
                    </Button>
                </div>
            </div>

            <div class="mx-auto max-w-5xl px-4">
                <div class="flex flex-col gap-4 pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                        <div class="relative -mt-16 size-36 shrink-0 sm:-mt-20 sm:size-44">
                            <Avatar class="border-background size-full border-4 shadow-lg">
                                <AvatarImage
                                    v-if="profile.avatar"
                                    :src="profile.avatar"
                                    :alt="profile.name"
                                />
                                <AvatarFallback class="text-4xl">
                                    {{ getInitials(profile.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <template v-if="profile.is_own">
                                <input
                                    ref="avatarInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    class="hidden"
                                    @change="pickPhoto('avatar', $event)"
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    class="absolute right-1 bottom-1 rounded-full"
                                    :loading="uploading === 'avatar'"
                                    aria-label="Update profile picture"
                                    @click="avatarInput?.click()"
                                >
                                    <Camera class="size-4" />
                                </Button>
                            </template>
                        </div>
                        <div class="min-w-0 pb-2">
                            <h1 class="text-3xl font-bold tracking-tight">
                                {{ profile.name }}
                            </h1>
                            <p class="text-muted-foreground text-sm">
                                @{{ profile.username }}
                            </p>
                            <p v-if="profile.headline" class="mt-1 text-sm font-medium">
                                {{ profile.headline }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pb-2">
                        <Button
                            v-if="profile.is_own"
                            as-child
                            variant="secondary"
                            class="rounded-lg"
                        >
                            <Link :href="edit()">Edit profile</Link>
                        </Button>
                        <template v-else>
                            <Button
                                type="button"
                                class="rounded-lg"
                                :loading="connecting"
                                :disabled="connection === 'accepted' || connection === 'pending_outgoing'"
                                @click="connect"
                            >
                                <Check v-if="connection === 'accepted'" class="size-4" />
                                <UserPlus v-else class="size-4" />
                                {{ connectLabel }}
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                class="rounded-lg"
                                @click="message"
                            >
                                <MessageSquare class="size-4" />
                                Message
                            </Button>
                        </template>
                    </div>
                </div>

                <div class="flex gap-1 border-t">
                    <span class="text-primary border-primary border-b-2 px-4 py-3 text-sm font-semibold">
                        Posts
                    </span>
                </div>
            </div>
        </div>

        <div class="mx-auto grid max-w-5xl gap-4 px-4 py-4 lg:grid-cols-[340px_minmax(0,1fr)]">
            <aside class="space-y-4 lg:sticky lg:top-4 lg:self-start">
                <div class="glass-panel rounded-xl p-4">
                    <h2 class="text-xl font-bold">Intro</h2>
                    <p
                        v-if="profile.bio"
                        class="mt-3 text-sm leading-relaxed whitespace-pre-wrap"
                    >
                        {{ profile.bio }}
                    </p>
                    <p
                        v-else
                        class="text-muted-foreground mt-3 text-sm"
                    >
                        No bio yet.
                    </p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li class="capitalize">
                            <span class="text-muted-foreground">Account · </span>
                            {{ profile.type }}
                        </li>
                        <li v-if="joined">
                            <span class="text-muted-foreground">Joined · </span>
                            {{ joined }}
                        </li>
                        <li v-if="formatUsdHourly(profile.hourly_rate_cents)">
                            <span class="text-muted-foreground">Rate · </span>
                            {{ formatUsdHourly(profile.hourly_rate_cents) }}
                        </li>
                        <li v-if="profile.is_talent">Available as talent</li>
                        <li v-else-if="profile.is_mentor">Open to mentoring</li>
                        <li v-if="profile.website">
                            <a
                                :href="profile.website"
                                class="text-primary inline-flex cursor-pointer items-center gap-1 hover:underline"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Globe class="size-3.5" />
                                {{ profile.website.replace(/^https?:\/\//, '') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>

            <section class="min-w-0">
                <Deferred data="signals">
                    <template #fallback>
                        <div class="space-y-4">
                            <Skeleton v-for="n in 3" :key="n" class="h-28 w-full rounded-2xl" />
                        </div>
                    </template>

                    <InfiniteScroll data="signals" :buffer="400">
                        <div class="flex flex-col gap-4">
                            <SignalCard
                                v-for="signal in items"
                                :key="signal.id"
                                :signal="signal"
                            />

                            <div
                                v-if="items.length === 0"
                                class="glass-panel text-muted-foreground rounded-xl px-4 py-16 text-center text-sm"
                            >
                                No posts yet.
                            </div>
                        </div>
                    </InfiniteScroll>
                </Deferred>
            </section>
        </div>
    </div>
</template>

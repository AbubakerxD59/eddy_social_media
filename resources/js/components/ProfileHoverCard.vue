<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, MessageSquare, UserPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { getInitials } from '@/composables/useInitials';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError, notifySuccess } from '@/lib/notify';
import { cn } from '@/lib/utils';
import type { PublicUser } from '@/types/social';

type ConnectionState = 'none' | 'pending_outgoing' | 'pending_incoming' | 'accepted';

const props = defineProps<{
    user: PublicUser;
    class?: string;
}>();

const page = usePage();
const viewerId = computed(() => page.props.auth.user?.id ?? null);
const isOwn = computed(() => viewerId.value !== null && viewerId.value === props.user.id);
const open = ref(false);
const loading = ref(false);
const connecting = ref(false);
const connection = ref<ConnectionState>('none');
const profile = ref<PublicUser>(props.user);

watch(
    () => props.user,
    (user) => {
        profile.value = user;
    },
);

const href = computed(() => `/@${props.user.username}`);

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

const loadCard = async () => {
    if (!viewerId.value) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/users/${props.user.id}/card`, {
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            user: PublicUser;
            connection: ConnectionState;
        };

        profile.value = payload.user;
        connection.value = payload.connection;
    } finally {
        loading.value = false;
    }
};

const onOpenChange = (value: boolean) => {
    open.value = value;

    if (value) {
        void loadCard();
    }
};

const connect = async () => {
    if (!viewerId.value) {
        router.visit('/login');
        return;
    }

    if (isOwn.value || connection.value === 'accepted' || connection.value === 'pending_outgoing' || connecting.value) {
        return;
    }

    connecting.value = true;

    try {
        const response = await fetch(`/users/${props.user.id}/connect`, {
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
    if (!viewerId.value) {
        router.visit('/login');
        return;
    }

    router.visit(`/messages/with/${props.user.id}`);
};
</script>

<template>
    <HoverCard :open="open" @update:open="onOpenChange">
        <HoverCardTrigger as-child>
            <Link
                :href="href"
                :class="cn('hover:underline cursor-pointer font-semibold underline-offset-2', props.class)"
                @click.stop
            >
                {{ user.name }}
            </Link>
        </HoverCardTrigger>
        <HoverCardContent class="w-80 overflow-hidden p-0" @click.stop>
            <Link :href="href" class="relative block h-24 cursor-pointer overflow-hidden">
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
            </Link>
            <div class="px-4 pb-4">
                <div class="flex items-end gap-3">
                    <Link :href="href" class="relative -mt-10 shrink-0">
                        <Avatar class="border-background size-20 border-4 shadow-md">
                            <AvatarImage
                                v-if="profile.avatar"
                                :src="profile.avatar"
                                :alt="profile.name"
                            />
                            <AvatarFallback class="text-xl">
                                {{ getInitials(profile.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Link>
                    <div class="min-w-0 flex-1 pb-1">
                        <Link :href="href" class="hover:underline cursor-pointer text-[15px] font-semibold underline-offset-2">
                            {{ profile.name }}
                        </Link>
                        <p class="text-muted-foreground truncate text-sm">
                            @{{ profile.username }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="profile.headline"
                    class="mt-3 line-clamp-2 text-sm"
                >
                    {{ profile.headline }}
                </p>
                <p
                    v-if="profile.bio"
                    class="text-muted-foreground mt-2 line-clamp-3 text-sm"
                >
                    {{ profile.bio }}
                </p>
                <div v-if="!isOwn" class="mt-4 flex items-center gap-2">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        class="rounded-full"
                        :loading="connecting"
                        :disabled="connection === 'accepted' || connection === 'pending_outgoing' || loading"
                        :aria-label="connectLabel"
                        :title="connectLabel"
                        @click="connect"
                    >
                        <Check v-if="connection === 'accepted'" class="size-4" />
                        <UserPlus v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        class="rounded-full"
                        aria-label="Message"
                        title="Message"
                        @click="message"
                    >
                        <MessageSquare class="size-4" />
                    </Button>
                </div>
            </div>
        </HoverCardContent>
    </HoverCard>
</template>

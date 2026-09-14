<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { MessagesSquare } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { csrfHeaders } from '@/lib/csrf';
import { formatRelativeTime } from '@/lib/relativeTime';
import { cn } from '@/lib/utils';
import type { InboxConversation } from '@/types/messages';

const POLL_MS = 8000;

const page = usePage();
const root = ref<HTMLElement | null>(null);
const open = ref(false);
const loadingAll = ref(false);
const items = ref<InboxConversation[]>([]);
const unreadCount = ref(0);

const unreadLabel = computed(() => {
    if (unreadCount.value < 1) {
        return null;
    }

    return unreadCount.value > 9 ? '9+' : String(unreadCount.value);
});

const applyPayload = (payload: { unread_count?: number; recent?: InboxConversation[]; conversations?: InboxConversation[] }) => {
    if (typeof payload.unread_count === 'number') {
        unreadCount.value = payload.unread_count;
    }

    const next = payload.conversations ?? payload.recent;

    if (next) {
        items.value = next;
    }
};

const fetchInbox = async (all = false): Promise<boolean> => {
    const response = await fetch(all ? '/conversations?all=1' : '/conversations', {
        headers: csrfHeaders(),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return false;
    }

    applyPayload((await response.json()) as { unread_count: number; conversations: InboxConversation[] });

    return true;
};

const toggleOpen = () => {
    open.value = !open.value;

    if (open.value) {
        void fetchInbox();
    }
};

const closePanel = () => {
    open.value = false;
};

const openChat = (chat: InboxConversation) => {
    closePanel();
    router.visit(`/messages/${chat.id}`);
};

const viewAll = () => {
    loadingAll.value = true;
    closePanel();
    router.visit('/messages', {
        onFinish: () => {
            loadingAll.value = false;
        },
    });
};

onClickOutside(root, () => {
    if (open.value) {
        closePanel();
    }
});

watch(
    () => page.props.chats,
    (payload) => {
        if (payload) {
            applyPayload(payload);
        }
    },
    { deep: true },
);

watch(
    () => page.props.messages_unread_count,
    (count) => {
        if (typeof count === 'number') {
            unreadCount.value = count;
        }
    },
);

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    applyPayload(page.props.chats ?? { unread_count: Number(page.props.messages_unread_count ?? 0), recent: [] });
    pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            void fetchInbox();
        }
    }, POLL_MS);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <div ref="root" class="relative">
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="relative rounded-full"
            aria-label="Messages"
            :aria-expanded="open"
            @click="toggleOpen"
        >
            <MessagesSquare class="size-5" />
            <span
                v-if="unreadLabel"
                class="bg-destructive text-destructive-foreground absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold"
            >
                {{ unreadLabel }}
            </span>
        </Button>

        <div
            v-if="open"
            class="glass-popup absolute top-full right-0 z-50 mt-2 flex h-[28rem] max-h-[calc(100dvh-5.5rem)] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border shadow-none"
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <p class="text-sm font-semibold">Chats</p>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <button
                    v-for="chat in items"
                    :key="chat.id"
                    type="button"
                    class="hover:bg-accent/70 flex w-full cursor-pointer items-start gap-3 px-3 py-2.5 text-left"
                    :class="cn(chat.unread && 'bg-primary/8')"
                    @click="openChat(chat)"
                >
                    <Avatar class="size-11">
                        <AvatarImage
                            v-if="chat.peer?.avatar"
                            :src="chat.peer.avatar"
                            :alt="chat.peer.name"
                        />
                        <AvatarFallback class="bg-primary/20 text-primary text-xs">
                            {{ getInitials(chat.peer?.name ?? 'Chat') }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-[13px] font-semibold">
                                {{ chat.peer?.name ?? 'Unknown' }}
                            </p>
                            <span class="text-muted-foreground shrink-0 text-[11px]">
                                {{ formatRelativeTime(chat.last_message_at) }}
                            </span>
                        </div>
                        <p
                            class="mt-0.5 truncate text-[12px]"
                            :class="chat.unread ? 'text-foreground font-medium' : 'text-muted-foreground'"
                        >
                            {{ chat.last_message_preview || 'No messages yet' }}
                        </p>
                        <p
                            v-if="chat.status === 'pending'"
                            class="text-primary mt-0.5 text-[11px] font-medium"
                        >
                            {{ chat.is_initiator ? 'Request sent' : 'Message request' }}
                        </p>
                    </div>
                    <span
                        v-if="chat.unread"
                        class="bg-primary mt-2 size-2.5 shrink-0 rounded-full"
                    />
                </button>
                <p
                    v-if="items.length === 0"
                    class="text-muted-foreground px-4 py-10 text-center text-sm"
                >
                    No chats yet.
                </p>
            </div>

            <div class="border-t p-2">
                <Button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    :loading="loadingAll"
                    @click="viewAll"
                >
                    View all chats
                </Button>
            </div>
        </div>
    </div>
</template>

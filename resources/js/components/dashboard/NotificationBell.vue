<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Bell } from '@lucide/vue';
import { ref } from 'vue';
import NotificationItem from '@/components/dashboard/NotificationItem.vue';
import { Button } from '@/components/ui/button';
import { useNotifications } from '@/composables/useNotifications';

const {
    items,
    unreadLabel,
    open,
    expanded,
    loadingAll,
    toggleOpen,
    closePanel,
    viewAll,
    openNotification,
    respondToConnection,
    respondingId,
} = useNotifications();

const root = ref<HTMLElement | null>(null);

onClickOutside(root, () => {
    if (open.value) {
        closePanel();
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
            aria-label="Notifications"
            :aria-expanded="open"
            @click="toggleOpen"
        >
            <Bell class="size-5" />
            <span
                v-if="unreadLabel"
                class="bg-destructive text-destructive-foreground absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold"
            >
                {{ unreadLabel }}
            </span>
        </Button>

        <div
            v-if="open"
            class="glass-popup absolute top-full right-0 z-50 mt-2 flex w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border shadow-none"
            :class="
                expanded
                    ? 'h-[calc(100dvh-5.5rem)] max-h-[calc(100dvh-5.5rem)]'
                    : 'h-[28rem] max-h-[calc(100dvh-5.5rem)]'
            "
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <p class="text-sm font-semibold">Notifications</p>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <NotificationItem
                    v-for="notification in items"
                    :key="notification.id"
                    :notification="notification"
                    :responding="respondingId === notification.id"
                    @open="openNotification"
                    @accept="respondToConnection($event.id, 'accept')"
                    @reject="respondToConnection($event.id, 'reject')"
                />
                <p
                    v-if="items.length === 0"
                    class="text-muted-foreground px-4 py-10 text-center text-sm"
                >
                    No notifications yet.
                </p>
            </div>

            <div v-if="!expanded" class="border-t p-2">
                <Button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    :loading="loadingAll"
                    @click="viewAll"
                >
                    View all notifications
                </Button>
            </div>
        </div>
    </div>
</template>

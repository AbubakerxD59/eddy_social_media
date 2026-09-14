<script setup lang="ts">
import { X } from '@lucide/vue';
import NotificationItem from '@/components/dashboard/NotificationItem.vue';
import { Button } from '@/components/ui/button';
import { useNotifications } from '@/composables/useNotifications';

const { popups, open, openNotification, dismissPopup, respondToConnection, respondingId } = useNotifications();
</script>

<template>
    <div
        v-if="!open && popups.length"
        class="pointer-events-none fixed top-20 right-4 z-50 flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2"
    >
        <div
            v-for="notification in popups"
            :key="notification.id"
            class="glass-popup pointer-events-auto relative overflow-hidden rounded-xl border shadow-lg"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="absolute top-1.5 right-1.5 z-10 rounded-full"
                aria-label="Dismiss notification"
                @click="dismissPopup(notification.id)"
            >
                <X class="size-3.5" />
            </Button>
            <NotificationItem
                :notification="notification"
                compact
                class="pr-10"
                :responding="respondingId === notification.id"
                @open="openNotification"
                @accept="respondToConnection($event.id, 'accept')"
                @reject="respondToConnection($event.id, 'reject')"
            />
        </div>
    </div>
</template>

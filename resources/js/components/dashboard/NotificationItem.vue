<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { formatRelativeTime } from '@/lib/relativeTime';
import { cn } from '@/lib/utils';
import type { AppNotification } from '@/types/notifications';

const { notification, compact = false, responding = false } = defineProps<{
    notification: AppNotification;
    compact?: boolean;
    responding?: boolean;
}>();

const emit = defineEmits<{
    open: [notification: AppNotification];
    accept: [notification: AppNotification];
    reject: [notification: AppNotification];
}>();
</script>

<template>
    <div>
        <div
            class="hover:bg-accent/70 flex w-full items-start gap-3 px-3 py-2.5 text-left"
            :class="cn(!notification.read_at && 'bg-primary/8')"
        >
            <button
                type="button"
                class="flex min-w-0 flex-1 cursor-pointer items-start gap-3 text-left"
                @click="emit('open', notification)"
            >
                <Avatar :class="compact ? 'size-9' : 'size-11'">
                    <AvatarImage
                        v-if="notification.actor?.avatar"
                        :src="notification.actor.avatar"
                        :alt="notification.actor.name"
                    />
                    <AvatarFallback class="bg-primary/20 text-primary text-xs">
                        {{ getInitials(notification.actor?.name ?? notification.title) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] leading-snug font-medium">
                        {{ notification.title }}
                    </p>
                    <p
                        v-if="notification.body"
                        class="text-muted-foreground mt-0.5 line-clamp-2 text-[12px] leading-snug"
                    >
                        {{ notification.body }}
                    </p>
                    <p class="text-primary mt-1 text-[11px] font-medium">
                        {{ formatRelativeTime(notification.created_at) }}
                    </p>
                </div>
            </button>
            <span
                v-if="!notification.read_at"
                class="bg-primary mt-2 size-2.5 shrink-0 rounded-full"
            />
        </div>
        <div
            v-if="notification.can_accept || notification.can_reject"
            class="flex gap-2 px-3 pb-3 pl-[3.75rem]"
        >
            <Button
                v-if="notification.can_accept"
                type="button"
                size="sm"
                class="rounded-full"
                :loading="responding"
                @click.stop="emit('accept', notification)"
            >
                Accept
            </Button>
            <Button
                v-if="notification.can_reject"
                type="button"
                size="sm"
                variant="outline"
                class="rounded-full"
                :disabled="responding"
                @click.stop="emit('reject', notification)"
            >
                Decline
            </Button>
        </div>
    </div>
</template>

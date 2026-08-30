<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, BellOff, Bookmark, Ellipsis, Flag, Link, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError, notifySuccess } from '@/lib/notify';
import type { FeedSignal } from '@/types/social';

const { signal } = defineProps<{
    signal: FeedSignal;
}>();

const emit = defineEmits<{
    delete: [];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const saved = ref(signal.saved);
const reported = ref(signal.reported);
const authorMuted = ref(signal.author_muted);
const saving = ref(false);
const reporting = ref(false);
const muting = ref(false);

watch(
    () => [signal.saved, signal.reported, signal.author_muted] as const,
    ([nextSaved, nextReported, nextMuted]) => {
        saved.value = nextSaved;
        reported.value = nextReported;
        authorMuted.value = nextMuted;
    },
);

const shareUrl = computed(() => {
    if (typeof window === 'undefined') {
        return `/s/${signal.id}`;
    }

    return `${window.location.origin}/s/${signal.id}`;
});

const requireAuth = (): boolean => {
    if (user.value) {
        return true;
    }

    window.location.href = '/login';

    return false;
};

const toggleSave = async () => {
    if (!requireAuth() || saving.value) {
        return;
    }

    saving.value = true;
    const nextSaved = !saved.value;
    saved.value = nextSaved;

    try {
        const response = await fetch(`/signals/${signal.id}/save`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';
            return;
        }

        if (!response.ok) {
            throw new Error('Save failed');
        }

        const data = (await response.json()) as { saved: boolean };
        saved.value = data.saved;
        notifySuccess(data.saved ? 'Signal saved.' : 'Removed from saved.');
    } catch {
        saved.value = !nextSaved;
        notifyError('Could not update saved signals.');
    } finally {
        saving.value = false;
    }
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        notifySuccess('Link copied.');
    } catch {
        notifyError('Could not copy the link.');
    }
};

const reportSignal = async () => {
    if (!requireAuth() || reporting.value || reported.value || !signal.can_report) {
        return;
    }

    reporting.value = true;

    try {
        const response = await fetch(`/signals/${signal.id}/report`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';
            return;
        }

        if (!response.ok) {
            throw new Error('Report failed');
        }

        reported.value = true;
        notifySuccess('Signal reported.');
    } catch {
        notifyError('Could not report this signal.');
    } finally {
        reporting.value = false;
    }
};

const toggleMute = () => {
    if (!requireAuth() || muting.value || !signal.can_mute) {
        return;
    }

    muting.value = true;

    router.post(`/users/${signal.author.id}/mute`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            authorMuted.value = !authorMuted.value;
        },
        onFinish: () => {
            muting.value = false;
        },
    });
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground -mr-1.5"
            >
                <Ellipsis class="size-4" />
                <span class="sr-only">Signal actions</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="rounded-[20px] border-neutral-700 bg-neutral-800 p-2 text-neutral-100 shadow-lg [&_[data-slot=dropdown-menu-separator]]:bg-neutral-600"
        >
            <DropdownMenuItem
                :disabled="saving"
                class="rounded-[10px] text-neutral-100 hover:bg-white/10 hover:text-white focus:bg-white/10 focus:text-white [&_svg]:text-neutral-200"
                @click="toggleSave"
            >
                <Bookmark :class="saved && 'fill-current'" />
                {{ saved ? 'Unsave' : 'Save' }}
            </DropdownMenuItem>
            <DropdownMenuItem
                class="rounded-[10px] text-neutral-100 hover:bg-white/10 hover:text-white focus:bg-white/10 focus:text-white [&_svg]:text-neutral-200"
                @click="copyLink"
            >
                <Link />
                Copy link
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="signal.can_report || !user"
                :disabled="reported || reporting"
                class="rounded-[10px] text-neutral-100 hover:bg-white/10 hover:text-white focus:bg-white/10 focus:text-white [&_svg]:text-neutral-200"
                @click="reportSignal"
            >
                <Flag />
                {{ reported ? 'Reported' : 'Report' }}
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="signal.can_mute || !user"
                :disabled="muting"
                class="rounded-[10px] text-neutral-100 hover:bg-white/10 hover:text-white focus:bg-white/10 focus:text-white [&_svg]:text-neutral-200"
                @click="toggleMute"
            >
                <Bell v-if="authorMuted" />
                <BellOff v-else />
                {{ authorMuted ? 'Unmute' : 'Mute' }}
            </DropdownMenuItem>
            <template v-if="signal.can_delete">
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    class="rounded-[10px] text-red-400 hover:bg-white/10 hover:text-red-400 focus:bg-white/10 focus:text-red-400 [&_svg]:text-red-400"
                    @click="emit('delete')"
                >
                    <Trash2 />
                    Delete
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError, notifySuccess } from '@/lib/notify';
import type { AppNotification, NotificationListResponse } from '@/types/notifications';

const POLL_MS = 8000;
const POPUP_MS = 8000;

const items = ref<AppNotification[]>([]);
const popups = ref<AppNotification[]>([]);
const unreadCount = ref(0);
const open = ref(false);
const expanded = ref(false);
const loadingAll = ref(false);
const respondingId = ref<number | null>(null);
const cursor = ref(0);
const seeded = ref(false);

let pollTimer: ReturnType<typeof setInterval> | null = null;
let listeners = 0;

const mergeNewestFirst = (incoming: AppNotification[], existing: AppNotification[]): AppNotification[] => {
    const seen = new Set(existing.map((item) => item.id));
    const fresh = incoming.filter((item) => !seen.has(item.id));

    if (fresh.length === 0) {
        return existing;
    }

    return [...fresh, ...existing];
};

const bumpCursor = (list: AppNotification[]) => {
    for (const item of list) {
        if (item.id > cursor.value) {
            cursor.value = item.id;
        }
    }
};

const applyUnread = (count: number) => {
    unreadCount.value = count;
};

async function fetchNotifications(query: string): Promise<NotificationListResponse | null> {
    const response = await fetch(`/notifications${query}`, {
        headers: csrfHeaders(),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return null;
    }

    return (await response.json()) as NotificationListResponse;
}

async function poll(): Promise<void> {
    if (document.visibilityState !== 'visible') {
        return;
    }

    const payload = await fetchNotifications(`?after_id=${cursor.value}`);

    if (!payload) {
        return;
    }

    applyUnread(payload.unread_count);

    const fresh = payload.notifications.filter((item) => item.id > cursor.value);

    if (fresh.length === 0) {
        return;
    }

    bumpCursor(fresh);
    items.value = mergeNewestFirst([...fresh].reverse(), items.value);

    if (!open.value) {
        popups.value = [...popups.value, ...fresh];

        for (const item of fresh) {
            window.setTimeout(() => dismissPopup(item.id), POPUP_MS);
        }
    }
}

function seedFromPage(payload: { unread_count: number; recent: AppNotification[] } | undefined): void {
    if (!payload) {
        return;
    }

    applyUnread(payload.unread_count);
    items.value = mergeNewestFirst(payload.recent ?? [], items.value);
    bumpCursor(payload.recent ?? []);
    seeded.value = true;
}

function dismissPopup(id: number): void {
    popups.value = popups.value.filter((item) => item.id !== id);
}

async function markRead(id: number): Promise<void> {
    const current = items.value.find((item) => item.id === id);

    if (current && current.read_at) {
        return;
    }

    items.value = items.value.map((item) =>
        item.id === id ? { ...item, read_at: item.read_at ?? new Date().toISOString() } : item,
    );
    unreadCount.value = Math.max(0, unreadCount.value - (current?.read_at ? 0 : 1));

    const response = await fetch(`/notifications/${id}/read`, {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
        body: '{}',
    });

    if (!response.ok) {
        return;
    }

    const payload = (await response.json()) as { unread_count: number };
    applyUnread(payload.unread_count);
}

async function respondToConnection(id: number, action: 'accept' | 'reject'): Promise<void> {
    respondingId.value = id;

    try {
        const response = await fetch(`/notifications/${id}/${action}`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: '{}',
        });

        let payload: { unread_count?: number; notification?: AppNotification; message?: string } = {};

        try {
            payload = (await response.json()) as typeof payload;
        } catch {
            payload = {};
        }

        if (!response.ok) {
            notifyError(payload.message ?? 'Could not update that request.');
            return;
        }

        if (payload.unread_count !== undefined) {
            applyUnread(payload.unread_count);
        }

        if (payload.notification) {
            items.value = items.value.map((item) => (item.id === id ? payload.notification! : item));
            popups.value = popups.value.map((item) => (item.id === id ? payload.notification! : item));
        }

        notifySuccess(payload.message ?? (action === 'accept' ? 'Request accepted.' : 'Request declined.'));
    } finally {
        respondingId.value = null;
    }
}

async function openNotification(notification: AppNotification): Promise<void> {
    dismissPopup(notification.id);
    open.value = false;
    expanded.value = false;
    await markRead(notification.id);
    router.visit(notification.url);
}

async function viewAll(): Promise<void> {
    loadingAll.value = true;

    try {
        const payload = await fetchNotifications('?all=1');

        if (!payload) {
            notifyError('Could not load notifications.');
            return;
        }

        applyUnread(payload.unread_count);
        items.value = payload.notifications;
        bumpCursor(payload.notifications);
        expanded.value = true;
    } finally {
        loadingAll.value = false;
    }
}

function toggleOpen(): void {
    open.value = !open.value;

    if (!open.value) {
        expanded.value = false;
    } else {
        popups.value = [];
    }
}

function closePanel(): void {
    open.value = false;
    expanded.value = false;
}

function startPolling(): void {
    if (pollTimer) {
        return;
    }

    pollTimer = setInterval(() => {
        void poll();
    }, POLL_MS);
}

function stopPolling(): void {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

export function useNotifications() {
    const page = usePage();
    const unreadLabel = computed(() => {
        if (unreadCount.value < 1) {
            return null;
        }

        return unreadCount.value > 9 ? '9+' : String(unreadCount.value);
    });

    watch(
        () => page.props.notifications,
        (payload) => {
            if (!payload) {
                return;
            }

            applyUnread(payload.unread_count);
            items.value = mergeNewestFirst(payload.recent ?? [], items.value);
            bumpCursor(payload.recent ?? []);
        },
        { deep: true },
    );

    onMounted(() => {
        listeners += 1;

        if (!seeded.value) {
            seedFromPage(page.props.notifications);
        }

        startPolling();
    });

    onUnmounted(() => {
        listeners = Math.max(0, listeners - 1);

        if (listeners === 0) {
            stopPolling();
        }
    });

    return {
        items,
        popups,
        unreadCount,
        unreadLabel,
        open,
        expanded,
        loadingAll,
        respondingId,
        toggleOpen,
        closePanel,
        viewAll,
        openNotification,
        dismissPopup,
        markRead,
        respondToConnection,
    };
}

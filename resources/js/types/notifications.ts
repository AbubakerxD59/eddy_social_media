import type { PublicUser } from '@/types/social';

export type AppNotification = {
    id: number;
    type: string;
    title: string;
    body: string | null;
    url: string;
    read_at: string | null;
    created_at: string | null;
    actor: PublicUser | null;
    connection_id?: number | null;
    conversation_id?: string | null;
    can_accept?: boolean;
    can_reject?: boolean;
};

export type NotificationPayload = {
    unread_count: number;
    recent: AppNotification[];
};

export type NotificationListResponse = {
    unread_count: number;
    notifications: AppNotification[];
};

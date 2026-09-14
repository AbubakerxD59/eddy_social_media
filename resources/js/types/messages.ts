import type { PublicUser } from '@/types/social';

export type ConversationStatus = 'pending' | 'accepted' | 'rejected';

export type MessageKind = 'text' | 'image' | 'video' | 'file';

export type ChatReplyQuote = {
    id: string;
    user_id: number;
    user_name: string | null;
    kind: MessageKind;
    body: string | null;
    url: string | null;
    original_name: string | null;
    deleted?: boolean;
};

export type ChatMessage = {
    id: string;
    user_id: number;
    group_id: string | null;
    kind: MessageKind;
    body: string | null;
    url: string | null;
    mime_type: string | null;
    original_name: string | null;
    size: number | null;
    created_at: string | null;
    edited_at: string | null;
    deleted_at: string | null;
    can_edit: boolean;
    can_delete: boolean;
    reply_to: ChatReplyQuote | null;
};

export type InboxConversation = {
    id: string;
    status: ConversationStatus;
    peer: PublicUser | null;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread: boolean;
    can_send: boolean;
    can_respond: boolean;
    is_initiator: boolean;
};

export type ChatInboxPayload = {
    unread_count: number;
    recent: InboxConversation[];
};

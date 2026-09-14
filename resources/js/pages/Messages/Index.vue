<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleAlert, Ellipsis, File as FileIcon, FileText, Image as ImageIcon, Pencil, Play, Plus, Reply, Send, Trash2, Video, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ChatMessageAlbum from '@/components/ChatMessageAlbum.vue';
import ChatMediaGallery, { type ChatGalleryItem } from '@/components/ChatMediaGallery.vue';
import ChatReplyQuote from '@/components/ChatReplyQuote.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import UploadProgressRing from '@/components/UploadProgressRing.vue';
import ProfileHoverCard from '@/components/ProfileHoverCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { csrfFormHeaders, csrfHeaders } from '@/lib/csrf';
import { firstValidationError, notifyError, notifySuccess } from '@/lib/notify';
import { formatRelativeTime } from '@/lib/relativeTime';
import { cn } from '@/lib/utils';
import type { ChatMessage, ChatReplyQuote as ReplyQuote, InboxConversation, MessageKind } from '@/types/messages';

type MessageStatus = 'sent' | 'pending' | 'failed';

type ThreadMessage = ChatMessage & {
    client_id: string;
    status: MessageStatus;
    progress: number | null;
};

type MessageBlock = {
    key: string;
    user_id: number;
    status: MessageStatus;
    body: string | null;
    created_at: string | null;
    edited_at: string | null;
    deleted: boolean;
    reply_to: ReplyQuote | null;
    messages: ThreadMessage[];
};

type DraftAttachment = {
    id: string;
    file: File;
    kind: MessageKind;
    previewUrl: string | null;
};

type PendingPayload = {
    conversationId: string;
    body: string;
    files: File[];
    clientIds: string[];
    replyToId: string | null;
};

type AttachmentKind = 'photo' | 'video' | 'document';

const POLL_MS = 3000;
const DELETE_WINDOW_MS = 10 * 60 * 1000;
const MAX_ATTACHMENTS = 10;
const PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif';
const VIDEO_ACCEPT = 'video/mp4,video/webm,video/quicktime,video/ogg,.mp4,.webm,.mov,.m4v';
const DOCUMENT_ACCEPT =
    '.pdf,.doc,.docx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
const PHOTO_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'webp', 'gif']);
const PHOTO_MIMES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
const DOCUMENT_MIMES = new Set([
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);
const PUBLIC_ID_PATTERN = /^[A-Za-z0-9]{8}$/;

const isPublicId = (id: string): boolean => PUBLIC_ID_PATTERN.test(id);

const toThreadMessage = (message: ChatMessage): ThreadMessage => ({
    ...message,
    group_id: message.group_id ?? null,
    reply_to: message.reply_to ?? null,
    edited_at: message.edited_at ?? null,
    deleted_at: message.deleted_at ?? null,
    can_edit: message.can_edit ?? false,
    can_delete: message.can_delete ?? false,
    client_id: `server-${message.id}`,
    status: 'sent',
    progress: null,
});

const props = defineProps<{
    conversations: InboxConversation[];
    conversation: InboxConversation | null;
    messages: ChatMessage[];
    unread_count?: number;
}>();

const page = usePage();
const viewerId = computed(() => page.props.auth.user?.id ?? null);
const inbox = ref<InboxConversation[]>([...props.conversations]);
const thread = ref<InboxConversation | null>(props.conversation);
const items = ref<ThreadMessage[]>(props.messages.map(toThreadMessage));
const body = ref('');
const drafts = ref<DraftAttachment[]>([]);
const responding = ref(false);
const extras = ref<Record<string, ThreadMessage[]>>({});
const pendingPayloads = new Map<string, PendingPayload>();
const deleteTarget = ref<MessageBlock | null>(null);
const deleteOpen = ref(false);
const nowMs = ref(Date.now());
const syncedAt = ref(new Date().toISOString());
const editingKey = ref<string | null>(null);
const editDraft = ref('');
const galleryOpen = ref(false);
const galleryIndex = ref(0);
const gallerySource = ref<'thread' | 'drafts'>('thread');
const replyingTo = ref<ReplyQuote | null>(null);
const highlightedKey = ref<string | null>(null);
const composerInput = ref<HTMLTextAreaElement | null>(null);
const scroller = ref<HTMLElement | null>(null);
const attachOpen = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);
const videoInput = ref<HTMLInputElement | null>(null);
const documentInput = ref<HTMLInputElement | null>(null);
let localSeq = 0;
let pollTimer: ReturnType<typeof setInterval> | null = null;

const fileExtension = (name: string): string => {
    const parts = name.split('.');

    return parts.length > 1 ? (parts.pop() ?? '').toLowerCase() : '';
};

const isAllowedAttachment = (kind: AttachmentKind, picked: File): boolean => {
    const extension = fileExtension(picked.name);

    if (kind === 'photo') {
        return PHOTO_MIMES.has(picked.type) || PHOTO_EXTENSIONS.has(extension);
    }

    if (kind === 'video') {
        return picked.type.startsWith('video/');
    }

    return DOCUMENT_MIMES.has(picked.type) || DOCUMENT_EXTENSIONS.has(extension);
};

const kindForFile = (picked: File): MessageKind => {
    if (PHOTO_MIMES.has(picked.type) || PHOTO_EXTENSIONS.has(fileExtension(picked.name))) {
        return 'image';
    }

    if (picked.type.startsWith('video/')) {
        return 'video';
    }

    return 'file';
};

const previewForDrafts = (text: string, attachments: DraftAttachment[]): string => {
    const images = attachments.filter((item) => item.kind === 'image').length;
    const videos = attachments.filter((item) => item.kind === 'video').length;
    const files = attachments.filter((item) => item.kind === 'file').length;
    const total = attachments.length;

    if (total > 1) {
        if (images === total) {
            return `${images} photos`;
        }

        if (videos === total) {
            return `${videos} videos`;
        }

        if (files === total) {
            return `${files} files`;
        }

        return `${total} files`;
    }

    if (attachments[0]?.kind === 'image') {
        return 'Photo';
    }

    if (attachments[0]?.kind === 'video') {
        return 'Video';
    }

    if (attachments[0]?.kind === 'file') {
        return attachments[0].file.name || 'File';
    }

    return text.slice(0, 80);
};

const revokeIfBlob = (url: string | null) => {
    if (url?.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
};

const extrasFor = (conversationId: string): ThreadMessage[] => extras.value[conversationId] ?? [];

const writeExtras = (conversationId: string, next: ThreadMessage[]) => {
    extras.value = { ...extras.value, [conversationId]: next };
};

const payloadKeyFor = (item: ThreadMessage): string => item.group_id ?? item.client_id;

const markStatus = (conversationId: string, clientIds: string[], status: MessageStatus) => {
    const ids = new Set(clientIds);
    const nextItem = (item: ThreadMessage): ThreadMessage => {
        if (!ids.has(item.client_id)) {
            return item;
        }

        return {
            ...item,
            status,
            progress: status === 'pending' ? (item.progress ?? 0) : null,
        };
    };

    if (thread.value?.id === conversationId) {
        items.value = items.value.map(nextItem);
    }

    writeExtras(conversationId, extrasFor(conversationId).map(nextItem));
};

const splitUploadProgress = (count: number, overall: number): number[] => {
    if (count <= 0) {
        return [];
    }

    const scaled = (Math.max(0, Math.min(100, overall)) / 100) * count * 100;

    return Array.from({ length: count }, (_, index) => Math.max(0, Math.min(100, scaled - index * 100)));
};

const setUploadProgress = (conversationId: string, clientIds: string[], overall: number) => {
    const percents = splitUploadProgress(clientIds.length, overall);
    const byId = new Map(clientIds.map((id, index) => [id, percents[index] ?? 0]));
    const nextItem = (item: ThreadMessage): ThreadMessage => {
        if (!byId.has(item.client_id) || item.status !== 'pending') {
            return item;
        }

        return { ...item, progress: byId.get(item.client_id) ?? 0 };
    };

    if (thread.value?.id === conversationId) {
        items.value = items.value.map(nextItem);
    }

    writeExtras(conversationId, extrasFor(conversationId).map(nextItem));
};

type MessageStoreResponse = {
    messages?: ChatMessage[];
    message?: ChatMessage | string;
    conversation?: InboxConversation;
    errors?: Record<string, string | string[]>;
};

const postConversationMessage = (
    url: string,
    form: FormData,
    onProgress: (percent: number) => void,
): Promise<{ ok: boolean; data: MessageStoreResponse }> => {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.withCredentials = true;

        Object.entries(csrfFormHeaders()).forEach(([header, value]) => {
            if (typeof value === 'string') {
                xhr.setRequestHeader(header, value);
            }
        });

        xhr.upload.onprogress = (event) => {
            if (!event.lengthComputable || event.total <= 0) {
                return;
            }

            onProgress(Math.max(0, Math.min(100, (event.loaded / event.total) * 100)));
        };

        xhr.onload = () => {
            try {
                resolve({
                    ok: xhr.status >= 200 && xhr.status < 300,
                    data: JSON.parse(xhr.responseText) as MessageStoreResponse,
                });
            } catch {
                reject(new Error('Could not send the message.'));
            }
        };

        xhr.onerror = () => reject(new Error('Could not send the message.'));
        xhr.send(form);
    });
};

const removeLocalGroup = (conversationId: string, clientIds: string[]) => {
    const ids = new Set(clientIds);

    extrasFor(conversationId)
        .filter((item) => ids.has(item.client_id))
        .forEach((item) => revokeIfBlob(item.url));

    writeExtras(
        conversationId,
        extrasFor(conversationId).filter((item) => !ids.has(item.client_id)),
    );

    if (thread.value?.id === conversationId) {
        items.value
            .filter((item) => ids.has(item.client_id))
            .forEach((item) => revokeIfBlob(item.url));
        items.value = items.value.filter((item) => !ids.has(item.client_id));
    }
};

const matchesPending = (local: ThreadMessage, incoming: ChatMessage): boolean => {
    return (
        local.status === 'pending' &&
        local.user_id === incoming.user_id &&
        local.kind === incoming.kind &&
        (local.body ?? null) === (incoming.body ?? null) &&
        local.original_name === incoming.original_name
    );
};

const mergeThread = (serverMessages: ChatMessage[], conversationId: string | null): ThreadMessage[] => {
    const server = serverMessages.map(toThreadMessage);
    const local = conversationId ? extrasFor(conversationId) : [];
    const remaining = local.filter((item) => {
        if (item.status === 'failed') {
            return true;
        }

        return !server.some((incoming) => matchesPending(item, incoming));
    });

    return [...server, ...remaining];
};

watch(
    () => [props.conversations, props.conversation, props.messages] as const,
    ([conversations, conversation, messages], previous) => {
        inbox.value = [...conversations];
        const previousId = previous?.[1]?.id ?? null;

        if (conversation?.id !== previousId) {
            replyingTo.value = null;
            highlightedKey.value = null;
            editingKey.value = null;
            editDraft.value = '';
        }

        thread.value = conversation;
        items.value = mergeThread(messages, conversation?.id ?? null);
        void scrollToBottom();
    },
);

const peer = computed(() => thread.value?.peer ?? null);
const canSend = computed(() => Boolean(thread.value?.can_send));
const blocks = computed((): MessageBlock[] => {
    const grouped: MessageBlock[] = [];

    items.value.forEach((item) => {
        const last = grouped.at(-1);

        if (
            item.group_id &&
            last &&
            last.messages[0]?.group_id === item.group_id &&
            last.user_id === item.user_id
        ) {
            last.messages.push(item);

            if (item.body && !last.body) {
                last.body = item.body;
            }

            if (item.status === 'failed') {
                last.status = 'failed';
            }

            last.created_at = last.created_at ?? item.created_at;
            last.edited_at = item.edited_at ?? last.edited_at;
            last.deleted = last.messages.every((message) => Boolean(message.deleted_at));
            return;
        }

        grouped.push({
            key: item.group_id ?? item.client_id,
            user_id: item.user_id,
            status: item.status,
            body: item.body,
            created_at: item.created_at,
            edited_at: item.edited_at ?? null,
            deleted: Boolean(item.deleted_at),
            reply_to: item.reply_to ?? null,
            messages: [item],
        });
    });

    return grouped;
});

const composerHint = computed(() => {
    if (!thread.value) {
        return 'Select a conversation to start chatting.';
    }

    if (thread.value.status === 'rejected') {
        return 'This message request was declined.';
    }

    if (thread.value.status === 'pending' && thread.value.can_respond) {
        return 'Accept this request to reply.';
    }

    if (thread.value.status === 'pending' && thread.value.is_initiator) {
        return 'Message request sent. You can send more while they decide.';
    }

    return null;
});

const formatBytes = (size: number | null): string => {
    if (!size) {
        return '';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const formatClock = (iso: string | null): string => {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
};

const scrollToBottom = async () => {
    await nextTick();

    if (scroller.value) {
        scroller.value.scrollTop = scroller.value.scrollHeight;
    }
};

const upsertInbox = (conversation: InboxConversation) => {
    const others = inbox.value.filter((item) => item.id !== conversation.id);
    inbox.value = [conversation, ...others].sort((a, b) => {
        return (b.last_message_at ?? '').localeCompare(a.last_message_at ?? '');
    });
};

const resetInputs = () => {
    [photoInput.value, videoInput.value, documentInput.value].forEach((input) => {
        if (input) {
            input.value = '';
        }
    });
};

const toGalleryItem = (item: {
    id: number | string;
    kind: MessageKind;
    url: string | null;
    mime_type: string | null;
}): ChatGalleryItem | null => {
    if ((item.kind !== 'image' && item.kind !== 'video') || !item.url) {
        return null;
    }

    return {
        id: item.id,
        kind: item.kind,
        url: item.url,
        mime_type: item.mime_type,
    };
};

const threadGalleryItems = computed((): ChatGalleryItem[] => {
    return items.value
        .filter((item) => !item.deleted_at)
        .map((item) => toGalleryItem({
            id: item.client_id,
            kind: item.kind,
            url: item.url,
            mime_type: item.mime_type,
        }))
        .filter((item): item is ChatGalleryItem => item !== null);
});

const draftGalleryItems = computed((): ChatGalleryItem[] => {
    return drafts.value
        .map((draft) => toGalleryItem({
            id: draft.id,
            kind: draft.kind,
            url: draft.previewUrl,
            mime_type: draft.file.type,
        }))
        .filter((item): item is ChatGalleryItem => item !== null);
});

const galleryItems = computed((): ChatGalleryItem[] => {
    return gallerySource.value === 'drafts' ? draftGalleryItems.value : threadGalleryItems.value;
});

const openGallery = (source: 'thread' | 'drafts', id: number | string, fallbackUrl?: string | null) => {
    gallerySource.value = source;
    const list = source === 'drafts' ? draftGalleryItems.value : threadGalleryItems.value;
    const found = list.findIndex((item) => item.id === id || (fallbackUrl !== undefined && item.url === fallbackUrl));
    galleryIndex.value = found >= 0 ? found : 0;
    galleryOpen.value = true;
};

const openMedia = (item: { id: string | number; client_id?: string; kind: MessageKind; url: string | null; mime_type: string | null }) => {
    if ((item.kind !== 'image' && item.kind !== 'video') || !item.url) {
        return;
    }

    openGallery('thread', item.client_id ?? item.id, item.url);
};

const openDraft = (draft: DraftAttachment) => {
    if (!draft.previewUrl || (draft.kind !== 'image' && draft.kind !== 'video')) {
        return;
    }

    openGallery('drafts', draft.id, draft.previewUrl);
};

const onFileChange = (kind: AttachmentKind, event: Event) => {
    const input = event.target as HTMLInputElement;
    const selected = Array.from(input.files ?? []);
    input.value = '';

    if (selected.length === 0) {
        return;
    }

    const remaining = MAX_ATTACHMENTS - drafts.value.length;

    if (remaining <= 0) {
        notifyError(`You can attach up to ${MAX_ATTACHMENTS} files.`);
        return;
    }

    const next = selected.slice(0, remaining);
    const accepted: DraftAttachment[] = [];

    next.forEach((picked) => {
        if (!isAllowedAttachment(kind, picked)) {
            notifyError(
                kind === 'photo'
                    ? 'Choose a photo (JPG, PNG, WEBP, or GIF).'
                    : kind === 'video'
                      ? 'Choose a video file.'
                      : 'Choose a PDF, Word, or Excel file.',
            );
            return;
        }

        const fileKind = kindForFile(picked);
        accepted.push({
            id: `draft-${++localSeq}`,
            file: picked,
            kind: fileKind,
            previewUrl: fileKind === 'image' || fileKind === 'video' ? URL.createObjectURL(picked) : null,
        });
    });

    if (selected.length > remaining) {
        notifyError(`You can attach up to ${MAX_ATTACHMENTS} files.`);
    }

    drafts.value = [...drafts.value, ...accepted];
};

const pickAttachment = (kind: AttachmentKind) => {
    if (kind === 'photo') {
        photoInput.value?.click();
        return;
    }

    if (kind === 'video') {
        videoInput.value?.click();
        return;
    }

    documentInput.value?.click();
};

const removeDraft = (id: string) => {
    const current = drafts.value.find((item) => item.id === id);
    revokeIfBlob(current?.previewUrl ?? null);
    drafts.value = drafts.value.filter((item) => item.id !== id);
};

const lastServerPublicId = (): string | null => {
    for (let index = items.value.length - 1; index >= 0; index -= 1) {
        const id = items.value[index]?.id;

        if (id && isPublicId(id)) {
            return id;
        }
    }

    return null;
};

const patchInboxPreview = (conversationId: string, preview: string) => {
    const now = new Date().toISOString();
    const current =
        thread.value?.id === conversationId ? thread.value : inbox.value.find((item) => item.id === conversationId);

    if (!current) {
        return;
    }

    const next = {
        ...current,
        last_message_preview: preview,
        last_message_at: now,
        unread: false,
    };

    if (thread.value?.id === conversationId) {
        thread.value = next;
    }

    upsertInbox(next);
};

const deliver = async (key: string) => {
    const payload = pendingPayloads.get(key);

    if (!payload) {
        return;
    }

    const form = new FormData();
    form.append('body', payload.body);

    if (payload.replyToId) {
        form.append('reply_to_id', String(payload.replyToId));
    }

    payload.files.forEach((file) => {
        form.append('attachments[]', file);
    });

    const applyResult = (data: MessageStoreResponse) => {
        const confirmed = Array.isArray(data.messages)
            ? data.messages
            : data.message && typeof data.message === 'object'
              ? [data.message]
              : [];

        if (confirmed.length === 0) {
            markStatus(payload.conversationId, payload.clientIds, 'failed');
            notifyError(
                firstValidationError(data.errors) ??
                    (typeof data.message === 'string' ? data.message : 'Could not send the message.'),
            );
            return false;
        }

        const replacements = new Map(
            payload.clientIds.map((clientId, index) => {
                const server = confirmed[index] ?? confirmed[0];
                return [clientId, { ...toThreadMessage(server), client_id: clientId }];
            }),
        );

        extrasFor(payload.conversationId)
            .filter((item) => replacements.has(item.client_id))
            .forEach((item) => revokeIfBlob(item.url));

        pendingPayloads.delete(key);
        writeExtras(
            payload.conversationId,
            extrasFor(payload.conversationId).filter((item) => !replacements.has(item.client_id)),
        );

        if (thread.value?.id === payload.conversationId) {
            items.value = items.value.flatMap((item) => {
                const next = replacements.get(item.client_id);

                if (!next) {
                    return [item];
                }

                revokeIfBlob(item.url);
                return [next];
            });
        }

        if (data.conversation) {
            if (thread.value?.id === payload.conversationId) {
                thread.value = data.conversation;
            }

            upsertInbox(data.conversation);
        }

        return true;
    };

    try {
        if (payload.files.length > 0) {
            const { ok, data } = await postConversationMessage(
                `/conversations/${payload.conversationId}/messages`,
                form,
                (percent) => setUploadProgress(payload.conversationId, payload.clientIds, percent),
            );

            if (!ok) {
                markStatus(payload.conversationId, payload.clientIds, 'failed');
                notifyError(
                    firstValidationError(data.errors) ??
                        (typeof data.message === 'string' ? data.message : 'Could not send the message.'),
                );
                return;
            }

            applyResult(data);
            return;
        }

        const response = await fetch(`/conversations/${payload.conversationId}/messages`, {
            method: 'POST',
            headers: csrfFormHeaders(),
            credentials: 'same-origin',
            body: form,
        });

        const data = (await response.json()) as MessageStoreResponse;

        if (!response.ok) {
            markStatus(payload.conversationId, payload.clientIds, 'failed');
            notifyError(
                firstValidationError(data.errors) ??
                    (typeof data.message === 'string' ? data.message : 'Could not send the message.'),
            );
            return;
        }

        applyResult(data);
    } catch {
        markStatus(payload.conversationId, payload.clientIds, 'failed');
        notifyError('Could not send the message.');
    }
};

const send = () => {
    if (!thread.value || !canSend.value || viewerId.value === null) {
        return;
    }

    const text = body.value.trim();
    const attachments = [...drafts.value];

    if (!text && attachments.length === 0) {
        notifyError('Write a message or attach a file.');
        return;
    }

    const conversationId = thread.value.id;
    const groupId = attachments.length > 1 ? `local-group-${++localSeq}` : null;
    const createdAt = new Date().toISOString();
    const quote = replyingTo.value;
    const optimistic: ThreadMessage[] = [];

    if (attachments.length === 0) {
        optimistic.push({
            id: `local-${++localSeq}`,
            client_id: `local-${localSeq}`,
            user_id: viewerId.value,
            group_id: null,
            kind: 'text',
            body: text,
            url: null,
            mime_type: null,
            original_name: null,
            size: null,
            created_at: createdAt,
            edited_at: null,
            deleted_at: null,
            can_edit: false,
            can_delete: false,
            reply_to: quote,
            status: 'pending',
            progress: null,
        });
    } else {
        attachments.forEach((attachment, index) => {
            optimistic.push({
                id: `local-${++localSeq}`,
                client_id: `local-${localSeq}`,
                user_id: viewerId.value,
                group_id: groupId,
                kind: attachment.kind,
                body: index === 0 && text !== '' ? text : null,
                url: attachment.previewUrl,
                mime_type: attachment.file.type,
                original_name: attachment.file.name,
                size: attachment.file.size,
                created_at: createdAt,
                edited_at: null,
                deleted_at: null,
                can_edit: false,
                can_delete: false,
                reply_to: index === 0 ? quote : null,
                status: 'pending',
                progress: 0,
            });
        });
    }

    const key = groupId ?? optimistic[0].client_id;
    pendingPayloads.set(key, {
        conversationId,
        body: text,
        files: attachments.map((item) => item.file),
        clientIds: optimistic.map((item) => item.client_id),
        replyToId: quote?.id ?? null,
    });
    writeExtras(conversationId, [...extrasFor(conversationId), ...optimistic]);
    items.value = [...items.value, ...optimistic];
    patchInboxPreview(conversationId, previewForDrafts(text, attachments));
    body.value = '';
    drafts.value = [];
    replyingTo.value = null;
    resetInputs();
    void scrollToBottom();
    void deliver(key);
};

const resend = (block: MessageBlock) => {
    if (block.status !== 'failed') {
        return;
    }

    const conversationId = thread.value?.id;
    const key = payloadKeyFor(block.messages[0]);

    if (!conversationId || !pendingPayloads.has(key)) {
        notifyError('Could not resend the message.');
        return;
    }

    markStatus(conversationId, block.messages.map((item) => item.client_id), 'pending');
    void deliver(key);
};

const askDeleteFailed = (block: MessageBlock) => {
    deleteTarget.value = block;
    deleteOpen.value = true;
};

const confirmDeleteFailed = () => {
    const block = deleteTarget.value;
    const conversationId = thread.value?.id;

    if (!block || !conversationId) {
        deleteOpen.value = false;
        return;
    }

    const key = payloadKeyFor(block.messages[0]);
    pendingPayloads.delete(key);
    removeLocalGroup(
        conversationId,
        block.messages.map((item) => item.client_id),
    );
    deleteTarget.value = null;
    deleteOpen.value = false;
    notifySuccess('Message removed.');
};

const mediaItems = (block: MessageBlock): ThreadMessage[] => {
    return block.messages.filter((item) => !item.deleted_at && (item.kind === 'image' || item.kind === 'video') && item.url);
};

const fileItems = (block: MessageBlock): ThreadMessage[] => {
    return block.messages.filter((item) => !item.deleted_at && item.kind === 'file');
};

const quoteAuthor = (quote: ReplyQuote): string => {
    if (quote.user_id === viewerId.value) {
        return 'You';
    }

    return quote.user_name || peer.value?.name || 'Chat';
};

const blockAnchor = (block: MessageBlock): string => {
    const sent = block.messages.find((item) => isPublicId(item.id));

    return sent ? `chat-msg-${sent.id}` : `chat-msg-${block.key}`;
};

const canReplyTo = (block: MessageBlock): boolean => {
    return canSend.value && block.status === 'sent' && !block.deleted && block.messages.some((item) => isPublicId(item.id));
};

const ownSentBlock = (block: MessageBlock): boolean => {
    return block.user_id === viewerId.value && block.status === 'sent' && !block.deleted && block.messages.some((item) => isPublicId(item.id));
};

const canEditOwn = (block: MessageBlock): boolean => ownSentBlock(block);

const canDeleteOwn = (block: MessageBlock): boolean => {
    if (!ownSentBlock(block) || !block.created_at) {
        return false;
    }

    const created = Date.parse(block.created_at);

    if (!Number.isFinite(created)) {
        return false;
    }

    return nowMs.value - created < DELETE_WINDOW_MS;
};

const hasMessageMenu = (block: MessageBlock): boolean => {
    return canReplyTo(block)
        || (canEditOwn(block) && editingKey.value !== block.key)
        || canDeleteOwn(block);
};

const applyServerMessages = (incoming: ChatMessage[]) => {
    if (incoming.length === 0) {
        return;
    }

    const byId = new Map(incoming.map((item) => [item.id, item]));

    items.value = items.value.map((item) => {
        const next = byId.get(item.id);

        if (!next) {
            return item;
        }

        return { ...toThreadMessage(next), client_id: item.client_id };
    });
};

const cloneThread = (): ThreadMessage[] => {
    return items.value.map((item) => ({
        ...item,
        reply_to: item.reply_to ? { ...item.reply_to } : null,
    }));
};

const sentIds = (block: MessageBlock): string[] => {
    return block.messages.map((item) => item.id).filter((id) => isPublicId(id));
};

const targetMessageId = (block: MessageBlock): string | null => {
    const sent = block.messages.find((item) => isPublicId(item.id));

    return sent ? sent.id : null;
};

const startEdit = (block: MessageBlock) => {
    if (!canEditOwn(block)) {
        return;
    }

    editingKey.value = block.key;
    editDraft.value = block.body ?? '';
};

const cancelEdit = () => {
    editingKey.value = null;
    editDraft.value = '';
};

const persistMessageChange = async (
    conversationId: string,
    messageId: string,
    method: 'PATCH' | 'DELETE',
    snapshot: ThreadMessage[],
    body?: string,
) => {
    try {
        const response = await fetch(`/conversations/${conversationId}/messages/${messageId}`, {
            method,
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: method === 'PATCH' ? JSON.stringify({ body }) : undefined,
        });

        const data = (await response.json()) as {
            messages?: ChatMessage[];
            conversation?: InboxConversation;
            message?: string;
            errors?: Record<string, string | string[]>;
        };

        if (!response.ok) {
            items.value = snapshot;
            notifyError(
                firstValidationError(data.errors)
                    ?? data.message
                    ?? (method === 'DELETE' ? 'Could not delete the message.' : 'Could not edit the message.'),
            );
            return;
        }

        applyServerMessages(data.messages ?? []);

        if (data.conversation) {
            thread.value = data.conversation;
            upsertInbox(data.conversation);
        }
    } catch {
        items.value = snapshot;
        notifyError(method === 'DELETE' ? 'Could not delete the message.' : 'Could not edit the message.');
    }
};

const saveEdit = (block: MessageBlock) => {
    const conversationId = thread.value?.id;
    const messageId = targetMessageId(block);
    const nextBody = editDraft.value.trim();

    if (!conversationId || messageId === null) {
        return;
    }

    if (block.messages.every((item) => item.kind === 'text') && nextBody === '') {
        notifyError('Write a message.');
        return;
    }

    if (nextBody === (block.body ?? '')) {
        cancelEdit();
        return;
    }

    const snapshot = cloneThread();
    const ids = new Set(sentIds(block));
    const captionId = messageId;
    const editedAt = new Date().toISOString();

    items.value = items.value.map((item) => {
        if (ids.has(item.id)) {
            return {
                ...item,
                body: item.id === captionId ? (nextBody !== '' ? nextBody : null) : item.body,
                edited_at: editedAt,
            };
        }

        if (item.reply_to && ids.has(item.reply_to.id)) {
            return {
                ...item,
                reply_to: {
                    ...item.reply_to,
                    body: nextBody !== '' ? nextBody : item.reply_to.body,
                },
            };
        }

        return item;
    });

    cancelEdit();
    void persistMessageChange(conversationId, messageId, 'PATCH', snapshot, nextBody);
};

const askDeleteSent = (block: MessageBlock) => {
    deleteTarget.value = block;
    deleteOpen.value = true;
};

const confirmDeleteSent = () => {
    const block = deleteTarget.value;
    const conversationId = thread.value?.id;
    const messageId = block ? targetMessageId(block) : null;

    if (!block || !conversationId || messageId === null) {
        deleteOpen.value = false;
        return;
    }

    const snapshot = cloneThread();
    const ids = new Set(sentIds(block));
    const deletedAt = new Date().toISOString();

    items.value = items.value.map((item) => {
        if (ids.has(item.id)) {
            return {
                ...item,
                body: null,
                url: null,
                original_name: null,
                mime_type: null,
                size: null,
                edited_at: null,
                deleted_at: deletedAt,
                can_edit: false,
                can_delete: false,
            };
        }

        if (item.reply_to && ids.has(item.reply_to.id)) {
            return {
                ...item,
                reply_to: {
                    ...item.reply_to,
                    body: null,
                    url: null,
                    original_name: null,
                    deleted: true,
                },
            };
        }

        return item;
    });

    if (editingKey.value === block.key) {
        cancelEdit();
    }

    deleteTarget.value = null;
    deleteOpen.value = false;
    void persistMessageChange(conversationId, messageId, 'DELETE', snapshot);
};

const confirmDelete = () => {
    if (deleteTarget.value?.status === 'failed') {
        confirmDeleteFailed();
        return;
    }

    void confirmDeleteSent();
};

const deleteDialogDescription = computed(() => {
    if (deleteTarget.value?.status === 'failed') {
        return 'This message was not sent. It will be removed from this chat only.';
    }

    return 'This message will be removed for everyone in this chat.';
});

const startReply = (block: MessageBlock) => {
    if (!canReplyTo(block)) {
        return;
    }

    const target = block.messages.find((item) => isPublicId(item.id)) ?? block.messages[0];
    const media = mediaItems(block)[0];

    replyingTo.value = {
        id: target.id,
        user_id: block.user_id,
        user_name: target.user_id === viewerId.value ? 'You' : peer.value?.name || null,
        kind: media?.kind ?? target.kind,
        body: block.body,
        url: media?.url ?? ((target.kind === 'image' || target.kind === 'video') ? target.url : null),
        original_name: target.original_name,
    };

    void nextTick(() => composerInput.value?.focus());
};

const scrollToQuote = async (quote: ReplyQuote) => {
    const block = blocks.value.find((item) => item.messages.some((message) => message.id === quote.id));
    const anchor = block ? blockAnchor(block) : `chat-msg-${quote.id}`;
    const element = document.getElementById(anchor);

    if (!element) {
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightedKey.value = block?.key ?? String(quote.id);
    window.setTimeout(() => {
        if (highlightedKey.value === (block?.key ?? String(quote.id))) {
            highlightedKey.value = null;
        }
    }, 1600);
};

const respond = async (action: 'accept' | 'reject') => {
    if (!thread.value || responding.value) {
        return;
    }

    responding.value = true;

    try {
        const response = await fetch(`/conversations/${thread.value.id}/${action}`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: '{}',
        });

        const data = (await response.json()) as { message?: string; conversation?: InboxConversation };

        if (!response.ok) {
            notifyError(data.message ?? 'Could not update that message request.');
            return;
        }

        if (data.conversation) {
            thread.value = data.conversation;
            upsertInbox(data.conversation);
        }

        notifySuccess(data.message ?? (action === 'accept' ? 'Message request accepted.' : 'Message request declined.'));
    } catch {
        notifyError('Could not update that message request.');
    } finally {
        responding.value = false;
    }
};

const poll = async () => {
    nowMs.value = Date.now();

    if (document.visibilityState !== 'visible' || !thread.value) {
        return;
    }

    const conversationId = thread.value.id;
    const after = lastServerPublicId();
    const params = new URLSearchParams({
        synced_at: syncedAt.value,
    });

    if (after) {
        params.set('after', after);
    }
    const response = await fetch(`/conversations/${conversationId}/messages?${params.toString()}`, {
        headers: csrfHeaders(),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return;
    }

    const data = (await response.json()) as {
        messages: ChatMessage[];
        updates?: ChatMessage[];
        synced_at?: string;
        conversation: InboxConversation;
        unread_count?: number;
    };

    if (data.synced_at) {
        syncedAt.value = data.synced_at;
    }

    if (data.updates && data.updates.length > 0) {
        applyServerMessages(data.updates);
    }

    if (data.messages.length > 0) {
        const seen = new Set(items.value.map((item) => item.id));
        const fresh = data.messages.filter((item) => !seen.has(item.id));

        if (fresh.length > 0) {
            const confirmedIds = new Set<string>();

            items.value = items.value.flatMap((item) => {
                const match = fresh.find((incoming) => matchesPending(item, incoming));

                if (match) {
                    confirmedIds.add(item.client_id);
                    pendingPayloads.delete(payloadKeyFor(item));
                    revokeIfBlob(item.url);
                    return [toThreadMessage(match)];
                }

                return [item];
            });

            const leftover = fresh.filter((incoming) => !items.value.some((item) => item.id === incoming.id));
            items.value = [...items.value, ...leftover.map(toThreadMessage)];

            if (confirmedIds.size > 0) {
                writeExtras(
                    conversationId,
                    extrasFor(conversationId).filter((item) => !confirmedIds.has(item.client_id)),
                );
            }

            await scrollToBottom();
        }
    }

    if (thread.value?.id === conversationId) {
        thread.value = data.conversation;
    }

    upsertInbox(data.conversation);
};

onMounted(() => {
    void scrollToBottom();
    pollTimer = setInterval(() => {
        void poll();
    }, POLL_MS);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }

    Object.values(extras.value)
        .flat()
        .forEach((item) => revokeIfBlob(item.url));
    drafts.value.forEach((item) => revokeIfBlob(item.previewUrl));
});
</script>

<template>
    <Head title="Messages" />

    <div class="glass-panel flex h-full min-h-0 overflow-hidden rounded-2xl">
        <aside class="flex w-full max-w-[20rem] shrink-0 flex-col border-r border-border/70">
            <div class="border-b border-border/70 px-4 py-3">
                <h1 class="text-lg font-semibold">Messages</h1>
            </div>
            <div class="scrollbar-thin-app min-h-0 flex-1 overflow-y-auto">
                <p
                    v-if="inbox.length === 0"
                    class="text-muted-foreground px-4 py-10 text-center text-sm"
                >
                    No chats yet. Message someone from their profile.
                </p>
                <Link
                    v-for="item in inbox"
                    :key="item.id"
                    :href="`/messages/${item.id}`"
                    class="hover:bg-accent/70 flex cursor-pointer items-start gap-3 px-3 py-3"
                    :class="cn(thread?.id === item.id && 'bg-primary/10')"
                >
                    <Avatar class="size-11">
                        <AvatarImage
                            v-if="item.peer?.avatar"
                            :src="item.peer.avatar"
                            :alt="item.peer.name"
                        />
                        <AvatarFallback class="bg-primary/20 text-primary text-xs">
                            {{ getInitials(item.peer?.name ?? 'Chat') }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-semibold">
                                {{ item.peer?.name ?? 'Unknown' }}
                            </p>
                            <span class="text-muted-foreground shrink-0 text-[11px]">
                                {{ formatRelativeTime(item.last_message_at) }}
                            </span>
                        </div>
                        <p
                            class="mt-0.5 truncate text-[13px]"
                            :class="item.unread ? 'text-foreground font-medium' : 'text-muted-foreground'"
                        >
                            {{ item.last_message_preview || 'No messages yet' }}
                        </p>
                        <p
                            v-if="item.status === 'pending'"
                            class="text-primary mt-0.5 text-[11px] font-medium"
                        >
                            {{ item.is_initiator ? 'Request sent' : 'Message request' }}
                        </p>
                    </div>
                    <span
                        v-if="item.unread"
                        class="bg-primary mt-2 size-2.5 shrink-0 rounded-full"
                    />
                </Link>
            </div>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col">
            <div
                v-if="thread && peer"
                class="flex items-center gap-3 border-b border-border/70 px-4 py-3"
            >
                <Avatar class="size-10">
                    <AvatarImage
                        v-if="peer.avatar"
                        :src="peer.avatar"
                        :alt="peer.name"
                    />
                    <AvatarFallback class="bg-primary/20 text-primary text-xs">
                        {{ getInitials(peer.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0">
                    <ProfileHoverCard :user="peer" class="text-[15px]" />
                    <p class="text-muted-foreground truncate text-xs">
                        @{{ peer.username }}
                    </p>
                </div>
            </div>

            <div
                v-if="thread?.can_respond"
                class="bg-muted/50 flex items-center justify-between gap-3 border-b border-border/70 px-4 py-3"
            >
                <p class="text-sm">
                    {{ peer?.name }} wants to message you.
                </p>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        class="rounded-full"
                        :loading="responding"
                        @click="respond('accept')"
                    >
                        Accept
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="rounded-full"
                        :disabled="responding"
                        @click="respond('reject')"
                    >
                        Decline
                    </Button>
                </div>
            </div>

            <div
                v-if="thread"
                ref="scroller"
                class="scrollbar-thin-app flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-4 py-4"
            >
                <p
                    v-if="items.length === 0"
                    class="text-muted-foreground my-auto text-center text-sm"
                >
                    {{ thread.status === 'pending' && thread.is_initiator
                        ? 'Send a message to start this request.'
                        : 'No messages yet.' }}
                </p>
                <div
                    v-for="block in blocks"
                    :id="blockAnchor(block)"
                    :key="block.key"
                    class="group flex items-center gap-1.5"
                    :class="block.user_id === viewerId ? 'justify-end' : 'justify-start'"
                >
                    <DropdownMenu v-if="hasMessageMenu(block) && block.user_id === viewerId">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground inline-flex cursor-pointer rounded-full border-0 p-1 opacity-100 outline-none transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                                aria-label="Message actions"
                            >
                                <Ellipsis class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-40">
                            <DropdownMenuItem
                                v-if="canReplyTo(block)"
                                class="cursor-pointer"
                                @select="startReply(block)"
                            >
                                <Reply />
                                Reply
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="canEditOwn(block) && editingKey !== block.key"
                                class="cursor-pointer"
                                @select="startEdit(block)"
                            >
                                <Pencil />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="canDeleteOwn(block)"
                                variant="destructive"
                                class="cursor-pointer"
                                @select="askDeleteSent(block)"
                            >
                                <Trash2 />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <DropdownMenu v-if="block.status === 'failed'">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="text-destructive flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-full border-0 outline-none"
                                aria-label="Message not sent"
                            >
                                <CircleAlert class="size-5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-36">
                            <DropdownMenuItem @select="resend(block)">
                                Resend
                            </DropdownMenuItem>
                            <DropdownMenuItem variant="destructive" @select="askDeleteFailed(block)">
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <div
                        class="max-w-[75%] rounded-2xl border-0 px-2 py-2 text-sm shadow-none outline-none transition"
                        :class="
                            cn(
                                block.user_id === viewerId
                                    ? 'bg-primary text-primary-foreground rounded-br-md'
                                    : 'bg-muted rounded-bl-md',
                                highlightedKey === block.key && 'ring-2 ring-primary ring-offset-0',
                            )
                        "
                    >
                        <p
                            v-if="block.deleted"
                            class="px-1 italic opacity-80"
                        >
                            This message was deleted
                        </p>
                        <template v-else>
                            <ChatReplyQuote
                                v-if="block.reply_to"
                                :quote="block.reply_to"
                                :author="quoteAuthor(block.reply_to)"
                                :own="block.user_id === viewerId"
                                @select="scrollToQuote(block.reply_to)"
                            />
                            <ChatMessageAlbum
                                v-if="mediaItems(block).length > 0"
                                :items="mediaItems(block)"
                                class="mb-1"
                                @open="openMedia"
                            />
                            <div
                                v-for="item in fileItems(block)"
                                :key="item.client_id"
                                class="relative mb-1 overflow-hidden rounded-lg"
                                :class="item.status === 'pending' && 'min-h-20'"
                            >
                                <a
                                    v-if="item.url && item.status !== 'pending'"
                                    :href="item.url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="flex cursor-pointer items-center gap-2 px-1 underline-offset-2 hover:underline"
                                >
                                    <FileText class="size-4 shrink-0" />
                                    <span class="min-w-0 truncate">
                                        {{ item.original_name ?? 'File' }}
                                    </span>
                                    <span class="opacity-80 text-xs">
                                        {{ formatBytes(item.size) }}
                                    </span>
                                </a>
                                <span
                                    v-else
                                    class="flex items-center gap-2 px-1"
                                >
                                    <FileText class="size-4 shrink-0" />
                                    <span class="min-w-0 truncate">
                                        {{ item.original_name ?? 'File' }}
                                    </span>
                                    <span class="opacity-80 text-xs">
                                        {{ formatBytes(item.size) }}
                                    </span>
                                </span>
                                <UploadProgressRing
                                    v-if="item.status === 'pending'"
                                    :progress="item.progress ?? 0"
                                />
                            </div>
                            <div
                                v-if="editingKey === block.key"
                                class="px-1"
                            >
                                <textarea
                                    v-model="editDraft"
                                    rows="2"
                                    maxlength="4000"
                                    class="border-primary-foreground/30 bg-black/10 max-h-32 min-h-16 w-full resize-none rounded-xl border px-2 py-1.5 text-sm outline-none"
                                    @keydown.enter.exact.prevent="saveEdit(block)"
                                    @keydown.esc.prevent="cancelEdit"
                                />
                                <div class="mt-1 flex justify-end gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 cursor-pointer px-2 text-xs"
                                        @click="cancelEdit"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        @click="saveEdit(block)"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </div>
                            <p
                                v-else-if="block.body"
                                class="whitespace-pre-wrap break-words px-1"
                            >
                                {{ block.body }}
                            </p>
                        </template>
                        <p
                            class="mt-1 px-1 text-[10px] opacity-70"
                            :class="block.user_id === viewerId ? 'text-right' : 'text-left'"
                        >
                            {{ formatClock(block.created_at) }}
                            <span v-if="block.edited_at && !block.deleted"> · Edited</span>
                        </p>
                    </div>
                    <DropdownMenu v-if="hasMessageMenu(block) && block.user_id !== viewerId">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground inline-flex cursor-pointer rounded-full border-0 p-1 opacity-100 outline-none transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                                aria-label="Message actions"
                            >
                                <Ellipsis class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-40">
                            <DropdownMenuItem
                                v-if="canReplyTo(block)"
                                class="cursor-pointer"
                                @select="startReply(block)"
                            >
                                <Reply />
                                Reply
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div
                v-else
                class="text-muted-foreground flex flex-1 items-center justify-center px-6 text-center text-sm"
            >
                Choose a conversation or message someone from their profile card.
            </div>

            <form
                v-if="thread"
                class="border-t border-border/70 p-3"
                @submit.prevent="send"
            >
                <p
                    v-if="composerHint"
                    class="text-muted-foreground mb-2 text-xs"
                >
                    {{ composerHint }}
                </p>
                <div
                    v-if="replyingTo"
                    class="mb-2 flex items-stretch gap-1"
                >
                    <ChatReplyQuote
                        class="mb-0 flex-1"
                        :quote="replyingTo"
                        :author="quoteAuthor(replyingTo)"
                        @select="scrollToQuote(replyingTo)"
                    />
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full"
                        aria-label="Cancel reply"
                        @click="replyingTo = null"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div
                    v-if="drafts.length > 0"
                    class="mb-2 flex gap-2 overflow-x-auto pb-1"
                >
                    <div
                        v-for="draft in drafts"
                        :key="draft.id"
                        class="relative size-16 shrink-0"
                    >
                        <button
                            v-if="draft.kind === 'image' || draft.kind === 'video'"
                            type="button"
                            class="size-16 cursor-pointer overflow-hidden rounded-xl bg-muted"
                            :aria-label="draft.kind === 'video' ? 'Play video' : 'View photo'"
                            @click="openDraft(draft)"
                        >
                            <img
                                v-if="draft.kind === 'image' && draft.previewUrl"
                                :src="draft.previewUrl"
                                :alt="draft.file.name"
                                class="size-full object-cover"
                            />
                            <video
                                v-else-if="draft.previewUrl"
                                :src="draft.previewUrl"
                                muted
                                preload="metadata"
                                class="size-full object-cover"
                            />
                            <span
                                v-if="draft.kind === 'video'"
                                class="absolute inset-0 flex items-center justify-center bg-black/30"
                            >
                                <Play class="size-5 fill-white text-white" />
                            </span>
                        </button>
                        <div
                            v-else
                            class="flex size-16 items-center justify-center rounded-xl bg-muted px-1 text-center"
                            :title="draft.file.name"
                        >
                            <FileText class="size-5" />
                        </div>
                        <button
                            type="button"
                            class="bg-background text-foreground absolute -top-1.5 -right-1.5 flex size-5 cursor-pointer items-center justify-center rounded-full border shadow-sm"
                            aria-label="Remove file"
                            @click="removeDraft(draft.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <input
                        ref="photoInput"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="PHOTO_ACCEPT"
                        @change="onFileChange('photo', $event)"
                    />
                    <input
                        ref="videoInput"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="VIDEO_ACCEPT"
                        @change="onFileChange('video', $event)"
                    />
                    <input
                        ref="documentInput"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="DOCUMENT_ACCEPT"
                        @change="onFileChange('document', $event)"
                    />
                    <DropdownMenu v-model:open="attachOpen">
                        <DropdownMenuTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="rounded-full"
                                :disabled="!canSend"
                                aria-label="Add an attachment"
                            >
                                <Plus
                                    class="size-5 transition-transform"
                                    :class="attachOpen ? 'rotate-45' : ''"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            side="top"
                            align="start"
                            :side-offset="8"
                            class="flex min-w-0 flex-row gap-1 p-1"
                        >
                            <DropdownMenuItem
                                class="size-10 justify-center rounded-full p-0"
                                aria-label="Add a photo"
                                title="Photo"
                                @select="pickAttachment('photo')"
                            >
                                <ImageIcon class="size-5" />
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="size-10 justify-center rounded-full p-0"
                                aria-label="Add a video"
                                title="Video"
                                @select="pickAttachment('video')"
                            >
                                <Video class="size-5" />
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="size-10 justify-center rounded-full p-0"
                                aria-label="Add a document"
                                title="File"
                                @select="pickAttachment('document')"
                            >
                                <FileIcon class="size-5" />
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <textarea
                        ref="composerInput"
                        v-model="body"
                        rows="1"
                        class="bg-background/70 border-border focus:border-primary/50 focus:ring-primary/20 max-h-32 min-h-10 flex-1 resize-none rounded-2xl border px-3 py-2 text-sm outline-none focus:ring-2"
                        placeholder="Type a message"
                        :disabled="!canSend"
                        @keydown.enter.exact.prevent="send"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        class="rounded-full"
                        :disabled="!canSend"
                        aria-label="Send"
                    >
                        <Send class="size-4" />
                    </Button>
                </div>
            </form>
        </section>
    </div>

    <ConfirmDeleteDialog
        v-model:open="deleteOpen"
        title="Delete this message?"
        :description="deleteDialogDescription"
        confirm-label="Delete"
        @confirm="confirmDelete"
    />

    <ChatMediaGallery
        v-model:open="galleryOpen"
        v-model:index="galleryIndex"
        :items="galleryItems"
    />
</template>

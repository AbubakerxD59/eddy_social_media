export type PublicUser = {
    id: number;
    name: string;
    username: string;
    headline?: string | null;
    bio?: string | null;
    website?: string | null;
    avatar?: string | null;
    created_at?: string | null;
};

export type SignalType = 'quote' | 'images' | 'video' | 'link';

export type SignalMedia = {
    id: number;
    kind: 'image' | 'video';
    url: string;
    mime_type: string | null;
};

export type SignalLink = {
    url: string;
    title: string | null;
    description: string | null;
    image: string | null;
};

export type FeedSignal = {
    id: string;
    type: SignalType;
    body: string | null;
    link: SignalLink | null;
    media: SignalMedia[];
    author: PublicUser;
    created_at: string | null;
    can_delete: boolean;
    can_mute: boolean;
    can_report: boolean;
    saved: boolean;
    reported: boolean;
    author_muted: boolean;
    liked: boolean;
    likes_count: number;
    replies_count: number;
};

export type Paginator<T> = {
    data: T[];
    next_page_url: string | null;
    prev_page_url: string | null;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

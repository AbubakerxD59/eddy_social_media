import type { PublicUser } from '@/types/social';

export type RailNeed = {
    id: string;
    title: string;
    budget: string | null;
    location: string | null;
};

export type RailMatch = PublicUser & {
    match: number;
};

export type StoryItem = {
    id: string;
    kind: 'image' | 'video';
    url: string;
    caption: string | null;
    created_at: string | null;
    expires_at: string;
    can_delete: boolean;
};

export type StoryGroup = {
    user: PublicUser;
    items: StoryItem[];
};

export type UniverseRail = {
    level: number;
    title: string;
    xp: number;
    xp_max: number;
    matches: RailMatch[];
    needs: RailNeed[];
};

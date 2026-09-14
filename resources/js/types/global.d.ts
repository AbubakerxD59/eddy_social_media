import type { Auth } from '@/types/auth';
import type { StoryGroup, UniverseRail } from '@/types/dashboard';
import type { ChatInboxPayload } from '@/types/messages';
import type { NotificationPayload } from '@/types/notifications';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            stories?: StoryGroup[];
            rail?: UniverseRail | null;
            viewerLatitude?: number | null;
            viewerLongitude?: number | null;
            viewerLocation?: string | null;
            viewerLocationManual?: boolean;
            notifications?: NotificationPayload;
            chats?: ChatInboxPayload;
            messages_unread_count?: number;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

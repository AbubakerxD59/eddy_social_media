import { router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { csrfHeaders } from '@/lib/csrf';

const RELOAD_THRESHOLD = 0.01;
const STORE_THRESHOLD = 0.0005;

const GEO_OPTIONS: PositionOptions = {
    enableHighAccuracy: false,
    timeout: 15000,
    maximumAge: 300_000,
};

let lastPosted: StoredViewerOrigin | null = null;
let persistChain: Promise<unknown> = Promise.resolve();
let suppressLiveUpdates = false;

export type PersistLocationOptions = {
    label?: string | null;
    manual?: boolean;
};

export type StoredViewerOrigin = {
    latitude: number;
    longitude: number;
    label?: string | null;
    manual?: boolean;
};

const coordsClose = (
    latitude: number,
    longitude: number,
    otherLatitude: number,
    otherLongitude: number,
    threshold: number,
) => Math.abs(latitude - otherLatitude) < threshold && Math.abs(longitude - otherLongitude) < threshold;

export function setLiveLocationSuppressed(value: boolean) {
    suppressLiveUpdates = value;
}

export function requestBrowserLocation(): Promise<{ latitude: number; longitude: number } | null> {
    return new Promise((resolve) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            resolve(null);

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                });
            },
            () => resolve(null),
            GEO_OPTIONS,
        );
    });
}

const parseStoredOrigin = (value: unknown): StoredViewerOrigin | null => {
    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const record = value as Record<string, unknown>;
    const latitude = Number(record.latitude);
    const longitude = Number(record.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    return {
        latitude,
        longitude,
        label: typeof record.label === 'string' ? record.label : null,
        manual: record.manual === true,
    };
};

const postViewerOrigin = async (
    latitude: number,
    longitude: number,
    options: PersistLocationOptions,
): Promise<StoredViewerOrigin | null> => {
    const fallback: StoredViewerOrigin = {
        latitude,
        longitude,
        label: options.label ?? lastPosted?.label ?? 'Current location',
        manual: options.manual ?? lastPosted?.manual ?? false,
    };

    try {
        const response = await fetch('/location', {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            body: JSON.stringify({
                latitude,
                longitude,
                ...(options.label !== undefined ? { label: options.label } : {}),
                ...(options.manual !== undefined ? { manual: options.manual } : {}),
            }),
        });

        let parsed: StoredViewerOrigin | null = null;

        try {
            parsed = parseStoredOrigin(await response.json());
        } catch {
            parsed = null;
        }

        if (!response.ok) {
            return null;
        }

        lastPosted = parsed ?? fallback;

        return lastPosted;
    } catch {
        return null;
    }
};

export async function persistLiveLocation(
    latitude: number,
    longitude: number,
    options: PersistLocationOptions = {},
): Promise<StoredViewerOrigin | null> {
    const force = options.label !== undefined || options.manual !== undefined;

    const run = async () => {
        if (
            !force
            && lastPosted
            && coordsClose(lastPosted.latitude, lastPosted.longitude, latitude, longitude, STORE_THRESHOLD)
        ) {
            return lastPosted;
        }

        return postViewerOrigin(latitude, longitude, options);
    };

    const result = persistChain.then(run, run);
    persistChain = result.then(() => undefined, () => undefined);

    return result;
}

export function reloadViewerLocation() {
    const page = usePage();
    const only: string[] = ['viewerLatitude', 'viewerLongitude', 'viewerLocation', 'viewerLocationManual', 'rail'];
    const reset: string[] = ['rail'];

    if (page.component === 'Feed') {
        only.push('signals');
        reset.push('signals');
        router.remember(undefined, 'inertia:infinite-scroll-data:signals');
    }

    router.reload({
        only,
        reset,
        replace: true,
    });
}

export function useLiveLocation() {
    const page = usePage();

    const refreshViewerOrigin = (latitude: number, longitude: number) => {
        const currentLatitude = page.props.viewerLatitude;
        const currentLongitude = page.props.viewerLongitude;
        const known = typeof currentLatitude === 'number' && typeof currentLongitude === 'number';

        if (known && coordsClose(currentLatitude, currentLongitude, latitude, longitude, RELOAD_THRESHOLD)) {
            return;
        }

        reloadViewerLocation();
    };

    const apply = async (latitude: number, longitude: number) => {
        if (suppressLiveUpdates || page.props.viewerLocationManual) {
            return;
        }

        const saved = await persistLiveLocation(latitude, longitude);

        if (saved === null) {
            return;
        }

        refreshViewerOrigin(saved.latitude, saved.longitude);
    };

    onMounted(() => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            return;
        }

        if (page.props.viewerLocationManual || typeof page.props.viewerLatitude === 'number') {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                void apply(position.coords.latitude, position.coords.longitude);
            },
            () => {
                // Nearby ranking stays off if the viewer declines location or GPS is unavailable.
            },
            GEO_OPTIONS,
        );
    });
}

<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { persistLiveLocation, requestBrowserLocation } from '@/composables/useLiveLocation';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError, notifySuccess } from '@/lib/notify';

type PlaceSuggestion = {
    id: string;
    label: string;
    description: string | null;
    remote?: boolean;
    current?: boolean;
};

const location = defineModel<string>('modelValue', { default: '' });
const latitude = defineModel<number | null>('latitude', { default: null });
const longitude = defineModel<number | null>('longitude', { default: null });
const placeId = defineModel<string>('placeId', { default: '' });

const {
    placeholder = 'Location',
    originLatitude = null,
    originLongitude = null,
    hideRemote = false,
    quiet = false,
    syncViewer = true,
    autofocus = false,
    biasOrigin = true,
    scope = 'places',
} = defineProps<{
    placeholder?: string;
    originLatitude?: number | null;
    originLongitude?: number | null;
    hideRemote?: boolean;
    quiet?: boolean;
    syncViewer?: boolean;
    autofocus?: boolean;
    biasOrigin?: boolean;
    scope?: 'places' | 'regions';
}>();

const emit = defineEmits<{
    picked: [payload: {
        label: string;
        latitude: number | null;
        longitude: number | null;
        remote: boolean;
        current: boolean;
    }];
}>();

const newSessionToken = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return '00000000-0000-4000-8000-000000000000';
};

const root = ref<HTMLElement | null>(null);
const open = ref(false);
const loading = ref(false);
const resolving = ref(false);
const activeIndex = ref(0);
const suggestions = ref<PlaceSuggestion[]>([]);
const sessionToken = ref(newSessionToken());
const selecting = ref(false);
let abort: AbortController | null = null;

const remoteSuggestion: PlaceSuggestion = {
    id: 'remote',
    label: 'Remote',
    description: 'No specific location',
    remote: true,
};

const currentLocationSuggestion = computed<PlaceSuggestion>(() => ({
    id: 'current',
    label: 'Use current location',
    description: hideRemote ? 'Rank nearby posts from here' : 'Share your live location',
    current: true,
}));

const visibleSuggestions = computed(() => {
    const query = location.value.trim().toLowerCase();
    const items = [...suggestions.value];

    if (
        !hideRemote
        && (query === '' || 'remote'.startsWith(query) || query.includes('remote'))
    ) {
        if (!items.some((item) => item.remote)) {
            items.unshift(remoteSuggestion);
        }
    }

    if (hideRemote) {
        items.unshift(currentLocationSuggestion.value);
    } else if (
        query === ''
        || 'use current location'.startsWith(query)
        || 'current location'.startsWith(query)
        || query === 'live'
        || query === 'gps'
    ) {
        if (!items.some((item) => item.current)) {
            items.unshift(currentLocationSuggestion.value);
        }
    }

    return items;
});

onClickOutside(root, () => {
    open.value = false;
});

const search = useDebounceFn(async (query: string) => {
    abort?.abort();

    if (query.trim().length < 2) {
        suggestions.value = [];
        loading.value = false;
        return;
    }

    abort = new AbortController();
    loading.value = true;

    try {
        const response = await fetch('/places/autocomplete', {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            signal: abort.signal,
            body: JSON.stringify({
                input: query,
                session_token: sessionToken.value,
                scope,
                ...(biasOrigin && originLatitude != null && originLongitude != null
                    ? { latitude: originLatitude, longitude: originLongitude }
                    : {}),
            }),
        });

        if (!response.ok) {
            suggestions.value = [];
            return;
        }

        const data = (await response.json()) as {
            suggestions?: { place_id: string; label: string; description: string | null }[];
        };

        suggestions.value = (data.suggestions ?? []).map((item) => ({
            id: item.place_id,
            label: item.label,
            description: item.description,
        }));
        activeIndex.value = 0;
        open.value = true;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        suggestions.value = [];
    } finally {
        loading.value = false;
    }
}, 280);

watch(location, (value) => {
    if (selecting.value) {
        return;
    }

    latitude.value = null;
    longitude.value = null;
    placeId.value = '';
    void search(value);
});

const finishSelecting = () => {
    void Promise.resolve().then(() => {
        selecting.value = false;
    });
};

const chooseRemote = () => {
    selecting.value = true;
    location.value = 'Remote';
    latitude.value = null;
    longitude.value = null;
    placeId.value = '';
    suggestions.value = [];
    open.value = false;
    sessionToken.value = newSessionToken();
    if (!quiet) {
        notifySuccess('Location set to Remote.');
    }
    emit('picked', {
        label: 'Remote',
        latitude: null,
        longitude: null,
        remote: true,
        current: false,
    });
    finishSelecting();
};

const chooseCurrentLocation = async () => {
    resolving.value = true;

    try {
        const coords = await requestBrowserLocation();

        if (coords === null) {
            notifyError('Could not read your location. Check browser permissions and try again.');
            return;
        }

        selecting.value = true;
        location.value = 'Current location';
        latitude.value = coords.latitude;
        longitude.value = coords.longitude;
        placeId.value = '';
        suggestions.value = [];
        open.value = false;
        sessionToken.value = newSessionToken();
        if (!quiet) {
            notifySuccess('Using your current location.');
        }
        emit('picked', {
            label: 'Current location',
            latitude: coords.latitude,
            longitude: coords.longitude,
            remote: false,
            current: true,
        });
        finishSelecting();
        if (syncViewer) {
            void persistLiveLocation(coords.latitude, coords.longitude);
        }
    } finally {
        resolving.value = false;
    }
};

const choosePlace = async (suggestion: PlaceSuggestion) => {
    if (suggestion.remote) {
        chooseRemote();
        return;
    }

    if (suggestion.current) {
        await chooseCurrentLocation();
        return;
    }

    resolving.value = true;

    try {
        const params = new URLSearchParams({ session_token: sessionToken.value });
        const response = await fetch(`/places/${encodeURIComponent(suggestion.id)}?${params}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            notifyError('Could not load that place. Please try another.');
            return;
        }

        const data = (await response.json()) as {
            place_id: string;
            label: string;
            latitude: number;
            longitude: number;
        };

        selecting.value = true;
        location.value = data.label;
        latitude.value = data.latitude;
        longitude.value = data.longitude;
        placeId.value = data.place_id;
        suggestions.value = [];
        open.value = false;
        sessionToken.value = newSessionToken();
        if (!quiet) {
            notifySuccess('Location set.');
        }
        emit('picked', {
            label: data.label,
            latitude: data.latitude,
            longitude: data.longitude,
            remote: false,
            current: false,
        });
        finishSelecting();
    } catch {
        notifyError('Could not load that place. Please try another.');
    } finally {
        resolving.value = false;
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (!open.value && ['ArrowDown', 'Enter'].includes(event.key) && visibleSuggestions.value.length > 0) {
        open.value = true;
    }

    if (!open.value || visibleSuggestions.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % visibleSuggestions.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + visibleSuggestions.value.length) % visibleSuggestions.value.length;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const suggestion = visibleSuggestions.value[activeIndex.value];

        if (suggestion) {
            void choosePlace(suggestion);
        }

        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
    }
};

onBeforeUnmount(() => {
    abort?.abort();
});
</script>

<template>
    <div ref="root" class="relative">
        <div class="relative">
            <MapPin class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
            <Input
                v-model="location"
                role="combobox"
                autocomplete="off"
                :placeholder="placeholder"
                :aria-expanded="open && visibleSuggestions.length > 0"
                aria-autocomplete="list"
                class="h-9 rounded-lg pr-9 pl-9"
                :autofocus="autofocus"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <Spinner
                v-if="loading || resolving"
                class="text-muted-foreground absolute top-1/2 right-3 -translate-y-1/2"
            />
        </div>

        <ul
            v-if="open && visibleSuggestions.length > 0"
            class="glass-popup mt-1 max-h-56 overflow-y-auto rounded-xl border p-1"
            role="listbox"
        >
            <li
                v-for="(suggestion, index) in visibleSuggestions"
                :key="suggestion.id"
                role="option"
                :aria-selected="index === activeIndex"
                class="cursor-pointer rounded-lg px-3 py-2"
                :class="index === activeIndex ? 'bg-accent' : 'hover:bg-accent/70'"
                @mousedown.prevent="choosePlace(suggestion)"
                @mouseenter="activeIndex = index"
            >
                <p class="text-sm font-medium">{{ suggestion.label }}</p>
                <p v-if="suggestion.description" class="text-muted-foreground text-xs">
                    {{ suggestion.description }}
                </p>
            </li>
        </ul>
        <p
            v-else-if="open && !loading && location.trim().length >= 2"
            class="text-muted-foreground px-1 py-2 text-xs"
        >
            No matching places.
        </p>
    </div>
</template>

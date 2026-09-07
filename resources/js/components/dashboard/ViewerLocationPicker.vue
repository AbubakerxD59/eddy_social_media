<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import LocationAutocomplete from '@/components/LocationAutocomplete.vue';
import { persistLiveLocation, reloadViewerLocation, setLiveLocationSuppressed } from '@/composables/useLiveLocation';
import { notifyError, notifySuccess } from '@/lib/notify';

type PickedLocation = {
    label: string;
    latitude: number | null;
    longitude: number | null;
    remote: boolean;
    current: boolean;
};

const page = usePage();
const root = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const open = ref(false);
const saving = ref(false);
const location = ref('');
const latitude = ref<number | null>(null);
const longitude = ref<number | null>(null);
const placeId = ref('');
const panelStyle = ref<Record<string, string>>({});

const label = computed(() => page.props.viewerLocation || 'Set location');
const originLatitude = computed(() => page.props.viewerLatitude ?? null);
const originLongitude = computed(() => page.props.viewerLongitude ?? null);

const positionPanel = () => {
    const button = root.value?.querySelector('button');

    if (!button) {
        return;
    }

    const rect = button.getBoundingClientRect();

    panelStyle.value = {
        top: `${rect.bottom + 8}px`,
        right: `${Math.max(16, window.innerWidth - rect.right)}px`,
        width: 'min(22rem, calc(100vw - 2rem))',
    };
};

watch(open, async (isOpen) => {
    setLiveLocationSuppressed(isOpen || saving.value);

    if (!isOpen) {
        return;
    }

    await nextTick();
    positionPanel();
});

onClickOutside(
    root,
    () => {
        open.value = false;
    },
    { ignore: [panel] },
);

const onResize = () => {
    if (open.value) {
        positionPanel();
    }
};

onMounted(() => {
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    setLiveLocationSuppressed(false);
});

const apply = async (picked: { label: string; latitude: number; longitude: number; manual: boolean }) => {
    if (saving.value) {
        return;
    }

    saving.value = true;
    setLiveLocationSuppressed(true);

    try {
        const saved = await persistLiveLocation(picked.latitude, picked.longitude, {
            label: picked.label,
            manual: picked.manual,
        });

        if (saved === null) {
            notifyError('Could not update your location. Please try again.');
            return;
        }

        open.value = false;
        location.value = '';
        latitude.value = null;
        longitude.value = null;
        placeId.value = '';
        notifySuccess(`Showing posts near ${saved.label || picked.label}.`);
        reloadViewerLocation();
    } finally {
        saving.value = false;
        setLiveLocationSuppressed(open.value);
    }
};

const onPicked = (picked: PickedLocation) => {
    if (picked.remote || picked.latitude === null || picked.longitude === null) {
        return;
    }

    void apply({
        label: picked.current ? 'Current location' : picked.label,
        latitude: picked.latitude,
        longitude: picked.longitude,
        manual: !picked.current,
    });
};
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="hover:bg-accent flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1.5"
            :aria-expanded="open"
            aria-haspopup="dialog"
            :aria-label="`Viewing near ${label}`"
            @click="open = !open"
        >
            <MapPin class="size-4 shrink-0" />
            <span class="hidden max-w-[10rem] truncate text-[13px] font-medium sm:inline">
                {{ label }}
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="panel"
                class="glass-popup fixed z-50 overflow-visible rounded-xl border p-2"
                :style="panelStyle"
            >
                <p class="text-muted-foreground px-1 pb-2 text-[11px] font-medium tracking-wide uppercase">
                    Viewing near
                </p>
                <LocationAutocomplete
                    v-model="location"
                    v-model:latitude="latitude"
                    v-model:longitude="longitude"
                    v-model:place-id="placeId"
                    hide-remote
                    quiet
                    :sync-viewer="false"
                    :origin-latitude="originLatitude"
                    :origin-longitude="originLongitude"
                    scope="regions"
                    placeholder="Search a city or place"
                    autofocus
                    @picked="onPicked"
                />
            </div>
        </Teleport>
    </div>
</template>

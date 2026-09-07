<script setup lang="ts">
import { ImagePlus, Play, X } from '@lucide/vue';
import DropzoneLib from 'dropzone';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { csrfToken } from '@/lib/csrf';
import { notifyError } from '@/lib/notify';

const Dropzone =
    typeof DropzoneLib === 'function'
        ? DropzoneLib
        : ((DropzoneLib as unknown as { default: typeof DropzoneLib }).default ?? DropzoneLib);

Dropzone.autoDiscover = false;

const IMAGE_MAX_BYTES = 8 * 1024 * 1024;
const VIDEO_MAX_BYTES = 50 * 1024 * 1024;
const MAX_FILES = 6;
const RING = 2 * Math.PI * 14;

type UploadKind = 'image' | 'video';
type UploadStatus = 'uploading' | 'ready' | 'error';

type UploadItem = {
    key: string;
    file: Dropzone.DropzoneFile;
    previewUrl: string;
    kind: UploadKind;
    progress: number;
    status: UploadStatus;
    serverId: string | null;
    message: string | null;
};

type UploadResponse = {
    id: string;
    kind: UploadKind;
    url: string;
    mime_type: string | null;
};

const mediaIds = defineModel<string[]>({ default: () => [] });

const { disabled = false } = defineProps<{
    disabled?: boolean;
}>();

const emit = defineEmits<{
    uploading: [value: boolean];
    added: [];
}>();

const root = ref<HTMLElement | null>(null);
const picker = ref<HTMLButtonElement | null>(null);
const items = ref<UploadItem[]>([]);
let dropzone: Dropzone | null = null;
let keySeq = 0;

const uploading = computed(() => items.value.some((item) => item.status === 'uploading'));
const canAddMore = computed(() => items.value.length < MAX_FILES);

const mediaKindOf = (file: File): UploadKind | null => {
    if (file.type.startsWith('image/')) {
        return 'image';
    }

    if (file.type.startsWith('video/')) {
        return 'video';
    }

    const name = file.name.toLowerCase();

    if (/\.(avif|bmp|gif|heic|heif|jpe?g|png|webp)$/.test(name)) {
        return 'image';
    }

    if (/\.(m4v|mkv|mov|mp4|webm)$/.test(name)) {
        return 'video';
    }

    return null;
};

const syncIds = () => {
    mediaIds.value = items.value
        .map((item) => item.serverId)
        .filter((id): id is string => Boolean(id));
};

const setItem = (file: Dropzone.DropzoneFile, patch: Partial<UploadItem>) => {
    items.value = items.value.map((item) => (item.file === file ? { ...item, ...patch } : item));
};

const errorMessage = (payload: unknown): string => {
    if (typeof payload === 'string' && payload.trim()) {
        try {
            return errorMessage(JSON.parse(payload));
        } catch {
            return payload;
        }
    }

    if (payload && typeof payload === 'object') {
        const body = payload as { message?: string; errors?: Record<string, string[] | string> };

        if (body.errors) {
            const first = Object.values(body.errors)[0];

            if (Array.isArray(first) && first[0]) {
                return first[0];
            }

            if (typeof first === 'string' && first) {
                return first;
            }
        }

        if (body.message) {
            return body.message;
        }
    }

    return 'Could not upload that file.';
};

const xsrfHeaders = (): Record<string, string> => {
    const token = csrfToken();

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
};

const open = (accept = 'image/*,video/*') => {
    if (disabled || !dropzone) {
        return;
    }

    const input = dropzone.hiddenFileInput;

    if (!input) {
        return;
    }

    input.accept = accept;
    input.click();
};

const removeItem = (item: UploadItem) => {
    dropzone?.removeFile(item.file);
};

watch(uploading, (value) => emit('uploading', value), { immediate: true });

watch(
    () => mediaIds.value,
    (ids, previous) => {
        if (ids.length === 0 && (previous?.length ?? 0) > 0) {
            dropzone?.removeAllFiles(true);
        }
    },
);

watch(
    () => disabled,
    (isDisabled) => {
        if (!dropzone) {
            return;
        }

        if (isDisabled) {
            dropzone.disable();
            return;
        }

        dropzone.enable();
    },
);

onMounted(() => {
    if (!root.value) {
        return;
    }

    dropzone = new Dropzone(root.value, {
        url: '/signals/uploads',
        paramName: 'media',
        method: 'post',
        maxFiles: MAX_FILES,
        maxFilesize: 50,
        parallelUploads: 3,
        uploadMultiple: false,
        autoQueue: true,
        autoProcessQueue: true,
        createImageThumbnails: false,
        addRemoveLinks: false,
        clickable: picker.value || true,
        previewsContainer: document.createElement('div'),
        previewTemplate: '<div></div>',
        acceptedFiles: 'image/*,video/*',
        timeout: 0,
        withCredentials: true,
        headers: xsrfHeaders(),
        dictMaxFilesExceeded: `You can attach up to ${MAX_FILES} files.`,
        accept: (file, done) => {
            const kind = mediaKindOf(file);

            if (kind === null) {
                done(`${file.name} is not an image or video.`);
                return;
            }

            if (kind === 'image' && file.size > IMAGE_MAX_BYTES) {
                done('Images must be 8 MB or smaller.');
                return;
            }

            if (kind === 'video' && file.size > VIDEO_MAX_BYTES) {
                done('Videos must be 50 MB or smaller.');
                return;
            }

            done();
        },
    });

    dropzone.on('addedfile', (file) => {
        const kind = mediaKindOf(file);

        if (kind === null) {
            dropzone?.removeFile(file);
            return;
        }

        items.value = [
            ...items.value,
            {
                key: `upload-${++keySeq}`,
                file,
                previewUrl: URL.createObjectURL(file),
                kind,
                progress: 0,
                status: 'uploading',
                serverId: null,
                message: null,
            },
        ];
        emit('added');
    });

    dropzone.on('uploadprogress', (file, progress) => {
        setItem(file, { progress: Math.max(0, Math.min(100, progress)), status: 'uploading' });
    });

    dropzone.on('success', (file, response) => {
        const payload = typeof response === 'string' ? (JSON.parse(response) as UploadResponse) : (response as UploadResponse);

        setItem(file, {
            progress: 100,
            status: 'ready',
            serverId: payload.id,
            message: null,
        });
        syncIds();
    });

    dropzone.on('error', (file, message) => {
        const text = errorMessage(message);
        setItem(file, { status: 'error', message: text });
        notifyError(text);
    });

    dropzone.on('removedfile', (file) => {
        const item = items.value.find((current) => current.file === file);

        if (item?.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }

        if (item?.serverId) {
            void fetch(`/signals/uploads/${item.serverId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: xsrfHeaders(),
            });
        }

        items.value = items.value.filter((current) => current.file !== file);
        syncIds();
    });

    dropzone.on('maxfilesexceeded', (file) => {
        dropzone?.removeFile(file);
        notifyError(`You can attach up to ${MAX_FILES} files.`);
    });

    if (disabled) {
        dropzone.disable();
    }
});

onBeforeUnmount(() => {
    items.value.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    dropzone?.destroy();
    dropzone = null;
});

defineExpose({ open });
</script>

<template>
    <div
        ref="root"
        class="composer-dropzone relative"
        :class="disabled && 'pointer-events-none opacity-50'"
    >
        <button
            ref="picker"
            type="button"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
        >
            Add files
        </button>
        <div
            v-if="items.length === 0"
            class="border-border hover:border-primary/50 hover:bg-accent/30 flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-8"
            @click="open()"
        >
            <ImagePlus class="text-primary size-8" />
            <span class="text-sm font-medium">Drop photos or videos</span>
            <span class="text-muted-foreground text-xs">
                Or click to upload. Up to {{ MAX_FILES }} files.
            </span>
        </div>

        <div v-else class="grid grid-cols-3 gap-2">
            <div
                v-for="item in items"
                :key="item.key"
                class="relative aspect-square overflow-hidden rounded-xl bg-neutral-950"
            >
                <img
                    v-if="item.kind === 'image'"
                    :src="item.previewUrl"
                    alt=""
                    class="size-full object-cover"
                >
                <video
                    v-else
                    :src="item.previewUrl"
                    class="size-full object-cover"
                    muted
                    playsinline
                    preload="metadata"
                />

                <span
                    v-if="item.kind === 'video' && item.status === 'ready'"
                    class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/25"
                >
                    <span class="flex size-9 items-center justify-center rounded-full bg-black/70 text-white">
                        <Play class="size-4 fill-current" />
                    </span>
                </span>

                <div
                    v-if="item.status === 'uploading'"
                    class="absolute inset-0 flex items-center justify-center bg-black/50"
                >
                    <div class="relative size-14">
                        <svg class="size-14 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                            <circle
                                cx="18"
                                cy="18"
                                r="14"
                                fill="none"
                                stroke="rgb(255 255 255 / 0.25)"
                                stroke-width="2.5"
                            />
                            <circle
                                cx="18"
                                cy="18"
                                r="14"
                                fill="none"
                                stroke="white"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                :stroke-dasharray="RING"
                                :stroke-dashoffset="RING * (1 - item.progress / 100)"
                            />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-[11px] font-semibold text-white">
                            {{ Math.round(item.progress) }}%
                        </span>
                    </div>
                </div>

                <div
                    v-else-if="item.status === 'error'"
                    class="absolute inset-0 flex items-center justify-center bg-black/60 px-2 text-center text-[11px] font-medium text-white"
                >
                    {{ item.message ?? 'Upload failed.' }}
                </div>

                <button
                    type="button"
                    class="absolute top-1.5 right-1.5 z-10 flex size-7 cursor-pointer items-center justify-center rounded-full bg-black/70 text-white"
                    aria-label="Remove file"
                    @click.stop="removeItem(item)"
                >
                    <X class="size-3.5" />
                </button>
            </div>

            <button
                v-if="canAddMore"
                type="button"
                class="border-border hover:border-primary/50 hover:bg-accent/30 flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed"
                @click="open()"
            >
                <ImagePlus class="text-muted-foreground size-6" />
                <span class="text-muted-foreground text-[11px] font-medium">Add</span>
            </button>
        </div>
    </div>
</template>

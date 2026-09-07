<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const open = defineModel<boolean>('open', { default: false });

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const previewKind = ref<'image' | 'video' | null>(null);

const form = useForm({
    media: null as File | null,
    caption: '',
});

const canSubmit = computed(
    () => Boolean(form.media) && !form.processing,
);

const revokePreview = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    previewKind.value = null;
};

const reset = () => {
    form.reset();
    form.clearErrors();
    revokePreview();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

watch(open, (isOpen) => {
    if (!isOpen) {
        reset();
    }
});

onUnmounted(revokePreview);

const pickFile = () => {
    fileInput.value?.click();
};

const onFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    revokePreview();
    form.media = file;
    form.clearErrors('media');

    if (!file) {
        return;
    }

    previewUrl.value = URL.createObjectURL(file);
    previewKind.value = file.type.startsWith('video/') ? 'video' : 'image';
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    form.post('/stories', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add to your story</DialogTitle>
                <DialogDescription>
                    A photo or video, live for 24 hours.
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*,video/*"
                    class="sr-only"
                    @change="onFile"
                >

                <button
                    v-if="!previewUrl"
                    type="button"
                    class="border-border hover:border-primary/50 hover:bg-accent/40 flex min-h-48 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-8"
                    @click="pickFile"
                >
                    <ImagePlus class="text-primary size-8" />
                    <span class="text-sm font-medium">Choose a photo or video</span>
                    <span class="text-muted-foreground text-xs">
                        Images up to 8 MB. Videos up to 50 MB.
                    </span>
                </button>

                <div v-else class="relative overflow-hidden rounded-xl">
                    <img
                        v-if="previewKind === 'image'"
                        :src="previewUrl"
                        alt="Story preview"
                        class="max-h-80 w-full object-cover"
                    >
                    <video
                        v-else
                        :src="previewUrl ?? undefined"
                        class="max-h-80 w-full object-cover"
                        muted
                        playsinline
                        controls
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon-sm"
                        class="absolute top-2 right-2 rounded-full"
                        @click="reset"
                    >
                        <X class="size-4" />
                        <span class="sr-only">Remove media</span>
                    </Button>
                </div>
                <InputError :message="form.errors.media" />

                <div>
                    <textarea
                        v-model="form.caption"
                        rows="2"
                        maxlength="200"
                        class="border-input placeholder:text-muted-foreground w-full resize-none rounded-xl border bg-transparent px-3 py-2 text-sm outline-none"
                        placeholder="Add a caption (optional)"
                    />
                    <InputError :message="form.errors.caption" />
                </div>

                <Button type="submit" class="w-full" :loading="form.processing" :disabled="!form.media">
                    Share to story
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

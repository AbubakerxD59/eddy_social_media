<script setup lang="ts">
import { Image, Link2, Video, X } from '@lucide/vue';
import { computed, nextTick, onMounted, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LinkPreviewCard from '@/components/LinkPreviewCard.vue';
import MediaCarousel from '@/components/MediaCarousel.vue';
import ThreadAvatar from '@/components/ThreadAvatar.vue';
import { Button } from '@/components/ui/button';
import { useSignalComposer } from '@/composables/useSignalComposer';
import type { SignalMedia } from '@/types/social';

const { variant = 'inline', parentId } = defineProps<{
    variant?: 'inline' | 'dock';
    parentId?: string;
}>();

const emit = defineEmits<{
    close: [];
    created: [];
}>();

const {
    user,
    form,
    imageInput,
    videoInput,
    bodyInput,
    previewing,
    preview,
    localPreviews,
    canSubmit,
    placeholder,
    resizeBody,
    focusBody,
    setType,
    onImages,
    onVideo,
    fetchPreview,
    submit,
} = useSignalComposer({
    parentId,
    onSuccess: () => emit('created'),
});

const previewMedia = computed<SignalMedia[]>(() =>
    localPreviews.value.map((url, id) => ({
        id,
        kind: 'image',
        url,
        mime_type: null,
    })),
);

watch(() => form.body, () => {
    void nextTick(resizeBody);
});

onMounted(() => {
    if (variant === 'dock') {
        focusBody();
        return;
    }

    resizeBody();
});
</script>

<template>
    <form
        :class="
            variant === 'dock'
                ? 'bg-popover border-border flex max-h-[min(36rem,calc(100vh-3rem))] w-[min(26rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border shadow-2xl'
                : 'border-b px-4'
        "
        @submit.prevent="submit"
    >
        <header
            v-if="variant === 'dock'"
            class="grid grid-cols-[2rem_1fr_2rem] items-center px-3 pt-3 pb-2"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground"
                aria-label="Close"
                @click="emit('close')"
            >
                <X class="size-4" />
            </Button>
            <h2 class="text-center text-[15px] font-semibold">New signal</h2>
        </header>

        <div
            class="flex min-h-0 flex-1 gap-3 overflow-y-auto"
            :class="variant === 'dock' ? 'px-4' : ''"
        >
            <ThreadAvatar
                :class="variant === 'dock' ? 'pt-1' : 'pt-4'"
                :name="user.name"
                :avatar="user.avatar"
                show-line
            />

            <div
                class="min-w-0 flex-1"
                :class="variant === 'dock' ? 'pb-2' : 'pt-4 pb-3'"
            >
                <p class="text-[15px] leading-none font-semibold">
                    {{ variant === 'dock' ? user.username : user.name }}
                </p>

                <textarea
                    ref="bodyInput"
                    v-model="form.body"
                    rows="2"
                    class="placeholder:text-muted-foreground mt-1.5 w-full resize-none bg-transparent text-[15px] leading-relaxed outline-none"
                    :placeholder="placeholder"
                    @input="resizeBody"
                />
                <InputError :message="form.errors.body" />

                <div v-if="form.type === 'images'" class="mt-3 space-y-2">
                    <input
                        ref="imageInput"
                        type="file"
                        accept="image/*"
                        multiple
                        class="hidden"
                        @change="onImages"
                    />
                    <div v-if="localPreviews.length" class="relative">
                        <button
                            type="button"
                            class="absolute top-2 right-2 z-10 flex size-7 items-center justify-center rounded-full bg-black/70 text-white"
                            aria-label="Remove photos"
                            @click="setType('quote')"
                        >
                            <X class="size-3.5" />
                        </button>
                        <MediaCarousel :media="previewMedia" />
                    </div>
                    <InputError :message="form.errors.media || form.errors['media.0']" />
                </div>

                <div v-else-if="form.type === 'video'" class="mt-3 space-y-2">
                    <input
                        ref="videoInput"
                        type="file"
                        accept="video/*"
                        class="hidden"
                        @change="onVideo"
                    />
                    <div v-if="localPreviews[0]" class="relative">
                        <button
                            type="button"
                            class="absolute top-2 right-2 z-10 flex size-7 items-center justify-center rounded-full bg-black/70 text-white"
                            aria-label="Remove video"
                            @click="setType('quote')"
                        >
                            <X class="size-3.5" />
                        </button>
                        <video
                            :src="localPreviews[0]"
                            class="max-h-72 w-full rounded-2xl border"
                            controls
                        />
                    </div>
                    <InputError :message="form.errors.media || form.errors['media.0']" />
                </div>

                <div v-else-if="form.type === 'link'" class="mt-3 space-y-2">
                    <div class="flex gap-2">
                        <input
                            v-model="form.link_url"
                            type="url"
                            placeholder="https://…"
                            class="border-input h-9 flex-1 rounded-full border bg-transparent px-3 text-sm"
                            @blur="fetchPreview"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="rounded-full"
                            :loading="previewing"
                            @click="fetchPreview"
                        >
                            Preview
                        </Button>
                    </div>
                    <InputError :message="form.errors.link_url" />
                    <a
                        v-if="form.link_url.trim()"
                        :href="form.link_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary inline-block cursor-pointer text-sm underline underline-offset-2"
                    >
                        {{ form.link_url }}
                    </a>
                    <LinkPreviewCard v-if="preview" :link="preview" />
                </div>

                <div
                    class="mt-3 flex items-center"
                    :class="variant === 'inline' && 'justify-between gap-3'"
                >
                    <div class="flex items-center gap-0.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground"
                            :class="form.type === 'images' && 'text-primary'"
                            aria-label="Add photos"
                            @click="setType('images')"
                        >
                            <Image class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground"
                            :class="form.type === 'video' && 'text-primary'"
                            aria-label="Add a video"
                            @click="setType('video')"
                        >
                            <Video class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-muted-foreground"
                            :class="form.type === 'link' && 'text-primary'"
                            aria-label="Add a link"
                            @click="setType('link')"
                        >
                            <Link2 class="size-4" />
                        </Button>
                    </div>

                    <Button
                        v-if="variant === 'inline'"
                        type="submit"
                        size="sm"
                        class="rounded-full px-5"
                        :disabled="!canSubmit"
                        :loading="form.processing"
                    >
                        {{ parentId ? 'Reply' : 'Signal' }}
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="variant === 'dock'"
            class="flex items-center justify-end border-t px-4 py-3"
        >
            <Button
                type="submit"
                size="sm"
                class="rounded-full px-5"
                :disabled="!canSubmit"
                :loading="form.processing"
            >
                Signal
            </Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import ComposerDropzone from '@/components/ComposerDropzone.vue';
import ComposerEditor from '@/components/ComposerEditor.vue';
import InputError from '@/components/InputError.vue';
import LinkPreviewCard from '@/components/LinkPreviewCard.vue';
import LocationAutocomplete from '@/components/LocationAutocomplete.vue';
import ThreadAvatar from '@/components/ThreadAvatar.vue';
import TimelineDurationField from '@/components/TimelineDurationField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { useSignalComposer } from '@/composables/useSignalComposer';

const {
    composer,
    showIdentity = false,
    identityName,
    showSubmit = true,
} = defineProps<{
    composer: ReturnType<typeof useSignalComposer>;
    showIdentity?: boolean;
    identityName: string;
    showSubmit?: boolean;
}>();

const {
    user,
    form,
    previewing,
    preview,
    mediaKind,
    mediaUploading,
    canSubmit,
    showLinkPreviewButton,
    placeholder,
    submitLabel,
    fetchPreview,
    addPollOption,
    removePollOption,
} = composer;

const allowsMedia = computed(() => form.type !== 'poll');
const page = usePage();
const originLatitude = computed(() => page.props.viewerLatitude ?? null);
const originLongitude = computed(() => page.props.viewerLongitude ?? null);

const setBodyEditor = (el: unknown) => {
    const instance = el as { focus?: () => void } | null;
    composer.bodyEditor.value = instance?.focus
        ? { focus: () => instance.focus?.() }
        : null;
};
</script>

<template>
    <div class="flex min-h-0 gap-3">
        <ThreadAvatar
            v-if="showIdentity && user"
            :name="user.name"
            :avatar="user.avatar"
            show-line
        />

        <div class="min-w-0 flex-1 pb-2">
            <p v-if="showIdentity && user" class="text-[15px] leading-none font-semibold">
                {{ identityName }}
            </p>

            <div
                v-if="form.type === 'need' || form.type === 'opportunity'"
                class="mt-3 space-y-2"
            >
                <input
                    :ref="(el) => (composer.titleInput.value = (el as HTMLInputElement | null))"
                    v-model="form.title"
                    :placeholder="form.type === 'need' ? 'What do you need?' : 'What is the opportunity?'"
                    class="border-input placeholder:text-muted-foreground h-9 w-full rounded-lg border bg-transparent px-3 text-sm outline-none"
                />
                <InputError :message="form.errors.title" />
            </div>

            <ComposerEditor
                :ref="setBodyEditor"
                v-model="form.body"
                class="mt-1.5"
                :placeholder="placeholder"
            />
            <InputError :message="form.errors.body" />

            <div v-if="showLinkPreviewButton" class="mt-2">
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
            <div v-else-if="preview" class="mt-3">
                <LinkPreviewCard :link="preview" />
            </div>
            <InputError :message="form.errors.link_url" />

            <div
                v-if="form.type === 'need'"
                class="mt-3 grid grid-cols-2 gap-2"
            >
                <Input v-model="form.budget" placeholder="Budget" class="h-9 rounded-lg" />
                <div class="space-y-1">
                    <TimelineDurationField
                        v-model:amount="form.timeline_amount"
                        v-model:unit="form.timeline_unit"
                    />
                    <InputError :message="form.errors.timeline" />
                </div>
                <div class="col-span-2 space-y-1">
                    <LocationAutocomplete
                        v-model="form.location"
                        v-model:latitude="form.latitude"
                        v-model:longitude="form.longitude"
                        v-model:place-id="form.place_id"
                        :origin-latitude="originLatitude"
                        :origin-longitude="originLongitude"
                    />
                    <InputError :message="form.errors.location" />
                </div>
                <Input v-model="form.skills" placeholder="Skills (comma separated)" class="col-span-2 h-9 rounded-lg" />
            </div>

            <div
                v-else-if="form.type === 'opportunity'"
                class="mt-3 grid grid-cols-2 gap-2"
            >
                <Input v-model="form.project_value" placeholder="Project value" class="h-9 rounded-lg" />
                <div class="space-y-1">
                    <TimelineDurationField
                        v-model:amount="form.timeline_amount"
                        v-model:unit="form.timeline_unit"
                    />
                    <InputError :message="form.errors.timeline" />
                </div>
                <div class="col-span-2 space-y-1">
                    <LocationAutocomplete
                        v-model="form.location"
                        v-model:latitude="form.latitude"
                        v-model:longitude="form.longitude"
                        v-model:place-id="form.place_id"
                        :origin-latitude="originLatitude"
                        :origin-longitude="originLongitude"
                    />
                    <InputError :message="form.errors.location" />
                </div>
                <Input v-model="form.trades" placeholder="Trades needed" class="col-span-2 h-9 rounded-lg" />
            </div>

            <div v-else-if="form.type === 'poll'" class="mt-3 space-y-2">
                <div
                    v-for="(_, index) in form.poll_options"
                    :key="index"
                    class="flex items-center gap-2"
                >
                    <Input
                        v-model="form.poll_options[index]"
                        :placeholder="`Option ${index + 1}`"
                        class="h-9 rounded-lg"
                    />
                    <Button
                        v-if="form.poll_options.length > 2"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="text-muted-foreground"
                        aria-label="Remove option"
                        @click="removePollOption(index)"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
                <InputError :message="form.errors.poll_options" />
                <Button
                    v-if="form.poll_options.length < 4"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground h-8 px-2"
                    @click="addPollOption"
                >
                    <Plus class="size-3.5" />
                    Add option
                </Button>
            </div>

            <div v-if="allowsMedia" class="mt-3 space-y-2">
                <ComposerDropzone
                    v-model="form.media_ids"
                    @added="mediaKind = 'files'"
                    @uploading="mediaUploading = $event"
                />
                <InputError :message="form.errors.media_ids" />
            </div>

            <div v-if="showSubmit" class="mt-3 flex justify-end">
                <Button
                    type="submit"
                    size="sm"
                    class="rounded-full px-5"
                    :disabled="!canSubmit"
                    :loading="form.processing"
                >
                    {{ submitLabel }}
                </Button>
            </div>
        </div>
    </div>
</template>

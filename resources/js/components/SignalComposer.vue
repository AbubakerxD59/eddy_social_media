<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SignalComposerFields from '@/components/SignalComposerFields.vue';
import SignalTypePicker from '@/components/SignalTypePicker.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useSignalComposer } from '@/composables/useSignalComposer';
import type { FeedSignal, SignalType } from '@/types/social';

const { variant = 'inline', parentId, initialType, editing = null } = defineProps<{
    variant?: 'inline' | 'dock';
    parentId?: string;
    initialType?: SignalType | null;
    editing?: FeedSignal | null;
}>();

const emit = defineEmits<{
    close: [];
    created: [];
}>();

const isEditing = computed(() => editing != null);
const dialogOpen = ref(isEditing.value);

const composer = useSignalComposer({
    parentId: editing ? undefined : parentId,
    initialType: editing?.type ?? initialType ?? undefined,
    editing: editing ?? undefined,
    onSuccess: () => {
        dialogOpen.value = false;
        emit('created');
        emit('close');
    },
});

const {
    user,
    form,
    isReply,
    meta,
    canSubmit,
    submitLabel,
    focusBody,
    setType,
    resetComposer,
    submit,
} = composer;

const usesTypeDialog = computed(() => variant === 'inline' && !isReply.value && !isEditing.value);
const showComposerDialog = computed(() => usesTypeDialog.value || isEditing.value);

const preventDismiss = (event: Event) => {
    if (isEditing.value) {
        return;
    }

    event.preventDefault();
};

const openType = (type: SignalType) => {
    resetComposer();
    setType(type);
    dialogOpen.value = true;
};

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        if (isEditing.value) {
            emit('close');
            return;
        }

        resetComposer();
        return;
    }

    void nextTick(() => {
        if (form.type === 'need' || form.type === 'opportunity') {
            composer.focusTitle();
            return;
        }

        focusBody();
    });
});

watch(
    () => initialType,
    (type) => {
        if (type && usesTypeDialog.value) {
            openType(type);
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (isEditing.value) {
        void nextTick(() => {
            if (form.type === 'need' || form.type === 'opportunity') {
                composer.focusTitle();
                return;
            }

            focusBody();
        });
        return;
    }

    if (usesTypeDialog.value) {
        return;
    }

    if (variant === 'dock') {
        focusBody();
    }
});
</script>

<template>
    <section
        v-if="usesTypeDialog"
        id="composer"
        class="glass-panel rounded-2xl px-4 py-4"
    >
        <SignalTypePicker
            :model-value="dialogOpen ? form.type : null"
            @update:model-value="openType"
        />
    </section>

    <Dialog v-if="showComposerDialog" :open="dialogOpen" @update:open="dialogOpen = $event">
        <DialogContent
            class="flex max-h-[min(92svh,52rem)] flex-col gap-0 p-0 sm:max-w-3xl"
            @pointer-down-outside="preventDismiss"
            @focus-outside="preventDismiss"
            @interact-outside="preventDismiss"
            @escape-key-down="preventDismiss"
        >
            <div class="flex items-center justify-between border-b px-5 py-3 pr-12">
                <div>
                    <DialogTitle>{{ isEditing ? 'Edit' : 'New' }} {{ meta.label.toLowerCase() }}</DialogTitle>
                    <DialogDescription class="text-muted-foreground text-sm">
                        {{ isEditing ? 'Update this signal and save your changes.' : meta.description }}
                    </DialogDescription>
                </div>
            </div>

            <form
                class="flex min-h-0 flex-1 flex-col"
                @submit.prevent="submit"
            >
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <SignalComposerFields
                        :composer="composer"
                        show-identity
                        :identity-name="user?.name ?? ''"
                        :show-submit="false"
                    />
                </div>

                <div class="flex items-center justify-end border-t px-5 py-3">
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
            </form>
        </DialogContent>
    </Dialog>

    <form
        v-if="!usesTypeDialog && !isEditing"
        :class="
            variant === 'dock'
                ? 'glass-popup flex max-h-[min(36rem,calc(100vh-3rem))] w-[min(36rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl'
                : 'glass-panel rounded-2xl px-4 py-4'
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
            <h2 class="text-center text-[15px] font-semibold">
                New {{ meta.label.toLowerCase() }}
            </h2>
        </header>

        <div
            class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto"
            :class="variant === 'dock' ? 'px-4' : ''"
        >
            <SignalTypePicker
                v-if="!isReply && variant === 'dock'"
                :model-value="form.type"
                compact
                class="pt-1"
                @update:model-value="setType"
            />

            <SignalComposerFields
                :composer="composer"
                :show-identity="Boolean(isReply || variant === 'dock')"
                :identity-name="variant === 'dock' ? (user?.username ?? '') : (user?.name ?? '')"
                :show-submit="variant === 'inline'"
            />
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
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>

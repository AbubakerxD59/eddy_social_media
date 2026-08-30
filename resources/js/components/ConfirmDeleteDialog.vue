<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const open = defineModel<boolean>('open', { default: false });

withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel?: string;
        cancelLabel?: string;
        loading?: boolean;
        showFooter?: boolean;
    }>(),
    {
        confirmLabel: 'Delete',
        cancelLabel: 'Cancel',
        loading: false,
        showFooter: true,
    },
);

const emit = defineEmits<{
    confirm: [];
}>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent
            :show-close-button="false"
            class="gap-0 overflow-hidden rounded-[20px] border-neutral-700 bg-neutral-800 p-0 text-white shadow-2xl sm:max-w-80"
        >
            <div class="px-6 py-6 text-center">
                <DialogTitle class="text-[17px] font-semibold text-white">
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="mt-2 text-sm leading-relaxed text-neutral-400">
                    {{ description }}
                </DialogDescription>

                <div v-if="$slots.default" class="mt-4 text-left">
                    <slot />
                </div>
            </div>

            <div
                v-if="showFooter || $slots.footer"
                class="grid grid-cols-2 border-t border-neutral-700"
            >
                <slot name="footer">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="loading"
                            class="h-auto rounded-none bg-transparent py-3.5 text-[17px] font-medium text-white hover:bg-white/5 hover:text-white"
                        >
                            {{ cancelLabel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="ghost"
                        :loading="loading"
                        class="h-auto rounded-none border-l border-neutral-700 bg-transparent py-3.5 text-[17px] font-medium text-red-500 hover:bg-white/5 hover:text-red-500"
                        @click="emit('confirm')"
                    >
                        {{ confirmLabel }}
                    </Button>
                </slot>
            </div>
        </DialogContent>
    </Dialog>
</template>

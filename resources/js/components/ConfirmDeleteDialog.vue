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
            class="z-[80] gap-0 overflow-hidden rounded-[20px] p-0 sm:max-w-80"
        >
            <div class="px-6 py-6 text-center">
                <DialogTitle class="text-foreground text-[17px] font-semibold">
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="text-muted-foreground mt-2 text-sm leading-relaxed">
                    {{ description }}
                </DialogDescription>

                <div v-if="$slots.default" class="mt-4 text-left">
                    <slot />
                </div>
            </div>

            <div
                v-if="showFooter || $slots.footer"
                class="border-border grid grid-cols-2 border-t"
            >
                <slot name="footer">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="loading"
                            class="text-foreground hover:bg-accent hover:text-foreground h-auto rounded-none bg-transparent py-3.5 text-[17px] font-medium"
                        >
                            {{ cancelLabel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="ghost"
                        :loading="loading"
                        class="text-destructive hover:text-destructive hover:bg-accent h-auto rounded-none border-l border-border bg-transparent py-3.5 text-[17px] font-medium"
                        @click="emit('confirm')"
                    >
                        {{ confirmLabel }}
                    </Button>
                </slot>
            </div>
        </DialogContent>
    </Dialog>
</template>

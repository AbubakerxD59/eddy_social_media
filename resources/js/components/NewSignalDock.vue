<script setup lang="ts">
import { onKeyStroke } from '@vueuse/core';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import SignalComposer from '@/components/SignalComposer.vue';

const open = ref(false);

const openDock = () => {
    open.value = true;
};

const closeDock = () => {
    open.value = false;
};

onKeyStroke('Escape', (event) => {
    if (!open.value) {
        return;
    }

    event.preventDefault();
    closeDock();
});
</script>

<template>
    <div class="fixed right-5 bottom-5 z-40 sm:right-6 sm:bottom-6">
        <SignalComposer
            v-if="open"
            variant="dock"
            @close="closeDock"
            @created="closeDock"
        />

        <button
            v-else
            type="button"
            class="bg-foreground text-background hover:bg-foreground/90 flex size-14 cursor-pointer items-center justify-center rounded-[22px] shadow-xl transition-transform hover:scale-[1.03] active:scale-95"
            aria-label="New signal"
            @click="openDock"
        >
            <Plus class="size-7" stroke-width="2.25" />
        </button>
    </div>
</template>

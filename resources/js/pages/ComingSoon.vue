<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const { title, description } = defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="glass-panel rounded-2xl px-6 py-12">
        <h1 class="text-2xl font-semibold">{{ title }}</h1>
        <p class="text-muted-foreground mt-3 max-w-xl text-sm leading-relaxed">
            {{ description }}
        </p>
    </div>
</template>

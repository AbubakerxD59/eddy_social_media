<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const { title, description } = defineProps<{
    title: string;
    description: string;
}>();

</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-xl px-4 py-16">
        <h1 class="text-2xl font-semibold">{{ title }}</h1>
        <p class="text-muted-foreground mt-3 text-sm leading-relaxed">
            {{ description }}
        </p>
    </div>
</template>

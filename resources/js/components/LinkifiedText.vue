<script setup lang="ts">
import { computed } from 'vue';
import { hrefForUrl, linkifyParts } from '@/lib/linkify';

const { text } = defineProps<{
    text: string;
}>();

const parts = computed(() => linkifyParts(text));
</script>

<template>
    <template v-for="(part, index) in parts" :key="index">
        <a
            v-if="part.type === 'link'"
            :href="hrefForUrl(part.value)"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary cursor-pointer underline underline-offset-2"
            data-no-nav
            @click.stop
        >{{ part.value }}</a>
        <template v-else>{{ part.value }}</template>
    </template>
</template>

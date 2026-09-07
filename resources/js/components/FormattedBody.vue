<script setup lang="ts">
import { computed } from 'vue';
import LinkifiedText from '@/components/LinkifiedText.vue';
import { isRichHtml } from '@/lib/htmlBody';

const { html } = defineProps<{
    html: string | null;
}>();

const rich = computed(() => isRichHtml(html));
</script>

<template>
    <div
        v-if="rich && html"
        class="signal-body"
        v-html="html"
    />
    <LinkifiedText v-else-if="html" :text="html" />
</template>

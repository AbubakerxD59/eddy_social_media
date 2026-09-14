<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Toaster } from '@/components/ui/sonner';
import { home } from '@/routes';

const props = defineProps<{
    title?: string;
    description?: string;
    wide?: boolean;
}>();

const descriptionParts = computed(() => {
    const text = props.description ?? '';
    const pattern = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g;
    const parts: { text: string; email: boolean }[] = [];
    let lastIndex = 0;

    for (const match of text.matchAll(pattern)) {
        const index = match.index ?? 0;

        if (index > lastIndex) {
            parts.push({ text: text.slice(lastIndex, index), email: false });
        }

        parts.push({ text: match[0], email: true });
        lastIndex = index + match[0].length;
    }

    if (lastIndex < text.length) {
        parts.push({ text: text.slice(lastIndex), email: false });
    }

    return parts;
});
</script>

<template>
    <div
        class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 overflow-y-auto p-6 md:p-10"
    >
        <div class="w-full" :class="wide ? 'max-w-2xl' : 'max-w-sm'">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-primary"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p v-if="description" class="text-muted-foreground text-center text-sm">
                            <template v-for="(part, index) in descriptionParts" :key="index">
                                <span
                                    v-if="part.email"
                                    class="text-foreground font-semibold break-all"
                                >{{ part.text }}</span>
                                <template v-else>{{ part.text }}</template>
                            </template>
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
        <Toaster />
    </div>
</template>

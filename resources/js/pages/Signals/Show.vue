<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import SignalCard from '@/components/SignalCard.vue';
import SignalComposer from '@/components/SignalComposer.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { htmlToPlainText } from '@/lib/htmlBody';
import type { FeedSignal } from '@/types/social';

const { signal, replies } = defineProps<{
    signal: FeedSignal;
    replies?: FeedSignal[];
}>();

const page = usePage();
const canReply = computed(() => Boolean(page.props.auth.user));
const pageTitle = computed(() => {
    if (signal.title) {
        return signal.title;
    }

    const body = htmlToPlainText(signal.body);

    return body ? body.slice(0, 48) : 'Signal';
});
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-1">
            <Button
                as-child
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground"
            >
                <Link href="/dashboard" aria-label="Back to Home">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <h1 class="text-[17px] font-semibold">Signal</h1>
        </div>

        <SignalCard :signal="signal" />

        <SignalComposer v-if="canReply" :parent-id="signal.id" />

        <Deferred data="replies">
            <template #fallback>
                <div class="flex flex-col gap-3">
                    <Skeleton v-for="n in 2" :key="n" class="h-24 w-full rounded-2xl" />
                </div>
            </template>

            <div v-if="replies?.length" class="flex flex-col gap-3">
                <SignalCard
                    v-for="reply in replies"
                    :key="reply.id"
                    :signal="reply"
                    variant="thread"
                />
            </div>

            <div
                v-if="(replies?.length ?? 0) === 0 && !canReply"
                class="glass-panel text-muted-foreground rounded-2xl px-4 py-10 text-center text-sm"
            >
                Log in to reply to this signal.
            </div>
        </Deferred>
    </div>
</template>

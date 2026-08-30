<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import SignalCard from '@/components/SignalCard.vue';
import SignalComposer from '@/components/SignalComposer.vue';
import { Button } from '@/components/ui/button';
import type { FeedSignal } from '@/types/social';

const { signal, replies } = defineProps<{
    signal: FeedSignal;
    replies: FeedSignal[];
}>();

const page = usePage();
const canReply = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <Head :title="signal.body ? signal.body.slice(0, 48) : 'Signal'" />

    <div class="mx-auto w-full max-w-3xl px-4 pb-6">
        <div class="flex items-center gap-1 py-3">
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

        <div class="rounded-2xl border [&>:last-child]:border-b-0">
            <SignalCard :signal="signal" :show-line="replies.length > 0 || canReply" />

            <SignalComposer v-if="canReply" :parent-id="signal.id" />

            <SignalCard
                v-for="(reply, index) in replies"
                :key="reply.id"
                :signal="reply"
                :show-line="index < replies.length - 1"
            />

            <div
                v-if="replies.length === 0 && !canReply"
                class="text-muted-foreground px-4 py-10 text-center text-sm"
            >
                Log in to reply to this signal.
            </div>
        </div>
    </div>
</template>

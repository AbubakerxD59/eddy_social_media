<script setup lang="ts">
import { ref } from 'vue';
import { csrfHeaders } from '@/lib/csrf';
import { notifyError } from '@/lib/notify';
import { cn } from '@/lib/utils';
import type { SignalPoll } from '@/types/social';

const { signalId, poll: initialPoll } = defineProps<{
    signalId: string;
    poll: SignalPoll;
}>();

const poll = ref(initialPoll);
const voting = ref<string | null>(null);

const percent = (votes: number): number => {
    if (poll.value.total_votes <= 0) {
        return 0;
    }

    return Math.round((votes / poll.value.total_votes) * 100);
};

const vote = async (optionId: string) => {
    if (voting.value || poll.value.voted_option_id === optionId) {
        return;
    }

    voting.value = optionId;

    try {
        const response = await fetch(`/signals/${signalId}/vote`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            body: JSON.stringify({ option_id: optionId }),
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';
            return;
        }

        if (!response.ok) {
            throw new Error('Vote failed');
        }

        const data = (await response.json()) as { poll: SignalPoll };
        poll.value = data.poll;
    } catch {
        notifyError('Could not record your vote.');
    } finally {
        voting.value = null;
    }
};
</script>

<template>
    <div class="space-y-2" data-no-nav>
        <button
            v-for="option in poll.options"
            :key="option.id"
            type="button"
            class="relative w-full cursor-pointer overflow-hidden rounded-xl border px-3 py-2.5 text-left text-sm transition-colors"
            :class="
                cn(
                    poll.voted_option_id === option.id
                        ? 'border-poll/50 bg-poll/10'
                        : 'border-border hover:bg-accent/60',
                    voting && 'cursor-not-allowed',
                )
            "
            :disabled="voting !== null"
            :aria-pressed="poll.voted_option_id === option.id"
            @click="vote(option.id)"
        >
            <span
                v-if="poll.voted_option_id"
                class="bg-poll/20 absolute inset-y-0 left-0"
                :style="{ width: `${percent(option.votes_count)}%` }"
            />
            <span class="relative flex items-center justify-between gap-3">
                <span class="font-medium">{{ option.text }}</span>
                <span
                    v-if="poll.voted_option_id"
                    class="text-muted-foreground shrink-0 tabular-nums"
                >
                    {{ percent(option.votes_count) }}%
                </span>
            </span>
        </button>

        <p
            v-if="poll.voted_option_id"
            class="text-muted-foreground px-0.5 text-[12px]"
        >
            {{ poll.total_votes }} {{ poll.total_votes === 1 ? 'vote' : 'votes' }}
        </p>
    </div>
</template>

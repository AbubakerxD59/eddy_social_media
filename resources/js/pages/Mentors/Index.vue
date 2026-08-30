<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import type { Paginator, PublicUser } from '@/types/social';

type Mentor = {
    id: number;
    headline: string | null;
    bio: string | null;
    hourly_rate_cents: number | null;
    google_connected: boolean;
    user: PublicUser;
};

defineProps<{
    mentors: Paginator<Mentor>;
    isMentor: boolean;
}>();
</script>

<template>
    <Head title="Mentors" />

    <div class="mx-auto w-full max-w-3xl space-y-8 px-4 py-6">
        <div>
            <h1 class="text-2xl font-semibold">Mentors</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Book live sessions with operators. Calendar and Stripe
                checkout land next; listings work now.
            </p>
        </div>

        <section
            v-if="!isMentor"
            class="rounded-xl border p-4"
        >
            <h2 class="font-medium">Become a mentor</h2>
            <p class="text-muted-foreground mb-4 text-sm">
                List yourself so founders can find you. Google Meet booking
                and Stripe payments will attach to this profile.
            </p>
            <Form
                action="/mentors"
                method="post"
                class="grid gap-3 md:grid-cols-2"
                v-slot="{ processing }"
            >
                <div class="grid gap-2 md:col-span-2">
                    <Label for="headline">Headline</Label>
                    <Input
                        id="headline"
                        name="headline"
                        placeholder="Go-to-market for B2B SaaS"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="bio">Bio</Label>
                    <textarea
                        id="bio"
                        name="bio"
                        rows="3"
                        class="border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="What you help founders with"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="hourly_rate_cents">Rate (cents / hour)</Label>
                    <Input
                        id="hourly_rate_cents"
                        name="hourly_rate_cents"
                        type="number"
                        min="0"
                        placeholder="15000"
                    />
                </div>
                <div class="flex items-end">
                    <Button type="submit" :loading="processing">
                        List me as a mentor
                    </Button>
                </div>
            </Form>
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <Link
                v-for="mentor in mentors.data"
                :key="mentor.id"
                :href="`/@${mentor.user.username}`"
                class="rounded-xl border p-4"
                data-clickable
            >
                <div class="flex items-center gap-3">
                    <Avatar>
                        <AvatarImage
                            v-if="mentor.user.avatar"
                            :src="mentor.user.avatar"
                            :alt="mentor.user.name"
                        />
                        <AvatarFallback>
                            {{ getInitials(mentor.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <p class="font-semibold hover:underline">
                            {{ mentor.user.name }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            @{{ mentor.user.username }}
                        </p>
                    </div>
                </div>
                <p v-if="mentor.headline" class="mt-3 font-medium">
                    {{ mentor.headline }}
                </p>
                <p v-if="mentor.bio" class="text-muted-foreground mt-1 text-sm">
                    {{ mentor.bio }}
                </p>
                <p
                    v-if="mentor.hourly_rate_cents"
                    class="mt-3 text-sm font-medium"
                >
                    ${{ (mentor.hourly_rate_cents / 100).toFixed(0) }} / hour
                </p>
                <p class="text-muted-foreground mt-2 text-xs">
                    {{
                        mentor.google_connected
                            ? 'Google Calendar connected'
                            : 'Live booking with Google Meet is coming next'
                    }}
                </p>
            </Link>
        </div>

        <p
            v-if="mentors.data.length === 0"
            class="text-muted-foreground text-sm"
        >
            No mentors are listed yet.
        </p>
    </div>
</template>

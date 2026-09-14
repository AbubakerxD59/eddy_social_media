<script setup lang="ts">
import { Deferred, Form, Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import TalentFields from '@/components/TalentFields.vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import ProfileHoverCard from '@/components/ProfileHoverCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import { formatUsdHourly } from '@/lib/currency';
import { notifyFormError, notifySuccess } from '@/lib/notify';
import type { Paginator, PublicUser } from '@/types/social';

type Talent = {
    id: number;
    headline: string | null;
    bio: string | null;
    skills: string[];
    hourly_rate_cents: number | null;
    is_available: boolean;
    user: PublicUser;
};

defineProps<{
    mentors?: Paginator<Talent>;
    isMentor: boolean;
    isTalent: boolean;
    canBecomeTalent: boolean;
    talentProfile?: {
        headline: string;
        bio: string;
        skills: string[] | null;
        hourly_rate_cents: number | null;
    } | null;
}>();
</script>

<template>
    <Head title="Talent Hub" />

    <div class="mx-auto w-full max-w-3xl space-y-8 px-4 py-6">
        <div>
            <h1 class="text-2xl font-semibold">Talent Hub</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Find freelancers and specialists. Explorers can list themselves as talent.
            </p>
        </div>

        <section
            v-if="canBecomeTalent || isTalent"
            class="bg-card rounded-xl border p-4"
        >
            <h2 class="font-medium">
                {{ isTalent ? 'Your talent profile' : 'Become talent' }}
            </h2>
            <p class="text-muted-foreground mb-4 text-sm">
                {{
                    isTalent
                        ? 'Update how businesses see your services.'
                        : 'Complete this form to convert your explorer account into a talent profile.'
                }}
            </p>
            <Form
                action="/talent"
                method="post"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="notifySuccess(isTalent ? 'Talent profile updated.' : 'You are now listed as talent.')"
                @error="notifyFormError($event)"
            >
                <TalentFields
                    :errors="errors"
                    :headline="talentProfile?.headline ?? ''"
                    :bio="talentProfile?.bio ?? ''"
                    :skills="(talentProfile?.skills ?? []).join(', ')"
                    :hourly-rate="
                        talentProfile?.hourly_rate_cents
                            ? talentProfile.hourly_rate_cents / 100
                            : ''
                    "
                />
                <div>
                    <Button type="submit" :loading="processing">
                        {{ isTalent ? 'Save talent profile' : 'Become talent' }}
                    </Button>
                </div>
            </Form>
        </section>

        <section
            v-else-if="!isMentor"
            class="bg-card rounded-xl border p-4"
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
                v-slot="{ processing, errors }"
                @success="notifySuccess('You are listed as a mentor.')"
                @error="notifyFormError($event)"
            >
                <div class="grid gap-2 md:col-span-2">
                    <Label for="headline">Headline</Label>
                    <Input
                        id="headline"
                        name="headline"
                        placeholder="Go-to-market for B2B SaaS"
                    />
                    <InputError :message="errors.headline" />
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
                    <InputError :message="errors.bio" />
                </div>
                <div class="grid gap-2">
                    <Label for="hourly_rate">Hourly rate in USD</Label>
                    <MoneyInput
                        id="hourly_rate"
                        name="hourly_rate"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="150"
                    />
                    <InputError :message="errors.hourly_rate" />
                </div>
                <div class="flex items-end">
                    <Button type="submit" :loading="processing">
                        List me as a mentor
                    </Button>
                </div>
            </Form>
        </section>

        <Deferred data="mentors">
            <template #fallback>
                <div class="grid gap-4 md:grid-cols-2">
                    <Skeleton v-for="n in 4" :key="n" class="h-40 rounded-xl" />
                </div>
            </template>

            <InfiniteScroll data="mentors" :buffer="400">
                <div class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="mentor in mentors?.data ?? []"
                        :key="mentor.id"
                        class="bg-card rounded-xl border p-4"
                    >
                        <div class="flex items-center gap-3">
                            <Link
                                :href="`/@${mentor.user.username}`"
                                class="shrink-0"
                            >
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
                            </Link>
                            <div>
                                <ProfileHoverCard :user="mentor.user" />
                                <p class="text-muted-foreground text-sm">
                                    @{{ mentor.user.username }}
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="`/@${mentor.user.username}`"
                            class="mt-3 block cursor-pointer"
                        >
                            <p v-if="mentor.headline" class="font-medium">
                                {{ mentor.headline }}
                            </p>
                            <p v-if="mentor.bio" class="text-muted-foreground mt-1 text-sm">
                                {{ mentor.bio }}
                            </p>
                            <p
                                v-if="mentor.skills?.length"
                                class="text-muted-foreground mt-2 text-xs"
                            >
                                {{ mentor.skills.join(' · ') }}
                            </p>
                            <p
                                v-if="formatUsdHourly(mentor.hourly_rate_cents)"
                                class="mt-3 text-sm font-medium"
                            >
                                {{ formatUsdHourly(mentor.hourly_rate_cents) }}
                            </p>
                        </Link>
                    </div>
                </div>

                <p
                    v-if="(mentors?.data.length ?? 0) === 0"
                    class="text-muted-foreground text-sm"
                >
                    No talent is listed yet.
                </p>
            </InfiniteScroll>
        </Deferred>
    </div>
</template>

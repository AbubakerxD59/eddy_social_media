<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';

const page = usePage();
const rail = computed(() => page.props.rail);

const events = [
    { month: 'MAY', day: '18', title: 'Operator Mixer', time: '6:00 PM' },
    { month: 'JUN', day: '04', title: 'Growth Summit', time: '9:00 AM' },
];
</script>

<template>
    <aside class="flex w-[320px] shrink-0 flex-col gap-4">
        <section class="glass-panel rounded-2xl p-4">
            <h2 class="text-sm font-semibold">AI Match For You</h2>
            <div v-if="!rail" class="mt-3 space-y-3">
                <div v-for="n in 3" :key="n" class="flex items-center gap-3">
                    <Skeleton class="size-10 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-3 w-28" />
                        <Skeleton class="h-3 w-20" />
                    </div>
                </div>
            </div>
            <div v-else class="mt-3 space-y-3">
                <div
                    v-for="match in rail.matches"
                    :key="match.id"
                    class="flex items-center gap-3"
                >
                    <Avatar class="size-10">
                        <AvatarImage v-if="match.avatar" :src="match.avatar" :alt="match.name" />
                        <AvatarFallback>{{ getInitials(match.name) }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ match.name }}</p>
                        <p class="text-muted-foreground truncate text-[12px]">
                            {{ match.headline || `@${match.username}` }}
                        </p>
                        <p class="text-primary text-[11px] font-semibold">
                            {{ match.match }}% Match
                        </p>
                    </div>
                    <Button
                        as-child
                        size="sm"
                        class="rounded-full px-3"
                    >
                        <Link :href="`/@${match.username}`">Connect</Link>
                    </Button>
                </div>
                <p
                    v-if="rail.matches.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    More operators will show up as the universe grows.
                </p>
            </div>
        </section>

        <section class="glass-panel rounded-2xl p-4">
            <h2 class="text-sm font-semibold">Needs Around You</h2>
            <div v-if="!rail" class="mt-3 space-y-2">
                <Skeleton v-for="n in 3" :key="n" class="h-16 w-full rounded-xl" />
            </div>
            <div v-else class="mt-3 space-y-2">
                <Link
                    v-for="need in rail.needs"
                    :key="need.id"
                    :href="`/s/${need.id}`"
                    class="hover:bg-accent flex cursor-pointer flex-col rounded-xl border px-3 py-2.5"
                >
                    <span class="text-[13px] font-medium">{{ need.title }}</span>
                    <span class="text-need mt-1 text-[12px] font-semibold">
                        {{ need.budget || 'Budget on request' }}
                    </span>
                    <span
                        v-if="need.location"
                        class="text-muted-foreground mt-0.5 flex items-center gap-1 text-[11px]"
                    >
                        <MapPin class="size-3" />
                        {{ need.location }}
                    </span>
                </Link>
                <p
                    v-if="rail.needs.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    No open needs yet.
                </p>
            </div>
        </section>

        <section class="glass-panel overflow-hidden rounded-2xl">
            <div class="from-primary/40 flex h-28 items-end bg-gradient-to-br to-transparent p-4">
                <p class="text-xs font-semibold tracking-wide uppercase">Deals Marketplace</p>
            </div>
            <div class="p-4">
                <p class="font-semibold">Featured listing</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    Profitable local service business ready for a new operator.
                </p>
                <p class="mt-2 text-sm font-semibold">$450,000 asking</p>
                <Button as-child class="mt-3 w-full rounded-full">
                    <Link href="/deals">View Deal</Link>
                </Button>
            </div>
        </section>

        <section class="glass-panel rounded-2xl p-4">
            <h2 class="text-sm font-semibold">Upcoming Events</h2>
            <div class="mt-3 space-y-2">
                <Link
                    v-for="event in events"
                    :key="event.title"
                    href="/events"
                    class="hover:bg-accent flex cursor-pointer items-center gap-3 rounded-xl px-1 py-1.5"
                >
                    <span class="bg-primary/15 text-primary flex size-12 flex-col items-center justify-center rounded-xl text-[10px] font-bold">
                        {{ event.month }}
                        <span class="text-foreground text-sm leading-none">{{ event.day }}</span>
                    </span>
                    <span>
                        <span class="block text-sm font-medium">{{ event.title }}</span>
                        <span class="text-muted-foreground text-[12px]">{{ event.time }}</span>
                    </span>
                </Link>
            </div>
        </section>
    </aside>
</template>

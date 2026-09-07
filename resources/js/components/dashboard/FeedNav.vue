<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    Building2,
    FolderKanban,
    Globe,
    Rocket,
    Shield,
    Sparkles,
    Store,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const rail = computed(() => page.props.rail);
const user = computed(() => page.props.auth.user);
const { isCurrentUrl } = useCurrentUrl();

const xpPercent = computed(() => {
    if (!rail.value) {
        return 0;
    }

    return Math.min(100, Math.round((rail.value.xp / rail.value.xp_max) * 100));
});

const mainNav: NavItem[] = [
    { title: 'Universe', href: dashboard(), icon: Globe },
    { title: 'Connections', href: '/connections', icon: Users },
    { title: 'Talent Hub', href: '/mentors', icon: Sparkles },
    { title: 'Businesses', href: '/businesses', icon: Building2 },
    { title: 'Opportunities', href: '/opportunities', icon: Rocket },
];

const spaceNav = computed<NavItem[]>(() => [
    { title: 'My Business', href: user.value ? `/@${user.value.username}` : '/dashboard', icon: Store },
    { title: 'My Needs', href: '/dashboard?filter=need', icon: Briefcase },
    { title: 'My Projects', href: '/projects', icon: FolderKanban },
    { title: 'My Wallet', href: '/wallet', icon: Wallet },
]);

const circles = [
    { title: 'Real Estate Owners', href: '/circles', color: 'bg-need' },
    { title: 'Investors Club', href: '/circles', color: 'bg-drop' },
    { title: 'Contractors', href: '/circles', color: 'bg-opportunity' },
];
</script>

<template>
    <aside class="flex w-[260px] shrink-0 flex-col gap-4">
        <div
            v-if="user && !rail"
            class="glass-panel space-y-3 rounded-2xl p-4"
        >
            <div class="flex items-center gap-3">
                <Skeleton class="size-11 rounded-xl" />
                <div class="flex-1 space-y-2">
                    <Skeleton class="h-3 w-16" />
                    <Skeleton class="h-4 w-24" />
                </div>
            </div>
            <Skeleton class="h-1.5 w-full rounded-full" />
        </div>

        <div
            v-else-if="rail && user"
            class="glass-panel rounded-2xl bg-gradient-to-br from-primary/15 to-transparent p-4"
        >
            <div class="flex items-center gap-3">
                <div
                    class="bg-need/20 text-need flex size-11 items-center justify-center rounded-xl shadow-[0_0_20px_hsl(37_97%_55%/0.25)]"
                >
                    <Shield class="size-5" />
                </div>
                <div>
                    <p class="text-[11px] font-medium tracking-wide text-need uppercase">
                        Level {{ rail.level }}
                    </p>
                    <p class="text-sm font-semibold">{{ rail.title }}</p>
                </div>
            </div>
            <div class="mt-3">
                <div class="bg-background/60 h-1.5 overflow-hidden rounded-full">
                    <div
                        class="from-need to-primary h-full rounded-full bg-gradient-to-r"
                        :style="{ width: `${xpPercent}%` }"
                    />
                </div>
                <p class="text-muted-foreground mt-1.5 text-[11px] tabular-nums">
                    {{ rail.xp.toLocaleString() }} / {{ rail.xp_max.toLocaleString() }} XP
                </p>
            </div>
        </div>

        <nav class="glass-panel rounded-2xl p-2">
            <Link
                v-for="item in mainNav"
                :key="item.title"
                :href="item.href"
                class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                :class="
                    cn(
                        isCurrentUrl(item.href)
                            ? 'bg-primary/15 text-primary shadow-[0_0_18px_hsl(252_56%_57%/0.18)]'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                    )
                "
            >
                <component :is="item.icon" class="size-4 shrink-0" />
                {{ item.title }}
            </Link>
        </nav>

        <div class="glass-panel rounded-2xl p-3">
            <p class="text-muted-foreground px-1 pb-2 text-[11px] font-semibold tracking-wide uppercase">
                My Space
            </p>
            <Link
                v-for="item in spaceNav"
                :key="item.title"
                :href="item.href"
                class="text-muted-foreground hover:bg-accent hover:text-foreground flex cursor-pointer items-center gap-3 rounded-xl px-2 py-2 text-sm font-medium"
            >
                <component :is="item.icon" class="size-4 shrink-0" />
                {{ item.title }}
            </Link>
        </div>

        <div class="glass-panel rounded-2xl p-3">
            <p class="text-muted-foreground px-1 pb-2 text-[11px] font-semibold tracking-wide uppercase">
                Circles (Groups)
            </p>
            <Link
                v-for="circle in circles"
                :key="circle.title"
                :href="circle.href"
                class="hover:bg-accent flex cursor-pointer items-center gap-3 rounded-xl px-2 py-2 text-sm"
            >
                <span :class="circle.color" class="size-6 rounded-full" />
                <span class="font-medium">{{ circle.title }}</span>
            </Link>
        </div>

        <div
            class="glass-panel relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/20 to-transparent p-4"
        >
            <div class="bg-primary/30 absolute -top-8 -right-8 size-24 rounded-full blur-2xl" />
            <p class="relative text-sm font-semibold">{{ page.props.name }} Pro</p>
            <p class="text-muted-foreground relative mt-1 text-xs leading-relaxed">
                Unlock AI matching, featured drops, and deal alerts.
            </p>
            <Button
                as-child
                class="relative mt-3 w-full rounded-full"
            >
                <Link href="/wallet">Upgrade Now</Link>
            </Button>
        </div>
    </aside>
</template>

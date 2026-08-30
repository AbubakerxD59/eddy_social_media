<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, CreditCard, MessageSquare, Users } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        title: 'A feed for operators',
        body: 'Share a signal: a quote, photo carousel, video, or a link preview. Built for founders, not a general audience.',
        icon: MessageSquare,
    },
    {
        title: 'Mentoring sessions',
        body: 'Mentors list themselves. Live bookings will use Google Calendar and Meet.',
        icon: Calendar,
    },
    {
        title: 'Paid time',
        body: 'Session payments will go through Stripe. The booking record stays in Laravel.',
        icon: CreditCard,
    },
    {
        title: 'A network that stays small',
        body: 'Follow people you would actually take a meeting with.',
        icon: Users,
    },
];
</script>

<template>
    <Head title="Eddy" />

    <Toaster />

    <div class="bg-background min-h-screen">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-7 fill-current" />
                Eddy
            </div>
            <nav class="flex items-center gap-3 text-sm">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-md border px-4 py-1.5"
                >
                    Open feed
                </Link>
                <template v-else>
                    <Link :href="login()" class="px-3 py-1.5">Log in</Link>
                    <Link
                        :href="register()"
                        class="bg-primary text-primary-foreground rounded-md px-4 py-1.5"
                    >
                        Create account
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-16">
            <p class="text-muted-foreground text-sm font-medium tracking-wide uppercase">
                Social for businesses
            </p>
            <h1 class="mt-3 max-w-2xl text-4xl font-semibold tracking-tight md:text-5xl">
                A quieter network for founders and operators.
            </h1>
            <p class="text-muted-foreground mt-4 max-w-xl text-lg">
                Share what you are building. Book a mentor. Keep the noise out.
            </p>
            <div class="mt-8 flex gap-3">
                <Link
                    :href="register()"
                    class="bg-primary text-primary-foreground rounded-md px-5 py-2.5 text-sm font-medium"
                >
                    Join Eddy
                </Link>
                <Link :href="login()" class="rounded-md border px-5 py-2.5 text-sm">
                    Sign in
                </Link>
            </div>

            <div class="mt-16 grid gap-4 md:grid-cols-2">
                <article
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border p-5"
                >
                    <component :is="feature.icon" class="mb-3 size-5" />
                    <h2 class="font-medium">{{ feature.title }}</h2>
                    <p class="text-muted-foreground mt-1 text-sm leading-relaxed">
                        {{ feature.body }}
                    </p>
                </article>
            </div>
        </main>
    </div>
</template>

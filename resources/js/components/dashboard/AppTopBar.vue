<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, MessageSquare, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import ViewerLocationPicker from '@/components/dashboard/ViewerLocationPicker.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { notifyError } from '@/lib/notify';
import { dashboard } from '@/routes';

const page = usePage();
const user = computed(() => page.props.auth.user);
const name = computed(() => page.props.name);
const query = ref('');

const search = () => {
    notifyError('Search is coming soon.');
};

const create = () => {
    router.visit('/dashboard?compose=drop');
};
</script>

<template>
    <header
        class="glass-panel fixed inset-x-0 top-0 z-40 border-x-0 border-t-0 rounded-none"
    >
        <div class="mx-auto flex h-16 max-w-[1600px] items-center gap-4 px-4 lg:px-5">
            <Link :href="dashboard()" class="flex shrink-0 items-center gap-2">
                <span
                    class="bg-primary flex size-8 items-center justify-center rounded-full text-sm font-bold text-white shadow-[0_0_18px_hsl(252_56%_57%/0.45)]"
                >
                    E
                </span>
                <span class="text-[17px] font-semibold tracking-tight">{{ name }}</span>
            </Link>

            <form
                class="mx-auto hidden min-w-0 max-w-xl flex-1 md:block"
                @submit.prevent="search"
            >
                <label class="relative block">
                    <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Search talent, businesses, or skills..."
                        class="bg-background/70 border-border focus:border-primary/50 focus:ring-primary/20 h-10 w-full rounded-full border pr-16 pl-10 text-sm outline-none focus:ring-2"
                    />
                    <kbd
                        class="text-muted-foreground bg-muted pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                    >
                        ⌘K
                    </kbd>
                </label>
            </form>

            <div class="ml-auto flex items-center gap-2">
                <ViewerLocationPicker v-if="user" />

                <Button
                    type="button"
                    class="from-need to-amber-500 text-neutral-950 hidden rounded-full bg-gradient-to-r px-4 font-semibold shadow-[0_8px_24px_hsl(37_97%_55%/0.28)] sm:inline-flex"
                    @click="create"
                >
                    <Plus class="size-4" />
                    Create
                </Button>

                <Button
                    as-child
                    variant="ghost"
                    size="icon"
                    class="relative rounded-full"
                >
                    <Link href="/messages" aria-label="Messages">
                        <MessageSquare class="size-5" />
                        <span class="bg-destructive absolute top-1.5 right-1.5 size-2 rounded-full" />
                    </Link>
                </Button>

                <Button
                    as-child
                    variant="ghost"
                    size="icon"
                    class="relative rounded-full"
                >
                    <Link href="/notifications" aria-label="Notifications">
                        <Bell class="size-5" />
                        <span class="bg-destructive absolute top-1.5 right-1.5 size-2 rounded-full" />
                    </Link>
                </Button>

                <DropdownMenu v-if="user">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-full py-1 pr-2 pl-1"
                        >
                            <Avatar class="size-8">
                                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                <AvatarFallback class="bg-primary/20 text-primary text-xs">
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="hidden text-left leading-tight lg:block">
                                <span class="block max-w-[9rem] truncate text-[13px] font-semibold">
                                    {{ user.name }}
                                </span>
                                <span class="text-muted-foreground block max-w-[9rem] truncate text-[11px]">
                                    {{ user.headline || `@${user.username}` }}
                                </span>
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>

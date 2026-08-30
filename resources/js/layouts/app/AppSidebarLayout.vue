<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import NewSignalDock from '@/components/NewSignalDock.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-h-0 min-w-0 overflow-hidden">
            <div class="flex h-svh min-h-0 flex-col">
                <div class="shrink-0 px-4 pt-3 md:hidden">
                    <SidebarTrigger />
                </div>
                <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                    <slot />
                </div>
            </div>
        </AppContent>
        <NewSignalDock v-if="$page.props.auth.user" />
        <Toaster />
    </AppShell>
</template>

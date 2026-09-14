<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { notifyFormError, notifySuccess } from '@/lib/notify';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify your email',
        description: 'Confirm your email address to open your dashboard.',
    },
});

defineProps<{
    status?: string;
}>();

const email = computed(() => usePage().props.auth.user?.email);
</script>

<template>
    <Head title="Verify your email" />

    <div class="space-y-6 text-center">
        <div
            v-if="status === 'verification-link-sent'"
            class="text-sm font-medium text-green-600"
        >
            A new verification link has been sent to {{ email }}.
        </div>

        <p class="text-muted-foreground text-sm leading-relaxed">
            We sent a verification link to
            <span class="text-foreground font-medium">{{ email }}</span>.
            Open that email and click the link before you can use the dashboard.
        </p>
        <p class="text-muted-foreground text-sm leading-relaxed">
            Did not get it, or did you delete or misplace the email? Send a new link.
        </p>

        <Form
            v-bind="send.form()"
            class="space-y-6"
            v-slot="{ processing }"
            @success="notifySuccess('Verification email sent.')"
            @error="notifyFormError($event)"
        >
            <Button type="submit" class="w-full" :loading="processing">
                Send verification link again
            </Button>

            <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
                Log out
            </TextLink>
        </Form>
    </div>
</template>

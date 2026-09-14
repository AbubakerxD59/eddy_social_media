<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { notifyFormError, notifySuccess } from '@/lib/notify';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

const props = defineProps<{
    status?: string;
    email?: string | null;
}>();

const email = computed(() => props.email || usePage().props.auth.user?.email || null);

watchEffect(() => {
    setLayoutProps({
        title: 'Verify your email',
        description: email.value
            ? `We sent a verification link to ${email.value}. Click that link to open your dashboard.`
            : 'Confirm your email address to open your dashboard.',
    });
});
</script>

<template>
    <Head title="Verify your email" />

    <div class="space-y-6 text-center">
        <div
            v-if="status === 'verification-link-sent'"
            class="text-sm font-medium text-green-600"
        >
            A new verification link has been sent to
            <span class="break-all">{{ email }}</span>.
        </div>

        <p
            v-if="email"
            class="text-foreground text-sm font-medium break-all"
        >
            {{ email }}
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

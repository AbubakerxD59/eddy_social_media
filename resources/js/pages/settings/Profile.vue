<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { uploadProfilePhoto } from '@/lib/profilePhoto';
import { send } from '@/routes/verification';

const page = usePage();
const user = computed(() => page.props.auth.user);
const uploading = ref<'avatar' | 'cover' | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
const coverInput = ref<HTMLInputElement | null>(null);

const pickPhoto = async (kind: 'avatar' | 'cover', event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    uploading.value = kind;
    await uploadProfilePhoto(kind, file);
    uploading.value = null;
};
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="How people see you on Eddy"
        />

        <div class="space-y-3">
            <div class="relative overflow-hidden rounded-xl">
                <img
                    v-if="user.cover"
                    :src="user.cover"
                    alt="Cover photo"
                    class="h-40 w-full object-cover"
                />
                <div
                    v-else
                    class="from-primary/70 via-primary/40 to-need/50 h-40 w-full bg-gradient-to-br"
                />
                <input
                    ref="coverInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="hidden"
                    @change="pickPhoto('cover', $event)"
                />
                <Button
                    type="button"
                    size="sm"
                    class="absolute right-3 bottom-3 rounded-lg bg-black/55 text-white hover:bg-black/70"
                    :loading="uploading === 'cover'"
                    @click="coverInput?.click()"
                >
                    <Camera class="size-4" />
                    Cover photo
                </Button>
            </div>
            <div class="flex items-end gap-3">
                <div class="relative -mt-10">
                    <Avatar class="border-background size-24 border-4 shadow-md">
                        <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                        <AvatarFallback class="text-xl">
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <input
                        ref="avatarInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        class="hidden"
                        @change="pickPhoto('avatar', $event)"
                    />
                    <Button
                        type="button"
                        size="icon"
                        class="absolute right-0 bottom-0 rounded-full"
                        :loading="uploading === 'avatar'"
                        aria-label="Update profile picture"
                        @click="avatarInput?.click()"
                    >
                        <Camera class="size-4" />
                    </Button>
                </div>
                <p class="text-muted-foreground pb-1 text-sm">
                    Use a square photo for your profile and a wide image for the cover.
                </p>
            </div>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ user.type === 'business' ? 'Business name' : 'Name' }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.type === 'business' ? (user.business_name || user.name) : user.name"
                    required
                    :autocomplete="user.type === 'business' ? 'organization' : 'name'"
                    :placeholder="user.type === 'business' ? 'Business name' : 'Your name'"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    class="mt-1 block w-full"
                    name="username"
                    :default-value="user.username"
                    required
                    autocomplete="username"
                    placeholder="yourhandle"
                />
                <InputError class="mt-2" :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="headline">Headline</Label>
                <Input
                    id="headline"
                    class="mt-1 block w-full"
                    name="headline"
                    :default-value="user.headline ?? ''"
                    placeholder="Founder at …"
                />
                <InputError class="mt-2" :message="errors.headline" />
            </div>

            <div class="grid gap-2">
                <Label for="bio">Bio</Label>
                <textarea
                    id="bio"
                    name="bio"
                    rows="4"
                    class="border-input mt-1 block w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                    :default-value="user.bio ?? ''"
                    placeholder="What you build and who you help"
                />
                <InputError class="mt-2" :message="errors.bio" />
            </div>

            <div class="grid gap-2">
                <Label for="website">Website</Label>
                <Input
                    id="website"
                    type="url"
                    class="mt-1 block w-full"
                    name="website"
                    :default-value="user.website ?? ''"
                    placeholder="https://"
                />
                <InputError class="mt-2" :message="errors.website" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="email"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="text-muted-foreground -mt-4 text-sm">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :loading="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>

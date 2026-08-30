<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordStrength from '@/components/PasswordStrength.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { notifySuccess } from '@/lib/notify';
import { login } from '@/routes';
import { store } from '@/routes/register';

const password = ref('');
const passwordConfirmation = ref('');

const { countryCodes, fiscalYears } = defineProps<{
    passwordRules: string;
    countryCodes: { code: string; label: string }[];
    fiscalYears: number[];
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
        wide: true,
    },
});

const selectClass =
    'border-input dark:bg-input/30 h-9 w-full cursor-pointer rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @success="notifySuccess('Account created.')"
    >
        <div class="grid gap-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="full_name">Full name</Label>
                    <Input
                        id="full_name"
                        type="text"
                        required
                        autofocus
                        autocomplete="given-name"
                        name="full_name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.full_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">Last name</Label>
                    <Input
                        id="last_name"
                        type="text"
                        required
                        autocomplete="family-name"
                        name="last_name"
                        placeholder="Last name"
                    />
                    <InputError :message="errors.last_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone_number">Phone number</Label>
                <div class="grid grid-cols-[7.5rem_1fr] gap-2">
                    <select
                        id="phone_country_code"
                        name="phone_country_code"
                        required
                        :class="selectClass"
                        aria-label="Country code"
                    >
                        <option
                            v-for="option in countryCodes"
                            :key="option.label"
                            :value="option.code"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <Input
                        id="phone_number"
                        type="tel"
                        required
                        autocomplete="tel-national"
                        name="phone_number"
                        inputmode="numeric"
                        placeholder="Phone number"
                    />
                </div>
                <InputError :message="errors.phone_country_code || errors.phone_number" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="gender">Gender</Label>
                    <select
                        id="gender"
                        name="gender"
                        required
                        :class="selectClass"
                    >
                        <option value="" disabled selected>Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                    </select>
                    <InputError :message="errors.gender" />
                </div>

                <div class="grid gap-2">
                    <Label for="date_of_birth">Date of birth</Label>
                    <Input
                        id="date_of_birth"
                        type="date"
                        required
                        autocomplete="bday"
                        name="date_of_birth"
                    />
                    <InputError :message="errors.date_of_birth" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="business_name">Business name</Label>
                <Input
                    id="business_name"
                    type="text"
                    required
                    autocomplete="organization"
                    name="business_name"
                    placeholder="Business name"
                />
                <InputError :message="errors.business_name" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="fiscal_year">Most recent complete fiscal year</Label>
                    <select
                        id="fiscal_year"
                        name="fiscal_year"
                        required
                        :class="selectClass"
                    >
                        <option value="" disabled selected>Select year</option>
                        <option
                            v-for="year in fiscalYears"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                    <InputError :message="errors.fiscal_year" />
                </div>

                <div class="grid gap-2">
                    <Label for="full_time_employees">Number of full employees</Label>
                    <Input
                        id="full_time_employees"
                        type="number"
                        required
                        min="0"
                        step="1"
                        name="full_time_employees"
                        placeholder="0"
                    />
                    <InputError :message="errors.full_time_employees" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    v-model="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
                <PasswordStrength
                    :password="password"
                    :confirmation="passwordConfirmation"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="passwordConfirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :loading="processing"
                data-test="register-user-button"
            >
                Create account
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
            >Log in</TextLink>
        </div>
    </Form>
</template>

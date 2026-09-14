<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, Compass, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import CountryCodeSelect from '@/components/CountryCodeSelect.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordStrength from '@/components/PasswordStrength.vue';
import TalentFields from '@/components/TalentFields.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { notifyFormError, notifySuccess } from '@/lib/notify';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { UserType } from '@/types/auth';

const password = ref('');
const passwordConfirmation = ref('');

const maxDateOfBirth = (() => {
    const date = new Date();
    date.setDate(date.getDate() - 1);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
})();

const { countryCodes, fiscalYears, accountType: initialAccountType } = defineProps<{
    passwordRules: string;
    countryCodes: { code: string; name: string; label: string }[];
    fiscalYears: number[];
    accountType?: UserType | null;
}>();

const accountType = ref<UserType>(initialAccountType ?? 'business');

const accountTypes: { value: UserType; title: string; description: string; icon: typeof Building2 }[] = [
    {
        value: 'business',
        title: 'Business',
        description: 'Hire, partner, and post needs for your company.',
        icon: Building2,
    },
    {
        value: 'talent',
        title: 'Talent',
        description: 'Offer freelance or professional services.',
        icon: Sparkles,
    },
    {
        value: 'explorer',
        title: 'Explorer',
        description: 'Browse every post and join the conversation as yourself.',
        icon: Compass,
    },
];

const layoutCopy = computed(() => {
    switch (accountType.value) {
        case 'talent':
            return {
                title: 'Join as talent',
                description: 'Create your personal account and tell businesses what you offer.',
            };
        case 'explorer':
            return {
                title: 'Join as an explorer',
                description: 'Create a personal account to view and interact with every kind of post.',
            };
        default:
            return {
                title: 'Create a business account',
                description: 'Enter your details below to create your account',
            };
    }
});

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Choose how you want to use Eddy',
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
        @success="notifySuccess('Account created. Check your email to verify.')"
        @error="notifyFormError($event)"
    >
        <input type="hidden" name="type" :value="accountType" />

        <div class="grid gap-2">
            <p class="text-sm font-medium">I am joining as</p>
            <div class="grid gap-2 sm:grid-cols-3">
                <button
                    v-for="option in accountTypes"
                    :key="option.value"
                    type="button"
                    class="flex cursor-pointer flex-col items-start gap-2 rounded-xl border px-3 py-3 text-left transition-colors"
                    :class="
                        accountType === option.value
                            ? 'border-primary bg-primary/10'
                            : 'hover:bg-accent/50'
                    "
                    @click="accountType = option.value"
                >
                    <component :is="option.icon" class="size-4" />
                    <span class="text-sm font-semibold">{{ option.title }}</span>
                    <span class="text-muted-foreground text-[11px] leading-snug">
                        {{ option.description }}
                    </span>
                </button>
            </div>
            <InputError :message="errors.type" />
            <p class="text-muted-foreground text-xs">{{ layoutCopy.description }}</p>
        </div>

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
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <CountryCodeSelect :options="countryCodes" />
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
                        min="1900-01-01"
                        :max="maxDateOfBirth"
                        placeholder="Select date of birth"
                    />
                    <InputError :message="errors.date_of_birth" />
                </div>
            </div>

            <div v-if="accountType === 'business'" class="grid gap-2">
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

            <div v-if="accountType === 'business'" class="grid gap-5 sm:grid-cols-2">
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

            <TalentFields
                v-if="accountType === 'talent'"
                prefix="talent_"
                :errors="errors"
            />

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

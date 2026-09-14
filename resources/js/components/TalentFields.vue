<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import { Label } from '@/components/ui/label';

const TALENT_BIO_MAX = 1200;

const props = defineProps<{
    prefix?: string;
    headline?: string;
    bio?: string;
    skills?: string;
    hourlyRate?: string | number;
    errors?: Record<string, string | undefined>;
}>();

const prefix = computed(() => props.prefix ?? '');
const nameFor = (field: string) => `${prefix.value}${field}`;

const bio = ref(props.bio ?? '');
const draftSkill = ref('');
const tags = ref(
    (props.skills ?? '')
        .split(',')
        .map((skill) => skill.trim())
        .filter(Boolean),
);

const skillsValue = computed(() => tags.value.join(', '));
const bioCount = computed(() => bio.value.length);

const addSkill = (raw: string) => {
    const skill = raw.trim().replace(/,$/u, '').trim();

    if (skill === '') {
        return;
    }

    if (tags.value.some((tag) => tag.toLowerCase() === skill.toLowerCase())) {
        return;
    }

    tags.value = [...tags.value, skill];
};

const commitDraft = () => {
    addSkill(draftSkill.value);
    draftSkill.value = '';
};

const consumeCommaSeparated = (value: string) => {
    if (!value.includes(',')) {
        draftSkill.value = value;

        return;
    }

    const parts = value.split(',');
    const rest = parts.pop() ?? '';

    for (const part of parts) {
        addSkill(part);
    }

    draftSkill.value = rest.replace(/^\s+/u, '');
};

watch(draftSkill, (value) => {
    if (value.includes(',')) {
        consumeCommaSeparated(value);
    }
});

const onSkillsKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commitDraft();
        return;
    }

    if (event.key === 'Backspace' && draftSkill.value === '' && tags.value.length > 0) {
        event.preventDefault();
        tags.value = tags.value.slice(0, -1);
    }
};

const onSkillsPaste = (event: ClipboardEvent) => {
    const pasted = event.clipboardData?.getData('text') ?? '';

    if (!pasted.includes(',') && !pasted.includes('\n')) {
        return;
    }

    event.preventDefault();
    consumeCommaSeparated(`${draftSkill.value}${pasted.replace(/\n/g, ',')}`);
};

const removeSkill = (index: number) => {
    tags.value = tags.value.filter((_, tagIndex) => tagIndex !== index);
};

const onBioInput = (event: Event) => {
    const target = event.target as HTMLTextAreaElement;

    if (target.value.length > TALENT_BIO_MAX) {
        target.value = target.value.slice(0, TALENT_BIO_MAX);
    }

    bio.value = target.value;
};

const onBioPaste = (event: ClipboardEvent) => {
    event.preventDefault();

    const pasted = event.clipboardData?.getData('text') ?? '';
    const target = event.target as HTMLTextAreaElement;
    const start = target.selectionStart ?? bio.value.length;
    const end = target.selectionEnd ?? bio.value.length;
    const next = `${bio.value.slice(0, start)}${pasted}${bio.value.slice(end)}`.slice(0, TALENT_BIO_MAX);

    bio.value = next;

    requestAnimationFrame(() => {
        const cursor = Math.min(start + pasted.length, TALENT_BIO_MAX);
        target.setSelectionRange(cursor, cursor);
    });
};
</script>

<template>
    <div class="grid gap-5">
        <div class="grid gap-2">
            <Label :for="nameFor('headline')">What you offer</Label>
            <Input
                :id="nameFor('headline')"
                type="text"
                required
                :name="nameFor('headline')"
                :default-value="headline"
                placeholder="Brand design for early-stage startups"
            />
            <InputError :message="errors?.[nameFor('headline')]" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between gap-3">
                <Label :for="nameFor('bio')">Talent bio</Label>
                <p class="text-muted-foreground text-xs tabular-nums">
                    {{ bioCount }} / {{ TALENT_BIO_MAX }}
                </p>
            </div>
            <textarea
                :id="nameFor('bio')"
                :name="nameFor('bio')"
                required
                rows="5"
                :maxlength="TALENT_BIO_MAX"
                class="border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                :value="bio"
                placeholder="The services you provide and who you work with"
                @input="onBioInput"
                @paste="onBioPaste"
            />
            <InputError :message="errors?.[nameFor('bio')]" />
        </div>

        <div class="grid gap-2">
            <Label :for="nameFor('skills')">Skills</Label>
            <input type="hidden" :name="nameFor('skills')" :value="skillsValue" />
            <div
                class="border-input focus-within:border-ring focus-within:ring-ring/50 flex min-h-9 cursor-text flex-wrap items-center gap-1.5 rounded-md border bg-transparent px-2 py-1.5 focus-within:ring-[3px]"
                @click="($refs.skillInput as HTMLInputElement | undefined)?.focus()"
            >
                <span
                    v-for="(tag, index) in tags"
                    :key="`${tag}-${index}`"
                    class="bg-primary/10 text-foreground inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                >
                    {{ tag }}
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground cursor-pointer"
                        :aria-label="`Remove ${tag}`"
                        @click.stop="removeSkill(index)"
                    >
                        <X class="size-3" />
                    </button>
                </span>
                <input
                    :id="nameFor('skills')"
                    ref="skillInput"
                    v-model="draftSkill"
                    type="text"
                    class="placeholder:text-muted-foreground min-w-[8rem] flex-1 bg-transparent text-sm outline-none"
            :required="tags.length === 0"
            :placeholder="tags.length === 0 ? 'Type a skill and press Enter' : 'Add another skill'"
            @keydown="onSkillsKeydown"
            @paste="onSkillsPaste"
            @blur="commitDraft"
                />
            </div>
            <p class="text-muted-foreground text-xs">
                Press Enter or comma to add a skill as a tag.
            </p>
            <InputError :message="errors?.[nameFor('skills')]" />
        </div>

        <div class="grid gap-2">
            <Label :for="nameFor('hourly_rate')">Hourly rate in USD (optional)</Label>
            <MoneyInput
                :id="nameFor('hourly_rate')"
                type="number"
                min="0"
                step="1"
                :name="nameFor('hourly_rate')"
                :default-value="hourlyRate"
                placeholder="150"
            />
            <InputError :message="errors?.[nameFor('hourly_rate')]" />
        </div>
    </div>
</template>

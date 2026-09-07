<script setup lang="ts">
import { ChevronDown, Search } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

export type CountryCodeOption = {
    code: string;
    name: string;
    label: string;
};

const {
    options,
    id = 'phone_country_code',
    name = 'phone_country_code',
} = defineProps<{
    options: CountryCodeOption[];
    id?: string;
    name?: string;
}>();

const root = ref<HTMLElement | null>(null);
const list = ref<HTMLElement | null>(null);
const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const selectedName = ref('');

const searchable = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const selected = computed(
    () => options.find((option) => option.name === selectedName.value) ?? null,
);

const filtered = computed(() => {
    const needle = searchable(query.value.trim());

    if (needle === '') {
        return options;
    }

    const digits = needle.replace(/^\+/, '');

    return options.filter((option) => {
        return searchable(option.name).includes(needle)
            || searchable(option.label).includes(needle)
            || searchable(option.code).includes(needle)
            || option.code.replace('+', '').includes(digits);
    });
});

const selectCountry = (option: CountryCodeOption) => {
    selectedName.value = option.name;
    query.value = option.label;
    open.value = false;
    activeIndex.value = 0;
};

const restoreQuery = () => {
    const exact = filtered.value.find((option) => {
        const needle = searchable(query.value.trim());

        return searchable(option.label) === needle || searchable(option.name) === needle;
    });

    if (exact) {
        selectCountry(exact);
        return;
    }

    query.value = selected.value?.label ?? '';
};

onClickOutside(root, () => {
    if (!open.value) {
        return;
    }

    open.value = false;
    restoreQuery();
});

watch(query, (value) => {
    activeIndex.value = 0;

    if (value.trim() === '') {
        selectedName.value = '';
    }
});

watch(activeIndex, (index) => {
    const item = list.value?.children[index];

    if (item instanceof HTMLElement) {
        item.scrollIntoView({ block: 'nearest' });
    }
});

const onFocus = (event: FocusEvent) => {
    open.value = true;

    const target = event.target;

    if (target instanceof HTMLInputElement && query.value !== '') {
        target.select();
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (!open.value && ['ArrowDown', 'Enter'].includes(event.key) && filtered.value.length > 0) {
        open.value = true;
    }

    if (!open.value || filtered.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % filtered.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + filtered.value.length) % filtered.value.length;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const option = filtered.value[activeIndex.value];

        if (option) {
            selectCountry(option);
        }

        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
        restoreQuery();
    }
};
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <input
            :id="id"
            type="hidden"
            :name="name"
            :value="selected?.code ?? ''"
        >
        <div class="relative">
            <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
            <Input
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                :required="!selected"
                :aria-controls="`${id}-listbox`"
                :aria-expanded="open"
                aria-autocomplete="list"
                aria-label="Country"
                placeholder="Search country"
                class="h-9 pr-9 pl-9"
                @focus="onFocus"
                @keydown="onKeydown"
            />
            <ChevronDown class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 opacity-50" />
        </div>

        <ul
            v-if="open"
            :id="`${id}-listbox`"
            ref="list"
            class="glass-popup absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border p-1"
            role="listbox"
        >
            <li
                v-if="filtered.length === 0"
                class="text-muted-foreground px-3 py-2 text-sm"
            >
                No countries found
            </li>
            <li
                v-for="(option, index) in filtered"
                :key="option.name"
                role="option"
                :aria-selected="option.name === selected?.name"
                class="cursor-pointer rounded-lg px-3 py-2 text-sm"
                :class="index === activeIndex ? 'bg-accent' : 'hover:bg-accent/70'"
                @mousedown.prevent="selectCountry(option)"
                @mouseenter="activeIndex = index"
            >
                <span class="block truncate">{{ option.name }}</span>
                <span class="text-muted-foreground text-xs">{{ option.code }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import type { Instance } from 'flatpickr/dist/types/instance';
import { onBeforeUnmount, onMounted, ref, useAttrs, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        required?: boolean | '';
        disabled?: boolean;
        placeholder?: string;
        autocomplete?: string;
        defaultValue?: string | number;
        modelValue?: string | number;
        class?: HTMLAttributes['class'];
        min?: string;
        max?: string;
        enableTime?: boolean;
    }>(),
    {
        placeholder: 'Select date',
        enableTime: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const attrs = useAttrs();
const input = ref<HTMLInputElement | null>(null);
let picker: Instance | null = null;
let cancelled = false;

const inputClass = cn(
    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
    'cursor-pointer pr-9',
    props.disabled && 'cursor-not-allowed opacity-50',
    props.class,
);

const currentValue = () => String(props.modelValue ?? props.defaultValue ?? '');

const applyAltInput = (instance: Instance) => {
    if (!instance.altInput) {
        return;
    }

    instance.altInput.className = inputClass;
    instance.altInput.placeholder = props.placeholder ?? 'Select date';
    instance.altInput.disabled = Boolean(props.disabled);
    instance.altInput.required = props.required === true || props.required === '';
    instance.altInput.autocomplete = props.autocomplete ?? 'off';
    instance.altInput.setAttribute('data-slot', 'input');
    instance.input.required = false;

    if (props.id) {
        instance.altInput.id = props.id;
        instance.input.removeAttribute('id');
    }

    for (const key of Object.keys(attrs)) {
        if (key === 'class' || key === 'type' || key === 'name' || key === 'id' || key.startsWith('on')) {
            continue;
        }

        const value = attrs[key];

        if (value === false || value == null) {
            continue;
        }

        instance.altInput.setAttribute(key, value === true ? '' : String(value));
    }
};

const dateFormat = () => (props.enableTime ? 'Y-m-d H:i' : 'Y-m-d');
const altFormat = () => (props.enableTime ? 'F j, Y at h:i K' : 'F j, Y');

onMounted(async () => {
    if (!input.value) {
        return;
    }

    const { default: flatpickr } = await import('flatpickr');

    if (cancelled || !input.value) {
        return;
    }

    input.value.value = currentValue();

    picker = flatpickr(input.value, {
        dateFormat: dateFormat(),
        altInput: true,
        altFormat: altFormat(),
        allowInput: true,
        disableMobile: true,
        clickOpens: true,
        minDate: props.min,
        maxDate: props.max,
        defaultDate: currentValue() || undefined,
        enableTime: props.enableTime,
        time_24hr: false,
        monthSelectorType: 'dropdown',
        onChange: (_dates, dateStr) => {
            emit('update:modelValue', dateStr);
        },
        onReady: (_dates, _str, instance) => {
            applyAltInput(instance);
            instance.calendarContainer.classList.add('glass-popup');
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        const next = value == null || value === '' ? '' : String(value);

        if (!picker) {
            return;
        }

        if (picker.input.value === next) {
            return;
        }

        picker.setDate(next, false);
    },
);

watch(
    () => [props.min, props.max, props.disabled] as const,
    ([min, max, disabled]) => {
        picker?.set('minDate', min);
        picker?.set('maxDate', max);
        picker?.set('clickOpens', !disabled);

        if (picker?.altInput) {
            picker.altInput.disabled = Boolean(disabled);
        }
    },
);

onBeforeUnmount(() => {
    cancelled = true;
    picker?.destroy();
    picker = null;
});
</script>

<template>
    <div class="relative">
        <input
            ref="input"
            :name="name"
            :disabled="disabled"
            type="text"
            class="hidden"
            tabindex="-1"
            autocomplete="off"
        />
        <Calendar
            class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 z-10 size-4 -translate-y-1/2"
        />
    </div>
</template>

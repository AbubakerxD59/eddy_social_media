<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed, useAttrs } from "vue"
import { useVModel } from "@vueuse/core"
import DatePicker from "@/components/DatePicker.vue"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
  type?: string
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const attrs = useAttrs()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const isDateInput = computed(() => {
  const type = props.type ?? (typeof attrs.type === "string" ? attrs.type : undefined)

  return type === "date" || type === "datetime-local"
})
</script>

<template>
  <DatePicker
    v-if="isDateInput"
    v-model="modelValue"
    :default-value="props.defaultValue"
    :class="props.class"
    :enable-time="(props.type ?? attrs.type) === 'datetime-local'"
    v-bind="$attrs"
  />
  <input
    v-else
    v-model="modelValue"
    data-slot="input"
    v-bind="$attrs"
    :type="props.type"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  >
</template>

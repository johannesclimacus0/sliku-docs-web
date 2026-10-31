<script setup lang="ts">
import { computed, type InputTypeHTMLAttribute } from 'vue'
defineOptions({ inheritAttrs: false })
const props = withDefaults(
    defineProps<{
        id: string
        label: string
        modelValue: string
        type?: InputTypeHTMLAttribute
        multiline?: boolean
    }>(),
    { type: 'text' },
)
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const value = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
})
</script>
<template>
    <div class="mb-5 block">
        <div
            v-if="$slots.labelAction"
            class="flex items-baseline justify-between gap-3 [&_a]:text-xs [&_a]:underline [&_a]:underline-offset-[3px]">
            <label :for="id" class="mb-[7px] block text-[13px]">{{ label }}</label
            ><slot name="labelAction" />
        </div>
        <label v-else :for="id" class="mb-[7px] block text-[13px]">{{ label }}</label>
        <textarea
            v-if="multiline"
            v-bind="$attrs"
            :id="id"
            v-model="value"
            class="block min-h-[105px] w-full resize-y rounded border border-control bg-white px-[11px] py-[9px] disabled:bg-[#f5f5f5]" />
        <input
            v-else
            v-bind="$attrs"
            :id="id"
            :type="type"
            v-model="value"
            class="block w-full rounded border border-control bg-white px-[11px] py-[9px] disabled:bg-[#f5f5f5]" />
        <small v-if="$slots.hint" class="mt-[5px] block text-right text-[11px] text-muted"><slot name="hint" /></small>
    </div>
</template>

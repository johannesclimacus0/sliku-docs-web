<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset'
        variant?: 'default' | 'primary' | 'secondary' | 'text' | 'icon'
        disabled?: boolean
        busy?: boolean
        compact?: boolean
        fullWidth?: boolean
    }>(),
    { type: 'button', variant: 'default' },
)
const emit = defineEmits<{ click: [event: MouseEvent] }>()
</script>
<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-2 text-center text-[13px]"
        :class="[
            variant === 'text' || variant === 'icon'
                ? 'border-0 bg-transparent px-0 py-[5px] text-[#555] hover:text-[#111] hover:underline'
                : [
                      'min-h-[34px] rounded border hover:no-underline',
                      compact ? 'px-[10px] py-[5px]' : 'px-3 py-[7px]',
                      variant === 'primary'
                          ? 'border-[#333] bg-[#333] text-white hover:border-[#1d1d1d] hover:bg-[#1d1d1d]'
                          : 'border-[#d4d4d4] bg-white text-[#333] hover:border-[#b8b8b8] hover:bg-[#f5f5f5]',
                  ],
            { 'w-full': fullWidth },
        ]"
        :disabled="disabled || busy"
        @click="emit('click', $event)">
        <slot />
    </button>
</template>

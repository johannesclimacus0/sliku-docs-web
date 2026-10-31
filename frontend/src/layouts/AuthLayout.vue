<script setup lang="ts">
defineProps<{ title: string }>()
</script>
<template>
    <div class="grid min-h-dvh place-items-center p-6">
        <main class="w-full max-w-[360px] [&>h1]:mb-8">
            <h1>{{ title }}</h1>
            <div class="[&_form>button]:w-full"><slot /></div>
            <div class="mt-7 text-[13px] text-muted [&_a]:underline [&_a]:underline-offset-[3px] [&_a+a]:ml-3">
                <slot name="footer" />
            </div>
        </main>
    </div>
</template>

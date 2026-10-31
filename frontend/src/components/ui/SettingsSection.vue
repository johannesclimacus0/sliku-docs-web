<script setup lang="ts">
defineProps<{ title: string }>()
</script>
<template>
    <section
        class="grid grid-cols-[180px_minmax(0,560px)] gap-[30px] border-b border-line pt-7 pb-8 first-of-type:pt-0 [&>h2]:mt-[6px] [&>h2]:mb-0 max-[1050px]:grid-cols-[130px_minmax(0,1fr)] max-[1050px]:gap-6 max-[760px]:grid-cols-1 max-[760px]:gap-5">
        <h2>{{ title }}</h2>
        <div class="min-w-0"><slot /></div>
    </section>
</template>

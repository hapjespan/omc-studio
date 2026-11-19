<script setup lang="ts">
import LogoMark from '../../components/ui/LogoMark.vue';

defineProps<{ title: string }>();
</script>

<template>
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="flex w-full max-w-[400px] flex-col gap-4 rounded-panel border border-hairline bg-white p-8">
            <div class="flex items-center gap-2.5">
                <LogoMark />
                <span class="font-display text-[17px] font-bold">OMC Studio</span>
            </div>
            <h1 class="font-display text-[22px] font-semibold text-ink">{{ title }}</h1>
            <slot />
        </div>
    </main>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { t } from '../../i18n';
import LogoMark from '../../components/ui/LogoMark.vue';

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="flex w-full max-w-[400px] flex-col gap-4 rounded-panel border border-hairline bg-white p-8">
            <div class="flex items-center gap-2.5">
                <LogoMark />
                <span class="font-display text-[17px] font-bold">{{ t('app.title') }}</span>
            </div>
            <h1 class="font-display text-[22px] font-semibold text-ink">{{ title }}</h1>
            <slot />
        </div>
    </main>
</template>

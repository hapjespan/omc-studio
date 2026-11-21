<script setup lang="ts">
import { t } from '../../i18n';
import Wordmark from '../../components/ui/Wordmark.vue';

defineProps<{ title: string }>();
</script>

<template>
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="flex w-full max-w-[400px] flex-col gap-4 rounded-panel border border-hairline bg-white p-8">
            <Wordmark :name="t('app.title')" />
            <h1 class="font-display text-[22px] font-semibold text-ink">{{ title }}</h1>
            <slot />
        </div>
    </main>
</template>

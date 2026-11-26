<script setup lang="ts">
import { onMounted } from 'vue';
import { announce } from '../components/feedback/status';
import { t } from '../i18n';

const props = defineProps<{ name: string; date: string; year: number }>();
const text = t('snap.banner', { name: props.name, date: props.date });
onMounted(() => announce(text));
</script>

<template>
    <section role="region" :aria-label="t('snap.banner_label')" class="mt-2 rounded-card border border-hairline bg-surface px-4 py-2.5 text-[13px]" data-snapshot-banner>
        <p class="font-display text-[14px] font-semibold">{{ text }}</p>
        <p class="text-[12.5px] text-secondary">{{ t('snap.banner_year', { year }) }}</p>
        <slot />
    </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { announce } from '../components/feedback/status';
import { t } from '../i18n';

const props = defineProps<{ name: string; date: string }>();
const text = t('snap.banner', { name: props.name, date: props.date });
onMounted(() => announce(text));
</script>

<template>
    <section role="region" :aria-label="t('snap.banner_label')" class="mt-2 rounded-md border border-[#848B95] bg-[#EEF1F5] px-3 py-2 text-[13px]" data-snapshot-banner>
        <p class="font-semibold">{{ text }}</p>
        <slot />
    </section>
</template>

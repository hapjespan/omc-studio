<script setup lang="ts">
import { t } from '../i18n';

defineProps<{ colour: string }>();
</script>

<template>
    <span class="absolute right-2 top-1 text-[11px] font-semibold" :style="{ color: colour }" data-system-mark>{{ t('view.system') }}</span>
</template>

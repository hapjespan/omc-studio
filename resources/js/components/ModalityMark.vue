<script setup lang="ts">
import { t } from '../i18n';
import { colourOf } from './modality';

defineProps<{ letter: string }>();
</script>

<template>
    <span data-testid="modality-mark" class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-badge text-[11px] font-bold leading-none text-white" :style="{ backgroundColor: colourOf(letter) }"><span class="sr-only">{{ t('entry.modality') }}</span>{{ letter }}</span>
</template>

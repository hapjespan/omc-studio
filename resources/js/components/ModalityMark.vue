<script setup lang="ts">
import { t } from '../i18n';
import { colourOf } from './modality';

defineProps<{ letter: string }>();
</script>

<template>
    <span data-testid="modality-mark" class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[12px] font-bold text-white outline outline-2 outline-[#1B1F27]" :style="{ backgroundColor: colourOf(letter), boxShadow: '0 0 0 4px #FFFFFF' }"><span class="sr-only">{{ t('entry.modality') }}</span>{{ letter }}</span>
</template>

<script lang="ts">
export const MODALITY_MARK_CLASS = 'h-4 w-4 shrink-0 items-center justify-center rounded-badge text-[11px] font-bold leading-none text-white';
</script>

<script setup lang="ts">
import { t } from '../i18n';
import { colourOf } from './modality';

defineProps<{ letter: string; colour?: string; decorative?: boolean }>();
</script>

<template>
    <span :data-testid="decorative ? undefined : 'modality-mark'" :class="['inline-flex', MODALITY_MARK_CLASS]" :style="{ backgroundColor: colour ?? colourOf(letter) }" :aria-hidden="decorative ? 'true' : undefined"><span v-if="!decorative" class="sr-only">{{ t('entry.modality') }}</span>{{ letter }}</span>
</template>

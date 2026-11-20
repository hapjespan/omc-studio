<script setup lang="ts">
import '../../css/canvas.css';
import { t } from '../i18n';
import { ARTBOARD } from '../layout';

defineProps<{ height: number }>();
</script>

<template>
    <div v-if="height > ARTBOARD.height" class="artboard-overflow absolute left-0 z-0" :style="{ top: `${ARTBOARD.height}px`, width: `${ARTBOARD.width}px`, height: `${height - ARTBOARD.height}px` }" data-overflow-area aria-hidden="true" />
    <div class="pointer-events-none absolute left-0 z-[50] border-t border-ink" :style="{ top: `${ARTBOARD.height - 1}px`, width: `${ARTBOARD.width}px` }" data-artboard-edge>
        <span class="absolute right-2 -top-6 rounded-badge bg-white px-2 py-0.5 text-[11px] text-muted">{{ t('view.edge') }}</span>
    </div>
</template>

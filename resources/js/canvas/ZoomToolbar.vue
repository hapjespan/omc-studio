<script lang="ts">
export const ZOOM_MIN = 50;
export const ZOOM_MAX = 200;
export const FIT_MIN = 10;
const STEP = 10;

export const zoomIn = (zoom: number): number => Math.min(ZOOM_MAX, Math.floor(zoom / STEP) * STEP + STEP);
export const zoomOut = (zoom: number): number => Math.max(Math.min(zoom, ZOOM_MIN), Math.ceil(zoom / STEP) * STEP - STEP);
export const fitZoom = (viewport: { width: number; height: number }, content: { width: number; height: number }): number =>
    Math.max(FIT_MIN, Math.min(ZOOM_MAX, Math.floor(Math.min(viewport.width / content.width, viewport.height / content.height) * 100)));
</script>

<script setup lang="ts">
import AppButton from '../components/ui/AppButton.vue';
import IconButton from '../components/ui/IconButton.vue';
import { t } from '../i18n';

const zoom = defineModel<number>('zoom', { required: true });
const emit = defineEmits<{ fit: [] }>();
const inert = 'aria-disabled:cursor-not-allowed aria-disabled:border-hairline aria-disabled:bg-disabled aria-disabled:text-muted';
</script>

<template>
    <div role="toolbar" :aria-label="t('view.zoom.toolbar')" class="flex flex-wrap items-center gap-2">
        <IconButton icon="minus" :label="t('view.zoom.out')" :class="inert" :aria-disabled="zoom <= ZOOM_MIN" @click="zoom = zoomOut(zoom)" />
        <output class="min-w-[4ch] text-center text-[13px] font-semibold" data-zoom aria-live="polite">{{ Math.round(zoom) }}%</output>
        <IconButton icon="plus" :label="t('view.zoom.in')" :class="inert" :aria-disabled="zoom >= ZOOM_MAX" @click="zoom = zoomIn(zoom)" />
        <AppButton size="small" @click="emit('fit')">{{ t('view.zoom.fit') }}</AppButton>
    </div>
</template>

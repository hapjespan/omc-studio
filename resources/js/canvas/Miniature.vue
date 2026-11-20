<script setup lang="ts">
import { computed } from 'vue';
import { buildMiniature } from '../layout/miniature';
import type { DrawnCanvas } from '../layout/types';

const props = withDefaults(defineProps<{ canvas: DrawnCanvas; width?: number }>(), { width: 240 });
const picture = computed(() => buildMiniature(props.canvas, props.width));
</script>

<template>
    <svg :width="picture.width" :height="picture.height" :viewBox="`0 0 ${picture.width} ${picture.height}`" aria-hidden="true" focusable="false">
        <rect v-for="rect in picture.rects" :key="rect.id" :x="rect.x" :y="rect.y" :width="rect.width" :height="rect.height" :fill="rect.fill" :fill-opacity="rect.fillOpacity" :stroke="rect.stroke ?? 'none'" :stroke-width="rect.strokeWidth" :stroke-dasharray="rect.dashed ? '3 2' : undefined" />
    </svg>
</template>

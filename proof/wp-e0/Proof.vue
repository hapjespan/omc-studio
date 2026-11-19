<script setup lang="ts">
import { ref } from 'vue';
import { useCanvas, useCanvasInput, provideEditor } from '@open-pencil/vue';
import type { createEditor } from '@open-pencil/core/editor';

const props = defineProps<{ editor: ReturnType<typeof createEditor>; onReady: () => void }>();
const canvas = ref<HTMLCanvasElement | null>(null);
provideEditor(props.editor);
const { hitTestSectionTitle, hitTestComponentLabel, hitTestFrameTitle } = useCanvas(canvas, props.editor, { onReady: props.onReady });
useCanvasInput(canvas, props.editor, hitTestSectionTitle, hitTestComponentLabel, hitTestFrameTitle, () => undefined, () => undefined);
</script>

<template>
    <canvas ref="canvas" id="stage"></canvas>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useCanvas, useCanvasInput, provideEditor } from '@open-pencil/vue';
import type { Editor } from '@open-pencil/core/editor';

const props = defineProps<{ editor: Editor; onReady: () => void }>();
const canvas = ref<HTMLCanvasElement | null>(null);
provideEditor(props.editor);
const { hitTestSectionTitle, hitTestComponentLabel, hitTestFrameTitle } = useCanvas(canvas, props.editor, { onReady: props.onReady, showRulers: false });
useCanvasInput(canvas, props.editor, hitTestSectionTitle, hitTestComponentLabel, hitTestFrameTitle, () => undefined, () => undefined);
</script>

<template>
    <canvas ref="canvas" class="block h-full w-full" data-drawing-surface></canvas>
</template>

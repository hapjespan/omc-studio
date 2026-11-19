<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import CanvasView from '../../canvas/CanvasView.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import { contentHeight } from '../../canvas/extent';
import ZoomToolbar, { fitZoom } from '../../canvas/ZoomToolbar.vue';
import { ARTBOARD } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import { computeCanvasLayout } from '../../layout';
import type { CanvasLayoutInput, TextMeasurer } from '../../layout';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    canWrite: boolean;
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
    modalities: ModalityMark[];
}>();

const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, measurer.value) : null));
const zoom = ref(100);
const scroller = ref<HTMLElement>();
const fit = (): void => {
    if (scroller.value && drawn.value) zoom.value = fitZoom({ width: scroller.value.clientWidth, height: scroller.value.clientHeight }, { width: ARTBOARD.width, height: contentHeight(drawn.value) });
};
</script>

<template>
    <Head :title="canvas.name" />
    <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
    <ZoomToolbar v-if="drawn" v-model:zoom="zoom" class="mt-3" @fit="fit" />
    <div ref="scroller" class="mt-3 max-h-[calc(100vh-14rem)] overflow-auto">
        <CanvasView v-if="drawn" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :zoom="zoom" />
    </div>
</template>

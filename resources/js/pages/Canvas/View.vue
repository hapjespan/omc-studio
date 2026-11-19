<script setup lang="ts">
import { computed, onMounted, shallowRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import CanvasView from '../../canvas/CanvasView.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
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
</script>

<template>
    <Head :title="canvas.name" />
    <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
    <div class="mt-3 overflow-auto">
        <CanvasView v-if="drawn" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" />
    </div>
</template>

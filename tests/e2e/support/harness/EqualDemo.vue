<script setup lang="ts">
import { onMounted, ref } from 'vue';
import CanvasView from '../../../../resources/js/canvas/CanvasView.vue';
import { createDrawingEngine } from '../../../../resources/js/edit-engine';
import { automaticPayload } from '../../../../resources/js/edit-engine/automaticPayload';
import { loadCanvasFonts } from '../../../../resources/js/layout/browserMeasurer';
import type { BlockStyle, DrawnBlock, DrawnCanvas } from '../../../../resources/js/layout/types';

const FAMILIES = ['Inter', 'Space Grotesk'] as const;
const WEIGHTS = [400, 500, 600, 700];
const view = new URLSearchParams(location.search).get('view');
const drawn = ref<DrawnCanvas | null>(null);
const host = ref<HTMLElement>();
const ready = ref(false);

const style = (family: BlockStyle['font']['family'], weight: number): BlockStyle => ({
    fill: '#F6E6CC',
    fillOpacity: 100,
    border: null,
    borderColour: '#DCDFE2',
    borderWidth: 0,
    borderStyle: 'solid',
    alignHorizontal: 'left',
    alignVertical: 'top',
    textColour: '#171B21',
    labelColour: '#171B21',
    font: { family, size: 14, weight, italic: false },
    lineHeight: 20,
    radius: 8,
});

const blocks: DrawnBlock[] = FAMILIES.flatMap((family, familyIndex) =>
    WEIGHTS.map((weight, weightIndex): DrawnBlock => {
        const index = familyIndex * WEIGHTS.length + weightIndex;
        return {
            id: `${family.replace(' ', '-')}-${weight}`,
            kind: 'domain',
            areaKey: 'primary_activities',
            parentId: null,
            x: 20 + (index % 2) * 340,
            y: 200 + Math.floor(index / 2) * 90,
            width: 300,
            drawnHeight: 64,
            z: 1000 + index,
            textLines: ['Hamburgefonstiv Onderwijs', 'Gjpqy Rekenen 0123456789'],
            modality: null,
            system: false,
            style: style(family, weight),
        };
    }),
);

onMounted(async () => {
    await loadCanvasFonts();
    const canvas: DrawnCanvas = { blocks, direction: [], warnings: [], beyondArtboardCount: 0 };
    drawn.value = canvas;
    if (view === 'html') {
        ready.value = true;
        return;
    }
    const engine = createDrawingEngine();
    await engine.mount(host.value as HTMLElement, canvas, { layout: automaticPayload([]) });
    engine.zoomTo100();
    Object.assign(window, { equal: { engine, blocks } });
    ready.value = true;
});
</script>

<template>
    <main :data-ready="ready">
        <CanvasView v-if="view === 'html' && drawn" :drawn="drawn" :areas="[]" :elements="[]" :statements="[]" :modalities="[]" />
        <div v-show="view !== 'html'" ref="host" class="h-[1200px] w-[1700px]"></div>
    </main>
</template>

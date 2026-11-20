<script setup lang="ts">
import { onMounted, ref } from 'vue';
import CanvasView from '../../../../resources/js/canvas/CanvasView.vue';
import { computeCanvasLayout } from '../../../../resources/js/layout';
import { createBrowserMeasurer } from '../../../../resources/js/layout/browserMeasurer';
import type { DrawnCanvas, ElementLayout } from '../../../../resources/js/layout/types';
import { eightAreas, element, withElements } from '../../../js/layout/support';

const elements = [
    element('d1', 'primary_activities', 'domain', 'Onderwijs en ontwikkeling van de leerlingen', null, 1),
    element('k1', 'primary_activities', 'cluster', 'Peuters en kleuters', 'd1', 1),
    element('a1', 'primary_activities', 'activity', 'Onderwijsontwikkelingsprogramma', 'k1', 1),
    element('a2', 'primary_activities', 'activity', 'Lessen geven aan een grote groep', 'd1', 2),
    element('d2', 'primary_activities', 'domain', 'Zorg', null, 2),
    element('d3', 'supporting_activities', 'domain', 'Facilitaire dienst met een lange naam', null, 1),
    element('g1', 'actors', 'domain', 'Ouders en verzorgers van alle kinderen', null, 1),
    element('g2', 'actors', 'activity', 'Moeders', 'g1', 1),
];
const layouts: Record<string, ElementLayout> = {
    primary_activities: { mode: 'manual', x: 560, y: 400, width: 1100, height: 380, z: 0 },
    d1: { mode: 'manual', x: 16, y: 48, width: 286, height: 204, z: 2, style: { fill: '#F6E6CC' } },
    k1: { mode: 'manual', x: 8, y: 60, width: 200, height: 40, z: 3 },
    a1: { mode: 'manual', x: 8, y: 30, width: 90, height: 24, z: 4 },
    d2: { mode: 'manual', x: 320, y: 48, width: 120, height: 30, z: 2 },
    g1: { mode: 'manual', x: 12, y: 60, width: 180, height: 50, z: 1 },
};
const drawn = ref<DrawnCanvas | null>(null);
const engine = ref('');

onMounted(async () => {
    const result = computeCanvasLayout({ ...withElements(elements), layouts }, await createBrowserMeasurer());
    engine.value = JSON.stringify(result.blocks.map(({ id, x, y, width, drawnHeight, textLines }) => ({ id, x, y, width, drawnHeight, textLines })));
    drawn.value = result;
});
</script>

<template>
    <main :data-engine="engine">
        <CanvasView v-if="drawn" :drawn="drawn" :areas="eightAreas" :elements="elements" :statements="[]" :modalities="[]" />
    </main>
</template>

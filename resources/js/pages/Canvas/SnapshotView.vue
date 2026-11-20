<script setup lang="ts">
import { computed, onMounted, shallowRef } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CanvasStage from '../../canvas/CanvasStage.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import HeaderSlot from '../../canvas/HeaderSlot.vue';
import OverflowNotice from '../../canvas/OverflowNotice.vue';
import OverlaySwitch from '../../canvas/OverlaySwitch.vue';
import type { CanvasFigures } from '../../canvas/overlay/figures';
import { visitOverlay } from '../../canvas/overlay/query';
import SnapshotBanner from '../../canvas/SnapshotBanner.vue';
import VersionSelect from '../../canvas/VersionSelect.vue';
import type { VersionOption } from '../../canvas/VersionSelect.vue';
import { computeCanvasLayout } from '../../layout';
import type { CanvasLayoutInput, TextMeasurer } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';

const props = defineProps<{
    snapshot: { id: number; name: string; date: string; year: number };
    versions: VersionOption[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
    modalities: ModalityMark[];
    overlay: string;
    year: number;
    costKinds: string[];
    damaged: boolean;
    figures: CanvasFigures | null;
    figuresMessage: string | null;
}>();

const canvas = computed(() => usePage().props.canvas as { id: number; name: string });
const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value && !props.damaged ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, measurer.value) : null));
</script>

<template>
    <Head :title="`${canvas.name} - ${snapshot.name}`" />
    <HeaderSlot>
        <h1 class="order-first mr-2 font-display text-[20px] font-semibold leading-[28px] tracking-[-0.01em]">{{ canvas.name }}</h1>
        <OverlaySwitch v-if="!damaged" :model-value="overlay" @update:model-value="visitOverlay({ overlay: $event === 'none' ? null : $event })" />
    </HeaderSlot>
    <SnapshotBanner :name="snapshot.name" :date="snapshot.date" :year="snapshot.year">
        <VersionSelect :canvas-id="canvas.id" :versions="versions" :current="snapshot.id" />
    </SnapshotBanner>
    <OverflowNotice v-if="drawn" :count="drawn.beyondArtboardCount" />
    <p v-if="figuresMessage" role="alert" class="mb-3 rounded-control border border-danger-edge px-3 py-2 text-[13px] text-danger" data-figures-message>{{ figuresMessage }}</p>
    <CanvasStage v-if="!damaged" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :overlay="overlay" :year="year" :figures="figures" :cost-kinds="costKinds" :canvas-id="canvas.id" :can-assign="false" />
</template>

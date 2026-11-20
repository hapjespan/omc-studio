<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CanvasStage from '../../canvas/CanvasStage.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import ExportMenu from '../../canvas/ExportMenu.vue';
import OverflowNotice from '../../canvas/OverflowNotice.vue';
import OverlaySwitch from '../../canvas/OverlaySwitch.vue';
import type { CanvasFigures, OverlayFilters as Filters } from '../../canvas/overlay/figures';
import OverlayFilters from '../../canvas/overlay/OverlayFilters.vue';
import type { FilterOptions } from '../../canvas/overlay/OverlayFilters.vue';
import { visitOverlay } from '../../canvas/overlay/query';
import VersionSelect from '../../canvas/VersionSelect.vue';
import type { VersionOption } from '../../canvas/VersionSelect.vue';
import { computeCanvasLayout } from '../../layout';
import type { CanvasLayoutInput, TextMeasurer } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import HeaderSlot from '../../shell/HeaderSlot.vue';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    canWrite: boolean;
    versions: VersionOption[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
    modalities: ModalityMark[];
    overlay: string;
    year: number | null;
    figures: CanvasFigures | null;
    figuresMessage: string | null;
    filters: Filters;
    filterOptions: FilterOptions;
}>();

const role = computed(() => (usePage().props.auth as { role: string | null }).role);
const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, measurer.value) : null));
const organisationId = computed(() => (usePage().props.canvas as { organisation: { id: number } | null } | null)?.organisation?.id ?? null);
const plain = ref(false);
</script>

<template>
    <Head :title="canvas.name" />
    <HeaderSlot to="title">
        <h1 class="mr-2 font-display text-[20px] font-semibold leading-[28px] tracking-[-0.01em]">{{ canvas.name }}</h1>
    </HeaderSlot>
    <HeaderSlot to="actions">
        <OverlaySwitch :model-value="overlay" @update:model-value="visitOverlay({ overlay: $event === 'none' ? null : $event })" />
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <OverlayFilters v-if="overlay === 'financial'" :filters="filters" :options="filterOptions" />
            <VersionSelect v-if="versions.length" :canvas-id="canvas.id" :versions="versions" :current="null" />
            <ExportMenu :canvas-id="canvas.id" :organisation-id="organisationId" :beyond="drawn?.beyondArtboardCount ?? 0" :overlay="overlay" @plain="plain = $event" />
        </div>
    </HeaderSlot>
    <OverflowNotice v-if="drawn" :count="drawn.beyondArtboardCount" />
    <p v-if="figuresMessage" role="alert" class="mb-3 rounded-control border border-danger-edge px-3 py-2 text-[13px] text-danger" data-figures-message>{{ figuresMessage }}</p>
    <CanvasStage :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :overlay="overlay" :year="year" :figures="figures" :cost-kinds="filterOptions.costKinds" :canvas-id="canvas.id" :can-assign="role === 'administrator'" :plain="plain" />
</template>

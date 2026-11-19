<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import CanvasView from '../../canvas/CanvasView.vue';
import type { ModalityMark } from '../../canvas/CanvasView.vue';
import { contentHeight } from '../../canvas/extent';
import OverflowNotice from '../../canvas/OverflowNotice.vue';
import OverlaySwitch from '../../canvas/OverlaySwitch.vue';
import { visitOverlay } from '../../canvas/overlay/query';
import ViewDetail, { detailOf } from '../../canvas/ViewDetail.vue';
import ZoomToolbar, { fitZoom } from '../../canvas/ZoomToolbar.vue';
import { ARTBOARD } from '../../layout';
import { detail, openDetail } from '../../shell/detail';
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
    overlay: string;
}>();

const measurer = shallowRef<TextMeasurer | null>(null);
onMounted(async () => (measurer.value = await createBrowserMeasurer()));
const drawn = computed(() => (measurer.value ? computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, measurer.value) : null));
const selectedId = ref<string | null>(null);
const content = computed(() => (selectedId.value ? detailOf(selectedId.value, { areas: props.layout.areas, elements: props.elements, statements: props.statements, modalities: props.modalities }) : null));
const select = (id: string): void => {
    selectedId.value = id;
    if (content.value) openDetail(content.value.title, document.querySelector<HTMLElement>(`[data-block="${CSS.escape(id)}"]`));
};
watch(() => detail.open, (open) => !open && (selectedId.value = null));

const zoom = ref(100);
const scroller = ref<HTMLElement>();
const fit = (): void => {
    if (scroller.value && drawn.value) zoom.value = fitZoom({ width: scroller.value.clientWidth, height: scroller.value.clientHeight }, { width: ARTBOARD.width, height: contentHeight(drawn.value) });
};
</script>

<template>
    <Head :title="canvas.name" />
    <h1 class="text-[20px] font-semibold">{{ canvas.name }}</h1>
    <OverflowNotice v-if="drawn" :count="drawn.beyondArtboardCount" />
    <div class="mt-3 flex flex-wrap items-center gap-4">
        <ZoomToolbar v-if="drawn" v-model:zoom="zoom" @fit="fit" />
        <OverlaySwitch :model-value="overlay" @update:model-value="visitOverlay({ overlay: $event === 'none' ? null : $event })" />
    </div>
    <div ref="scroller" class="mt-3 h-[calc(100vh-14rem)] overflow-auto" tabindex="-1" data-scroller>
        <CanvasView v-if="drawn" :drawn="drawn" :areas="layout.areas" :elements="elements" :statements="statements" :modalities="modalities" :zoom="zoom" :selected-id="selectedId" @select="select" />
    </div>
    <ViewDetail v-if="content && detail.open" :content="content" />
</template>

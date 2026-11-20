<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computeCanvasLayout } from '../../layout';
import { createBrowserMeasurer } from '../../layout/browserMeasurer';
import type { CanvasLayoutInput } from '../../layout';
import ModeSwitch from '../../shell/ModeSwitch.vue';
import type { ShellCanvas } from '../../shell/TopBar.vue';
import type { DrawingEngine } from '../../edit-engine';
import { automaticPayload } from '../../edit-engine/payload';

const props = defineProps<{
    canvas: { id: number; name: string; archived: boolean };
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
}>();

const page = usePage<{ auth: { role: string } }>();
const shellCanvas = computed(() => page.props.canvas as unknown as ShellCanvas);
const host = ref<HTMLElement>();
const state = ref<'loading' | 'ready' | 'failed'>('loading');
let engine: DrawingEngine | null = null;

onMounted(async () => {
    try {
        const drawn = computeCanvasLayout({ ...props.layout, elements: props.elements, statements: props.statements }, await createBrowserMeasurer());
        const { createDrawingEngine } = await import('../../edit-engine');
        engine = createDrawingEngine();
        await engine.mount(host.value as HTMLElement, drawn, { layout: automaticPayload(props.elements.map(({ id }) => id)) });
        engine.zoomToFit();
        state.value = 'ready';
    } catch {
        state.value = 'failed';
    }
});
onBeforeUnmount(() => engine?.destroy());
</script>

<template>
    <Head :title="canvas.name" />
    <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h1 class="font-display text-[22px] font-semibold">{{ canvas.name }}</h1>
        <ModeSwitch :role="page.props.auth.role" :canvas="shellCanvas" :url="page.url" />
    </header>
    <div ref="host" class="mt-3 h-[calc(100vh-10rem)] w-full" :data-state="state"></div>
</template>

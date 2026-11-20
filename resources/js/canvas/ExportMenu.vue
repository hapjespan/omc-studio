<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { announce } from '../components/feedback/status';
import ActionMenu from '../components/ui/ActionMenu.vue';
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';
import { routes } from '../routes';
import { exportCsv } from './csvDownload';
import { exportPng } from './exportPng';
import { printCanvas, printWarning } from './printCanvas';

const props = withDefaults(defineProps<{ canvasId: number; organisationId: number | null; beyond?: number; overlay?: string }>(), { beyond: 0, overlay: 'none' });

const emit = defineEmits<{ plain: [value: boolean] }>();

const withOverlay = ref(true);
const busy = ref(false);
const plainNow = (): boolean => props.overlay !== 'none' && !withOverlay.value;
const beforePrint = (): void => emit('plain', plainNow());
const afterPrint = (): void => emit('plain', false);
const note = ref('');
const items = computed(() => [
    { key: 'json', label: t('jsonx.export') },
    ...(props.organisationId ? [{ key: 'cost-records', label: t('csvx.export_cost_records') }] : []),
    { key: 'allocations', label: t('csvx.export_allocations') },
    { key: 'png', label: t('print.export_png') },
    { key: 'print', label: t('print.print') },
]);

onMounted(() => {
    window.addEventListener('beforeprint', beforePrint);
    window.addEventListener('afterprint', afterPrint);
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeprint', beforePrint);
    window.removeEventListener('afterprint', afterPrint);
});

function json(): void {
    const link = document.createElement('a');
    link.href = routes.canvasJsonExport(props.canvasId);
    link.download = '';
    document.body.append(link);
    link.click();
    link.remove();
}

async function csv(url: string, name: string): Promise<void> {
    const text = await exportCsv(url, name);
    if (!text) return;
    note.value = text;
    announce(text);
}

async function png(): Promise<void> {
    if (busy.value) return;
    busy.value = true;
    try {
        announce(t('print.png_working'));
        emit('plain', plainNow());
        await nextTick();
        const text = await exportPng();
        note.value = text;
        announce(text);
    } finally {
        emit('plain', false);
        busy.value = false;
    }
}

const pending = ref(false);
const proceed = ref<InstanceType<typeof AppButton>>();

async function print(): Promise<void> {
    const warning = printWarning(props.beyond);
    if (!warning) {
        note.value = '';
        printCanvas();
        return;
    }
    note.value = warning;
    pending.value = true;
    await announce(warning);
    await nextTick();
    proceed.value?.focus();
}

function printAnyway(): void {
    pending.value = false;
    note.value = '';
    printCanvas();
}

function run(key: string): void {
    if (key === 'json') json();
    else if (key === 'cost-records' && props.organisationId) void csv(routes.costRecordsCsvExport(props.organisationId), 'cost-records.csv');
    else if (key === 'allocations') void csv(routes.allocationsCsvExport(props.canvasId), 'allocations.csv');
    else if (key === 'png') void png();
    else if (key === 'print') void print();
}
</script>

<template>
    <div class="contents print:hidden">
        <label v-if="overlay !== 'none'" class="inline-flex min-h-mark items-center gap-2 text-[12.5px] text-secondary"><input v-model="withOverlay" type="checkbox" role="switch" class="size-4 accent-ink" data-with-overlay>{{ t('print.with_overlay') }}</label>
        <ActionMenu :label="t('jsonx.export_menu')" :items="items" @select="run" />
        <p v-if="note" class="basis-full text-right text-[12.5px]" data-testid="export-note">{{ note }}</p>
        <AppButton v-if="pending" ref="proceed" @click="printAnyway">{{ t('print.print_anyway') }}</AppButton>
    </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { announce } from '../components/feedback/status';
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';
import { routes } from '../routes';
import { exportCsv } from './csvDownload';
import { exportPng } from './exportPng';
import { printCanvas, printWarning } from './printCanvas';

const props = withDefaults(defineProps<{ canvasId: number; organisationId: number | null; beyond?: number; overlay?: string }>(), { beyond: 0, overlay: 'none' });

const emit = defineEmits<{ plain: [value: boolean] }>();

const open = ref(false);
const withOverlay = ref(true);
const busy = ref(false);
const plainNow = (): boolean => props.overlay !== 'none' && !withOverlay.value;
const beforePrint = (): void => emit('plain', plainNow());
const afterPrint = (): void => emit('plain', false);
const note = ref('');
const trigger = ref<InstanceType<typeof AppButton>>();
const list = ref<HTMLElement>();
const root = ref<HTMLElement>();

const outside = (event: MouseEvent): void => {
    if (open.value && !root.value?.contains(event.target as Node)) open.value = false;
};
onMounted(() => {
    document.addEventListener('click', outside);
    window.addEventListener('beforeprint', beforePrint);
    window.addEventListener('afterprint', afterPrint);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', outside);
    window.removeEventListener('beforeprint', beforePrint);
    window.removeEventListener('afterprint', afterPrint);
});

function leave(event: FocusEvent): void {
    if (event.relatedTarget && !root.value?.contains(event.relatedTarget as Node)) open.value = false;
}

async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value) {
        await nextTick();
        list.value?.querySelector<HTMLElement>('[role="menuitem"]')?.focus();
    }
}

function close(): void {
    if (!open.value) return;
    open.value = false;
    trigger.value?.focus();
}

function move(event: KeyboardEvent, step: number): void {
    const items = [...(list.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];
    items[(items.indexOf(event.target as HTMLElement) + step + items.length) % items.length]?.focus();
}

async function csv(url: string, name: string): Promise<void> {
    const text = await exportCsv(url, name);
    if (!text) return;
    note.value = text;
    announce(text);
    close();
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
        close();
    } finally {
        emit('plain', false);
        busy.value = false;
    }
}

const pending = ref(false);
const proceed = ref<InstanceType<typeof AppButton>>();
const item = 'flex min-h-dense items-center rounded-badge px-2.5 text-[13px] text-ink hover:bg-accent-soft focus:bg-accent-soft';

async function print(): Promise<void> {
    close();
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
</script>

<template>
    <div class="contents print:hidden">
        <label v-if="overlay !== 'none'" class="inline-flex min-h-mark items-center gap-2 text-[12.5px] text-secondary"><input v-model="withOverlay" type="checkbox" role="switch" class="size-4 accent-ink" data-with-overlay>{{ t('print.with_overlay') }}</label>
        <div ref="root" class="relative inline-block" @keydown.esc="close" @focusout="leave">
            <AppButton ref="trigger" variant="primary" aria-haspopup="menu" :aria-expanded="open" aria-controls="export-menu" @click="toggle">{{ t('jsonx.export_menu') }}</AppButton>
            <ul v-show="open" id="export-menu" ref="list" role="menu" :aria-label="t('jsonx.export_menu')" class="absolute right-0 z-20 mt-1 min-w-[16rem] rounded-control border border-hairline bg-white p-1 shadow-[0_1px_3px_rgba(0,0,0,.12)]" @keydown.down.prevent="move($event, 1)" @keydown.up.prevent="move($event, -1)">
                <li role="none"><a role="menuitem" tabindex="-1" :href="routes.canvasJsonExport(canvasId)" download :class="item" @click="close">{{ t('jsonx.export') }}</a></li>
                <li v-if="organisationId" role="none"><button role="menuitem" tabindex="-1" type="button" :class="[item, 'w-full text-left']" @click="csv(routes.costRecordsCsvExport(organisationId), 'cost-records.csv')">{{ t('csvx.export_cost_records') }}</button></li>
                <li role="none"><button role="menuitem" tabindex="-1" type="button" :class="[item, 'w-full text-left']" @click="csv(routes.allocationsCsvExport(canvasId), 'allocations.csv')">{{ t('csvx.export_allocations') }}</button></li>
                <li role="none"><button role="menuitem" tabindex="-1" type="button" :class="[item, 'w-full text-left']" @click="png">{{ t('print.export_png') }}</button></li>
                <li role="none"><button role="menuitem" tabindex="-1" type="button" :class="[item, 'w-full text-left']" @click="print">{{ t('print.print') }}</button></li>
            </ul>
        </div>
        <p v-if="note" class="basis-full text-right text-[12.5px]" data-testid="export-note">{{ note }}</p>
        <AppButton v-if="pending" ref="proceed" @click="printAnyway">{{ t('print.print_anyway') }}</AppButton>
    </div>
</template>

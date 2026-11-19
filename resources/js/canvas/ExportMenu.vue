<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { announce } from '../components/feedback/status';
import { t } from '../i18n';
import { routes } from '../routes';
import { exportCsv } from './csvDownload';
import { exportPng } from './exportPng';
import { printCanvas, printWarning } from './printCanvas';

const props = withDefaults(defineProps<{ canvasId: number; organisationId: number | null; beyond?: number }>(), { beyond: 0 });

const open = ref(false);
const note = ref('');
const trigger = ref<HTMLButtonElement>();
const list = ref<HTMLElement>();
const root = ref<HTMLElement>();

const outside = (event: MouseEvent): void => {
    if (open.value && !root.value?.contains(event.target as Node)) open.value = false;
};
onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));

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
    announce(t('print.png_working'));
    const text = await exportPng();
    note.value = text;
    announce(text);
    close();
}

const pending = ref(false);
const proceed = ref<HTMLButtonElement>();

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
    <div ref="root" class="relative inline-block print:hidden" @keydown.esc="close" @focusout="leave">
        <button ref="trigger" type="button" aria-haspopup="menu" :aria-expanded="open" aria-controls="export-menu" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="toggle">{{ t('jsonx.export_menu') }}</button>
        <ul v-show="open" id="export-menu" ref="list" role="menu" :aria-label="t('jsonx.export_menu')" class="absolute z-20 mt-1 min-w-[16rem] rounded-md border border-[#848B95] bg-white p-1 shadow-md" @keydown.down.prevent="move($event, 1)" @keydown.up.prevent="move($event, -1)">
            <li role="none"><a role="menuitem" tabindex="-1" :href="routes.canvasJsonExport(canvasId)" download class="block rounded px-2 py-1.5 text-[13px] hover:bg-[#E3EBFB] focus:bg-[#E3EBFB]" @click="close">{{ t('jsonx.export') }}</a></li>
            <li v-if="organisationId" role="none"><button role="menuitem" tabindex="-1" type="button" class="block w-full rounded px-2 py-1.5 text-left text-[13px] hover:bg-[#E3EBFB] focus:bg-[#E3EBFB]" @click="csv(routes.costRecordsCsvExport(organisationId), 'cost-records.csv')">{{ t('csvx.export_cost_records') }}</button></li>
            <li role="none"><button role="menuitem" tabindex="-1" type="button" class="block w-full rounded px-2 py-1.5 text-left text-[13px] hover:bg-[#E3EBFB] focus:bg-[#E3EBFB]" @click="csv(routes.allocationsCsvExport(canvasId), 'allocations.csv')">{{ t('csvx.export_allocations') }}</button></li>
            <li role="none"><button role="menuitem" tabindex="-1" type="button" class="block w-full rounded px-2 py-1.5 text-left text-[13px] hover:bg-[#E3EBFB] focus:bg-[#E3EBFB]" @click="png">{{ t('print.export_png') }}</button></li>
            <li role="none"><button role="menuitem" tabindex="-1" type="button" class="block w-full rounded px-2 py-1.5 text-left text-[13px] hover:bg-[#E3EBFB] focus:bg-[#E3EBFB]" @click="print">{{ t('print.print') }}</button></li>
        </ul>
        <p v-if="note" class="mt-1 text-[12.5px]" data-testid="export-note">{{ note }}</p>
        <button v-if="pending" ref="proceed" type="button" class="mt-1 rounded-md border border-[#848B95] px-3 py-1.5 text-[13px] font-semibold" @click="printAnyway">{{ t('print.print_anyway') }}</button>
    </div>
</template>

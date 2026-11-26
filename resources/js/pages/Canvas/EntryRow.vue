<script setup lang="ts">
import ModalityMark from '../../components/ModalityMark.vue';
import AppButton from '../../components/ui/AppButton.vue';
import Pill from '../../components/ui/Pill.vue';
import { t } from '../../i18n';
import { kindLabelKey, type EntryRowData } from './entry';
import ReorderButtons from './ReorderButtons.vue';

const depthClass = { 0: 'pl-0', 1: 'pl-6', 2: 'pl-12' } as const;

defineProps<{ row: EntryRowData; letter: string | null; selected: boolean; canvasId: number; icon?: string | null }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null]; restore: [element: EntryRowData['element']] }>();
</script>

<template>
    <li data-testid="entry-row" :data-depth="row.depth" :class="depthClass[row.depth]">
        <div :class="['flex min-h-control items-center gap-2 rounded-control border bg-white pr-1.5', selected ? 'selected-ring' : 'border-hairline']">
            <button :id="`row-${row.element.id}`" type="button" :aria-current="selected ? 'true' : undefined" class="flex min-h-control min-w-0 flex-1 items-center gap-3 rounded-control px-2.5 text-left text-[13px]" @click="(event) => $emit('select', row.element.id, event.currentTarget as HTMLElement)">
                <span class="w-[72px] shrink-0 text-[10.5px] text-muted">{{ t(kindLabelKey(row.element.kind, row.element.area)) }}</span>
                <span :class="['min-w-0 flex-1', row.element.kind !== 'activity' && 'font-semibold']">{{ row.element.name }}</span>
                <span v-if="row.element.archived" class="text-[12.5px] font-semibold text-secondary">{{ t('list.archived') }}</span>
                <span v-if="icon" class="text-[12.5px] text-secondary">{{ icon }}</span>
                <Pill v-if="row.empty" tone="warning" icon="warning">{{ t('entry.empty') }}</Pill>
                <ModalityMark v-if="letter" :letter="letter" />
            </button>
            <AppButton v-if="row.element.archived" size="small" :aria-label="t('entry.restore_named', { name: row.element.name })" @click="$emit('restore', row.element)">{{ t('list.restore') }}</AppButton>
            <ReorderButtons v-else :canvas-id="canvasId" :row="row" />
        </div>
    </li>
</template>

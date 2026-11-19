<script setup lang="ts">
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import type { TargetRow } from '../../allocation/types';

defineProps<{ rows: TargetRow[]; inSet: Set<string>; needle: string; disabled: boolean; hideTotals: boolean; chosen?: string | null }>();
const emit = defineEmits<{ add: [id: string] }>();

const matches = (row: TargetRow, needle: string): boolean => needle === '' || row.name.toLowerCase().includes(needle) || row.children.some((child) => matches(child, needle));
const drag = (event: DragEvent, row: TargetRow): void => event.dataTransfer?.setData('text/plain', String(row.id)) ?? undefined;
</script>

<template>
    <ul role="group" class="space-y-1">
        <template v-for="row in rows" :key="row.id">
            <li v-if="matches(row, needle)" role="treeitem" :aria-expanded="row.children.length ? 'true' : undefined">
                <div class="flex items-center gap-2 rounded-md border border-[#C9CED6] px-2 py-1 text-[13px]" :draggable="!disabled && chosen === undefined" @dragstart="drag($event, row)">
                    <span class="min-w-0 flex-1">
                        <span class="font-semibold">{{ row.name }}</span>
                        <span class="ml-1 text-[#4B5563]">({{ t(`allocation_page.kind.${row.kind}`) }})</span>
                        <span v-if="row.totalCents !== null && !hideTotals" class="ml-1">{{ formatAmount(row.totalCents) }}</span>
                    </span>
                    <span v-if="inSet.has(String(row.id))" class="rounded bg-[#EEF1F5] px-1.5 text-[12px]">{{ t('allocation_page.in_set') }}</span>
                    <span v-if="chosen !== undefined && chosen === String(row.id)" class="rounded bg-[#EEF1F5] px-1.5 text-[12px]">{{ t('redistribute.chosen') }}</span>
                    <button v-if="chosen === undefined" type="button" :disabled="disabled" :aria-label="t('allocation_page.add', { target: row.name })" class="rounded-md border border-[#848B95] px-2 py-1 disabled:opacity-60" @click="emit('add', String(row.id))">{{ t('allocation_page.add_label') }}</button>
                    <button v-else type="button" :aria-pressed="chosen === String(row.id)" :aria-label="t('redistribute.target_action', { target: row.name })" class="rounded-md border border-[#848B95] px-2 py-1 aria-pressed:border-2 aria-pressed:border-[#1B1F27] aria-pressed:font-semibold" @click="emit('add', String(row.id))">{{ t('redistribute.choose') }}</button>
                </div>
                <div v-if="row.children.length" class="ml-4 mt-1">
                    <TargetNode :rows="row.children" :in-set="inSet" :needle="needle" :disabled="disabled" :hide-totals="hideTotals" :chosen="chosen" @add="emit('add', $event)" />
                </div>
            </li>
        </template>
    </ul>
</template>

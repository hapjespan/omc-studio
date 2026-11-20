<script setup lang="ts">
import { activitiesOf } from '../../allocation/helpers';
import { matchesTarget, type TargetRow } from '../../allocation/types';
import AppButton from '../../components/ui/AppButton.vue';
import Icon from '../../components/ui/Icon.vue';
import IconButton from '../../components/ui/IconButton.vue';
import { formatAmount } from '../../format';
import { plural, t } from '../../i18n';

withDefaults(defineProps<{ rows: TargetRow[]; inSet: Set<string>; needle: string; disabled: boolean; hideTotals: boolean; chosen?: string | null; depth?: number }>(), { chosen: undefined, depth: 0 });
const emit = defineEmits<{ add: [id: string] }>();

const drag = (event: DragEvent, row: TargetRow): void => event.dataTransfer?.setData('text/plain', String(row.id)) ?? undefined;
const kindLabel = (row: TargetRow): string => {
    const kind = t(`allocation_page.kind.${row.kind}`);
    if (row.kind !== 'domain') return kind;
    const count = activitiesOf(row).length;
    return `${kind} · ${count > 0 ? plural('allocation_page.domain_activities', count) : t('allocation_page.tree_empty')}`;
};
</script>

<template>
    <ul role="group" :class="depth === 0 ? 'space-y-1.5' : 'mt-0.5'">
        <template v-for="row in rows" :key="row.id">
            <li v-if="matchesTarget(row, needle)" role="treeitem" :aria-expanded="row.children.length ? 'true' : undefined">
                <div class="flex items-center gap-2 rounded-card px-2.5 py-1.5" :class="depth === 0 ? 'bg-surface' : ''" :draggable="!disabled && chosen === undefined" @dragstart="drag($event, row)">
                    <span class="flex min-w-0 flex-1 flex-col-reverse">
                        <span class="text-[12.5px] leading-snug text-ink" :class="depth === 0 ? 'font-semibold' : ''">{{ row.name }}</span>
                        <span class="text-[10.5px] leading-snug text-muted">{{ kindLabel(row) }}</span>
                    </span>
                    <span v-if="row.totalCents !== null && !hideTotals" class="shrink-0 whitespace-nowrap text-right text-[12.5px]" :class="row.totalCents > 0 ? 'text-secondary' : 'text-muted'">{{ row.totalCents > 0 ? formatAmount(row.totalCents) : t('allocation_page.none') }}</span>
                    <span v-if="chosen !== undefined && chosen === String(row.id)" class="rounded-badge bg-surface px-1.5 text-[12px]">{{ t('redistribute.chosen') }}</span>
                    <template v-if="chosen === undefined">
                        <span v-if="inSet.has(String(row.id))" class="inline-flex shrink-0 items-center gap-1 text-[11.5px] font-semibold text-success"><Icon name="check" :size="12" />{{ t('allocation_page.in_set') }}</span>
                        <IconButton v-else icon="plus" :disabled="disabled" :label="t('allocation_page.add', { target: row.name })" @click="emit('add', String(row.id))" />
                    </template>
                    <AppButton v-else size="small" :aria-pressed="chosen === String(row.id)" :aria-label="t('redistribute.target_action', { target: row.name })" class="aria-pressed:border-2 aria-pressed:border-ink aria-pressed:font-semibold" @click="emit('add', String(row.id))">{{ t('redistribute.choose') }}</AppButton>
                </div>
                <div v-if="row.children.length" class="pl-4">
                    <TargetNode :rows="row.children" :in-set="inSet" :needle="needle" :disabled="disabled" :hide-totals="hideTotals" :chosen="chosen" :depth="depth + 1" @add="emit('add', $event)" />
                </div>
            </li>
        </template>
    </ul>
</template>

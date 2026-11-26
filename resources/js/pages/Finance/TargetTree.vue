<script setup lang="ts">
import { computed, ref } from 'vue';
import { matchesTarget, type TargetRow } from '../../allocation/types';
import SearchField from '../../components/ui/SearchField.vue';
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import TargetNode from './TargetNode.vue';

const AREA_ORDER = ['steering', 'governing', 'primary_activities', 'supporting_activities'];

const props = defineProps<{ targets: TargetRow[]; inSet: string[]; disabled: boolean; figuresAvailable: boolean; year: number | null; areaTotals: Record<string, number> | null }>();
const emit = defineEmits<{ add: [id: string] }>();
const search = ref('');
const set = computed(() => new Set(props.inSet));
const needle = computed(() => search.value.trim().toLowerCase());
const groups = computed(() => {
    const byArea = new Map<string, TargetRow[]>();
    for (const row of props.targets) byArea.set(row.area ?? '', [...(byArea.get(row.area ?? '') ?? []), row]);
    const keys = [...byArea.keys()].sort((a, b) => (AREA_ORDER.indexOf(a) + 1 || 99) - (AREA_ORDER.indexOf(b) + 1 || 99));
    return keys
        .map((key) => ({ key, rows: byArea.get(key) ?? [] }))
        .filter((group) => group.rows.some((row) => matchesTarget(row, needle.value)))
        .map((group) => ({ ...group, total: props.areaTotals?.[group.key] ?? null }));
});
</script>

<template>
    <section role="region" aria-labelledby="allocation-targets" class="min-w-0 rounded-panel border border-hairline bg-white p-3.5">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="allocation-targets" class="font-display text-[13px] font-semibold">{{ t('allocation_page.targets') }}</h2>
            <span v-if="figuresAvailable && year !== null" class="text-[12px] text-muted">{{ t('allocation_page.targets_total', { year }) }}</span>
        </div>
        <p v-if="!figuresAvailable" role="status" class="mt-1 text-[12.5px] text-warning">{{ t('allocation_page.total_hidden') }}</p>
        <div class="mt-2.5"><SearchField id="allocation-targets-search" v-model="search" :label="t('allocation_page.targets_search')" :placeholder="t('allocation_page.targets_placeholder')" /></div>
        <div class="mt-1">
            <div v-for="group in groups" :key="group.key" role="group" :aria-labelledby="`allocation-area-${group.key}`">
                <div class="flex items-baseline justify-between px-2.5 pb-1.5 pt-3.5 text-[10.5px] font-semibold uppercase tracking-wider text-secondary">
                    <span :id="`allocation-area-${group.key}`">{{ group.key ? t(`areas.${group.key}.name`) : '' }}</span>
                    <span v-if="figuresAvailable && group.total !== null" class="normal-case tracking-normal">{{ group.total > 0 ? formatAmount(group.total) : t('allocation_page.none') }}</span>
                </div>
                <TargetNode :rows="group.rows" :in-set="set" :needle="needle" :disabled="disabled" :hide-totals="!figuresAvailable" collapsible @add="emit('add', $event)" />
            </div>
        </div>
        <p v-if="groups.length === 0" role="status" class="mt-2.5 text-[13px] text-secondary">{{ t('allocation_page.targets_empty') }}</p>
        <p class="mt-3.5 text-[11.5px] leading-snug text-muted">{{ t('allocation_page.footnote') }}</p>
    </section>
</template>

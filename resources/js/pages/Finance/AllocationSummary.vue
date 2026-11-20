<script setup lang="ts">
import { computed } from 'vue';
import { formatPercentage, remaining } from '../../allocation/helpers';
import type { CostRecordRow, DraftLine } from '../../allocation/types';
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import { stripeClass } from './AllocationEditor.vue';

const props = defineProps<{ record: CostRecordRow; year: number | null; lines: DraftLine[]; draft: string | null }>();
const left = computed(() => remaining(props.lines.map((line) => line.basisPoints)));
const leftCents = computed(() => (left.value === 0 ? 0 : Math.round((props.record.budgetCents * left.value) / 10000)));
const eyebrow = computed(() => [props.record.code, props.record.costKind ? t(`cost_kind.${props.record.costKind}`) : null, props.record.owner ? t('allocation_page.eyebrow_owner', { owner: props.record.owner }) : null].filter(Boolean).join(' · '));
const live = computed(() => t('allocation_page.remaining_live', { percentage: formatPercentage(left.value), amount: formatAmount(leftCents.value) }));
</script>

<template>
    <div class="rounded-panel border border-hairline bg-white p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p v-if="eyebrow" class="text-[11.5px] text-muted">{{ eyebrow }}</p>
                <h2 id="allocation-middle" class="mt-1 font-display text-[24px] font-semibold leading-tight">{{ record.name }}</h2>
                <p class="mt-1 text-[13px]">{{ t('allocation_page.budget', { year: year ?? '', amount: formatAmount(record.budgetCents) }) }}<span v-if="draft" class="ml-3 font-semibold text-warning" data-testid="draft">{{ t('allocation_page.draft', { percentage: draft }) }}</span></p>
            </div>
            <div class="shrink-0 rounded-card bg-warning-soft px-3.5 py-3 text-warning">
                <p class="text-[11.5px] font-semibold">{{ t('allocation_page.remaining') }}</p>
                <p data-testid="remaining" class="font-display text-[30px] font-bold leading-[1.4] tracking-[-0.6px]">{{ formatPercentage(left) }}</p>
                <p class="text-[12.5px]">{{ formatAmount(leftCents) }}</p>
            </div>
        </div>
        <div class="mt-4 flex h-2.5 gap-0.5" aria-hidden="true" data-testid="line-bar">
            <span v-for="(line, index) in lines" :key="line.elementId" class="min-w-0 basis-0 rounded-badge" :class="stripeClass(index)" :style="{ flexGrow: line.basisPoints }" />
            <span v-if="left > 0" class="min-w-0 basis-0 rounded-badge border border-dashed border-warning" :style="{ flexGrow: left }" />
        </div>
        <div class="mt-4"><slot /></div>
        <p role="status" aria-live="polite" class="sr-only">{{ live }}</p>
    </div>
</template>

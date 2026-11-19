<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatPercentage, lineAmounts, parsePercentage, remaining } from '../../allocation/helpers';
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import type { DraftLine, TargetInfo } from '../../allocation/types';

const props = defineProps<{ lines: DraftLine[]; info: Record<string, TargetInfo>; budgetCents: number; disabled: boolean }>();
const emit = defineEmits<{ change: [index: number, basisPoints: number]; remove: [index: number]; rest: [index: number]; drop: [id: string] }>();
const invalid = ref<number | null>(null);
const left = computed(() => remaining(props.lines.map((line) => line.basisPoints)));
const amounts = computed(() => (left.value === 0 ? lineAmounts(props.budgetCents, props.lines.map((line) => line.basisPoints)) : props.lines.map((line) => Math.round((props.budgetCents * line.basisPoints) / 10000))));
const leftCents = computed(() => (left.value === 0 ? 0 : Math.round((props.budgetCents * left.value) / 10000)));
const live = computed(() => t('allocation_page.remaining_live', { percentage: formatPercentage(left.value), amount: formatAmount(leftCents.value) }));
const target = (line: DraftLine): TargetInfo => props.info[line.elementId];
const text = (basisPoints: number): string => formatPercentage(basisPoints).replace('%', '');

function commit(index: number, event: Event): void {
    const input = event.target as HTMLInputElement;
    const parsed = parsePercentage(input.value);
    if (parsed === null || parsed < 1 || parsed > 10000) {
        invalid.value = index;
        return;
    }
    invalid.value = null;
    input.value = text(parsed);
    emit('change', index, parsed);
}

function dropped(event: DragEvent): void {
    const id = event.dataTransfer?.getData('text/plain');
    if (id && !props.disabled) emit('drop', id);
}
</script>

<template>
    <section role="region" :aria-label="t('allocation_page.drop_zone')" class="min-w-0" @dragover.prevent @drop.prevent="dropped">
        <p v-if="lines.length === 0" class="rounded-md border border-dashed border-[#848B95] p-3 text-[13px]">{{ t('allocation_page.no_lines') }}</p>
        <ul class="space-y-2">
            <li v-for="(line, index) in lines" :key="line.elementId" class="rounded-md border border-[#848B95] p-2 text-[13px]">
                <p class="font-semibold">{{ target(line).path }}</p>
                <p>{{ t(`allocation_page.kind.${target(line).kind}`) }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <label :for="`allocation-line-${index}`" class="sr-only">{{ t('allocation_page.percentage_of', { target: target(line).path }) }}</label>
                    <input :id="`allocation-line-${index}`" :value="text(line.basisPoints)" inputmode="decimal" :disabled="disabled" :aria-invalid="invalid === index || undefined" :aria-describedby="invalid === index ? `allocation-line-${index}-error` : undefined" class="w-24 rounded-md border border-[#848B95] bg-white px-2 py-1 text-right" @change="commit(index, $event)" />
                    <span aria-hidden="true">%</span>
                    <span :aria-label="t('allocation_page.amount')">{{ formatAmount(amounts[index]) }}</span>
                    <button type="button" :disabled="disabled" :aria-label="t('allocation_page.rest_to_line', { target: target(line).path })" class="rounded-md border border-[#848B95] px-2 py-1 disabled:opacity-60" @click="emit('rest', index)">{{ t('allocation_page.rest_label') }}</button>
                    <button type="button" :disabled="disabled" :aria-label="t('allocation_page.remove', { target: target(line).path })" class="rounded-md border border-[#848B95] px-2 py-1 disabled:opacity-60" @click="emit('remove', index)">&times;</button>
                </div>
                <p v-if="invalid === index" :id="`allocation-line-${index}-error`" role="alert" class="mt-1 text-[#A12A1E]">{{ t('allocation_page.invalid_percentage') }}</p>
            </li>
        </ul>
        <p class="mt-3 text-[14px] font-semibold">
            {{ t('allocation_page.remaining') }}: <span data-testid="remaining">{{ formatPercentage(left) }}</span>
            <span class="ml-1 font-normal">{{ formatAmount(leftCents) }}</span>
        </p>
        <p role="status" aria-live="polite" class="sr-only">{{ live }}</p>
    </section>
</template>

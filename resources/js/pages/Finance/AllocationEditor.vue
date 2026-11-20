<script lang="ts">
const STRIPES = ['bg-ink', 'bg-muted', 'bg-dashed'];

export const stripeClass = (index: number): string => STRIPES[index % STRIPES.length];
</script>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatPercentage, lineAmounts, parsePercentage, remaining } from '../../allocation/helpers';
import AppButton from '../../components/ui/AppButton.vue';
import IconButton from '../../components/ui/IconButton.vue';
import { formatAmount } from '../../format';
import { t } from '../../i18n';
import type { DraftLine, TargetInfo } from '../../allocation/types';

const props = defineProps<{ lines: DraftLine[]; info: Record<string, TargetInfo>; budgetCents: number; disabled: boolean }>();
const emit = defineEmits<{ change: [index: number, basisPoints: number]; remove: [index: number]; rest: [index: number]; drop: [id: string] }>();
const invalid = ref<number | null>(null);
const left = computed(() => remaining(props.lines.map((line) => line.basisPoints)));
const amounts = computed(() => (left.value === 0 ? lineAmounts(props.budgetCents, props.lines.map((line) => line.basisPoints)) : props.lines.map((line) => Math.round((props.budgetCents * line.basisPoints) / 10000))));
const target = (line: DraftLine): TargetInfo => props.info[line.elementId];
const text = (basisPoints: number): string => formatPercentage(basisPoints).replace('%', '');
const eyebrow = (line: DraftLine): string => {
    const info = target(line);
    const parents = info.path.split(' > ').slice(0, -1);
    return [t(`allocation_page.kind.${info.kind}`), [info.area ? t(`areas.${info.area}.name`) : null, ...parents].filter(Boolean).join(' › ')].filter(Boolean).join(' · ');
};

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
        <ul class="space-y-2.5">
            <li v-for="(line, index) in lines" :key="line.elementId" class="rounded-card border border-hairline bg-white p-3 text-[13px]">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="h-9 w-2.5 shrink-0 rounded-badge" :class="stripeClass(index)" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[11.5px] text-muted">{{ eyebrow(line) }}</p>
                        <p class="text-[15px] font-semibold">{{ target(line).name }}</p>
                    </div>
                    <label :for="`allocation-line-${index}`" class="sr-only">{{ t('allocation_page.percentage_of', { target: target(line).path }) }}</label>
                    <input :id="`allocation-line-${index}`" :value="text(line.basisPoints)" inputmode="decimal" :disabled="disabled" :aria-invalid="invalid === index || undefined" :aria-describedby="invalid === index ? `allocation-line-${index}-error` : undefined" class="h-control w-[84px] rounded-control border border-edge bg-white px-2.5 text-right font-display text-[13px] font-bold text-ink disabled:border-hairline disabled:text-muted" @change="commit(index, $event)" />
                    <span aria-hidden="true" class="font-semibold">%</span>
                    <span :aria-label="t('allocation_page.amount')" class="w-28 shrink-0 text-right font-display text-[13px] font-bold">{{ formatAmount(amounts[index]) }}</span>
                </div>
                <div class="mt-2 flex items-center gap-2 pl-[22px]">
                    <AppButton :disabled="disabled" :aria-label="t('allocation_page.rest_to_line', { target: target(line).path })" @click="emit('rest', index)">{{ t('allocation_page.rest_label') }}</AppButton>
                    <IconButton icon="close" :disabled="disabled" :label="t('allocation_page.remove', { target: target(line).path })" @click="emit('remove', index)" />
                </div>
                <p v-if="invalid === index" :id="`allocation-line-${index}-error`" role="alert" class="mt-1 text-danger">{{ t('allocation_page.invalid_percentage') }}</p>
            </li>
        </ul>
        <p class="mt-2.5 flex min-h-12 items-center justify-center rounded-card border border-dashed border-edge px-3 py-2 text-center text-[13px] text-secondary">{{ t('allocation_page.no_lines') }}</p>
    </section>
</template>

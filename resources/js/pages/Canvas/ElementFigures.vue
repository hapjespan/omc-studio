<script lang="ts">
import { formatPercentage } from '../../allocation/helpers';
import { formatAmount, formatFte } from '../../format';
import { t } from '../../i18n';
import type { ElementFigures } from '../../canvas/overlay/figures';

export function figureFields(figures: ElementFigures): { label: string; value: string }[] {
    return [
        { label: t('overlay.detail.direct'), value: formatAmount(figures.direct.budget) },
        { label: t('overlay.detail.total'), value: formatAmount(figures.total.budget) },
        { label: t('overlay.detail.share'), value: figures.shareBp === null ? '-' : formatPercentage(figures.shareBp) },
        { label: t('overlay.detail.forecast'), value: formatAmount(figures.total.forecast) },
        { label: t('overlay.detail.run'), value: formatAmount(figures.total.run) },
        { label: t('overlay.detail.change'), value: formatAmount(figures.total.change) },
        { label: t('overlay.detail.undetermined'), value: formatAmount(figures.total.undetermined) },
        { label: t('overlay.detail.fte'), value: formatFte(figures.fteHundredths) },
    ];
}
</script>

<script setup lang="ts">
import Breakdown from '../../canvas/overlay/Breakdown.vue';

defineProps<{ elementId: string; figures: ElementFigures }>();
</script>

<template>
    <Teleport to="#detail-body">
        <div class="mt-4 border-t border-hairline pt-3 text-[13px]" data-element-figures>
            <dl class="flex flex-col gap-3">
                <div v-for="field in figureFields(figures)" :key="field.label">
                    <dt class="font-semibold text-secondary">{{ field.label }}</dt>
                    <dd>{{ field.value }}</dd>
                </div>
            </dl>
            <Breakdown :key="elementId" :element-id="elementId" cents />
        </div>
    </Teleport>
</template>

<script lang="ts">
import { basisPointsOf, formatAmount, formatBasisPoints, formatWholeEuros } from '../../format';
import { plural, t } from '../../i18n';
import type { CanvasFigures } from '../../canvas/overlay/figures';

export interface PanelLine {
    label: string;
    value: string;
}

export interface PanelGroup {
    key: string;
    title: string;
    lines: PanelLine[];
}

const MODALITIES = ['common', 'adaptive', 'specialized', 'distinct', 'none', 'unknown_allocation'];
const zero = { budget: 0 };
const counted = (entry: { count: number; budget: number }): string => `${plural('overlay.panel.count', entry.count)} · ${formatWholeEuros(entry.budget)}`;

export function closingLine(figures: CanvasFigures): string {
    const { allocated, unknown } = figures.sidePanel;
    return t('overlay.panel.closing', { allocated: formatAmount(allocated.budget), unknown: formatAmount(unknown.budget), total: formatAmount(figures.canvasTotal.budget) });
}

export function panelGroups(figures: CanvasFigures, costKinds: string[]): PanelGroup[] {
    const { canvasTotal, sidePanel, breakdowns } = figures;
    const forecastShare = basisPointsOf(canvasTotal.forecast, canvasTotal.budget);
    const modalityLabel = (key: string): string => (key === 'none' ? t('overlay.panel.no_modality') : key === 'unknown_allocation' ? t('overlay.panel.unknown') : t(`view.modality.${key}`));
    return [
        {
            key: 'totals',
            title: t('overlay.panel.totals'),
            lines: [
                { label: t('overlay.panel.budget'), value: formatWholeEuros(canvasTotal.budget) },
                { label: t('overlay.panel.forecast'), value: forecastShare === null ? formatWholeEuros(canvasTotal.forecast) : t('overlay.panel.forecast_value', { amount: formatWholeEuros(canvasTotal.forecast), percentage: formatBasisPoints(forecastShare) }) },
                { label: t('overlay.panel.allocated'), value: counted(sidePanel.allocated) },
                { label: t('overlay.panel.unknown'), value: counted(sidePanel.unknown) },
            ],
        },
        { key: 'cost_kind', title: t('overlay.panel.per_cost_kind'), lines: costKinds.map((key) => ({ label: t(`cost_kind.${key}`), value: formatWholeEuros((breakdowns.costKind[key] ?? zero).budget) })) },
        { key: 'modality', title: t('overlay.panel.per_modality'), lines: MODALITIES.map((key) => ({ label: modalityLabel(key), value: formatWholeEuros((breakdowns.modality[key] ?? zero).budget) })) },
        {
            key: 'split',
            title: t('overlay.panel.split'),
            lines: [
                { label: t('overlay.detail.run'), value: formatWholeEuros(breakdowns.split.run) },
                { label: t('overlay.detail.change'), value: formatWholeEuros(breakdowns.split.change) },
                { label: t('overlay.detail.undetermined'), value: formatWholeEuros(breakdowns.split.undetermined) },
            ],
        },
    ];
}
</script>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { routes } from '../../routes';

defineProps<{ figures: CanvasFigures; costKinds: string[]; canvasId: number; year: number | null; canAssign: boolean }>();
</script>

<template>
    <aside :aria-label="t('overlay.panel.title')" class="w-full shrink-0 text-[13px] lg:w-[300px]" data-figure-panel>
        <h2 class="text-[15px] font-semibold">{{ t('overlay.panel.title') }}</h2>
        <div v-for="group in panelGroups(figures, costKinds)" :key="group.key" class="mt-3" :data-group="group.key">
            <h3 class="font-semibold">{{ group.title }}</h3>
            <dl>
                <div v-for="line in group.lines" :key="line.label" class="flex justify-between gap-3">
                    <dt>{{ line.label }}</dt>
                    <dd class="text-right">{{ line.value }}</dd>
                </div>
            </dl>
            <p v-if="group.key === 'totals'" class="mt-2" data-closing>{{ closingLine(figures) }}</p>
            <Link v-if="group.key === 'totals' && canAssign && figures.sidePanel.unknown.count > 0" :href="routes.allocation(canvasId, year ?? undefined)" class="mt-1 inline-block font-semibold text-[#1F4FB4] underline">{{ t('overlay.panel.assign') }}</Link>
        </div>
    </aside>
</template>

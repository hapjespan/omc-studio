<script lang="ts">
import { basisPointsOf, formatAmount, formatBasisPoints, formatTenths, formatWholeEuros, tenthsOf } from '../../format';
import { plural, t } from '../../i18n';
import type { CanvasFigures } from '../../canvas/overlay/figures';

export interface PanelLine {
    label: string;
    value: string;
    key?: string;
    count?: string;
    amount?: string;
    share?: string;
    bar?: number;
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
    const measured = (label: string, cents: number, key?: string): PanelLine => {
        const share = basisPointsOf(cents, canvasTotal.budget);
        return { label, key, value: formatWholeEuros(cents), share: share === null ? '-' : formatTenths(tenthsOf(cents, canvasTotal.budget) ?? 0), bar: share === null ? 0 : Math.min(100, Math.max(0, share / 100)) };
    };
    return [
        {
            key: 'totals',
            title: t('overlay.panel.totals'),
            lines: [
                { label: t('overlay.panel.budget'), value: formatWholeEuros(canvasTotal.budget) },
                { label: t('overlay.panel.forecast'), value: forecastShare === null ? formatWholeEuros(canvasTotal.forecast) : t('overlay.panel.forecast_value', { amount: formatWholeEuros(canvasTotal.forecast), percentage: formatBasisPoints(forecastShare) }) },
                { label: t('overlay.panel.allocated'), value: counted(sidePanel.allocated), count: plural('overlay.panel.count', sidePanel.allocated.count), amount: formatWholeEuros(sidePanel.allocated.budget) },
                { label: t('overlay.panel.unknown'), value: counted(sidePanel.unknown), count: plural('overlay.panel.count', sidePanel.unknown.count), amount: formatWholeEuros(sidePanel.unknown.budget) },
            ],
        },
        { key: 'cost_kind', title: t('overlay.panel.per_cost_kind'), lines: costKinds.map((key) => measured(t(`cost_kind.${key}`), (breakdowns.costKind[key] ?? zero).budget)) },
        { key: 'modality', title: t('overlay.panel.per_modality'), lines: MODALITIES.map((key) => measured(modalityLabel(key), (breakdowns.modality[key] ?? zero).budget, key)) },
        {
            key: 'split',
            title: t('overlay.panel.split'),
            lines: [measured(t('overlay.detail.run'), breakdowns.split.run), measured(t('overlay.detail.change'), breakdowns.split.change), measured(t('overlay.detail.undetermined'), breakdowns.split.undetermined)],
        },
    ];
}

export function allocatedBar(figures: CanvasFigures): { allocated: number; unknown: number } | null {
    const total = figures.canvasTotal.budget;
    if (total <= 0) return null;
    const allocated = Math.min(100, Math.max(0, (figures.sidePanel.allocated.budget / total) * 100));
    return { allocated, unknown: Math.max(0, 100 - allocated) };
}
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { ModalityColour } from '../../canvas/CanvasView.vue';
import ModalityMark from '../../components/ModalityMark.vue';
import Callout from '../../components/ui/Callout.vue';
import { routes } from '../../routes';

const props = defineProps<{ figures: CanvasFigures; costKinds: string[]; canvasId: number; year: number | null; canAssign: boolean; modalities: ModalityColour[] }>();
const bar = computed(() => allocatedBar(props.figures));
const colourOf = (modalities: ModalityColour[], key: string | undefined): ModalityColour | undefined => modalities.find((mark) => mark.key === key);
</script>

<template>
    <aside :aria-label="t('overlay.panel.title')" class="flex w-full shrink-0 flex-col gap-3.5 self-start rounded-panel border border-hairline bg-white p-4 text-[12.5px] lg:w-[334px]" data-figure-panel>
        <h2 class="sr-only">{{ t('overlay.panel.title') }}</h2>
        <template v-for="group in panelGroups(figures, costKinds)" :key="group.key">
            <div v-if="group.key === 'totals'" class="flex flex-col gap-3.5" :data-group="group.key">
                <div class="flex flex-col gap-0.5">
                    <p class="text-[11.5px] leading-[16.1px] text-muted">{{ year === null ? t('overlay.panel.caption_plain') : t('overlay.panel.caption', { year }) }}</p>
                    <p class="font-display text-[30px] font-bold leading-[42px] tracking-[-0.02em]" data-canvas-total>{{ group.lines[0].value }}</p>
                    <p class="text-[12px] text-secondary">{{ group.lines[1].label }} {{ group.lines[1].value }}</p>
                </div>
                <div v-if="bar" class="flex h-2.5 gap-0.5 overflow-hidden rounded-[5px]" role="img" :aria-label="t('overlay.panel.bar_label', { allocated: formatBasisPoints(Math.round(bar.allocated * 100)), unknown: formatBasisPoints(Math.round(bar.unknown * 100)) })" data-allocated-bar>
                    <div class="bg-ink" :style="{ width: `${bar.allocated}%` }" />
                    <div class="flex-1 bg-warning" />
                </div>
                <dl class="flex flex-col gap-1.5">
                    <div v-for="line in group.lines.slice(2)" :key="line.label" class="flex justify-between gap-2">
                        <dt>{{ line.label }} · {{ line.count }}</dt>
                        <dd class="font-semibold">{{ line.amount }}</dd>
                    </div>
                </dl>
                <Link v-if="canAssign && figures.sidePanel.unknown.count > 0" :href="routes.allocation(canvasId, year ?? undefined)" class="font-semibold text-ink underline">{{ t('overlay.panel.assign') }}</Link>
                <Callout tone="success" data-closing>{{ closingLine(figures) }}</Callout>
            </div>
            <div v-else class="flex flex-col gap-1.5 border-t border-hairline pt-3.5" :data-group="group.key">
                <h3 class="font-display text-[13px] font-semibold leading-[18.2px]">{{ group.title }}</h3>
                <div v-for="line in group.lines" :key="line.label" class="flex flex-col gap-[3px]">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex min-w-0 flex-1 items-center gap-1.5">
                            <ModalityMark v-if="colourOf(modalities, line.key)" :letter="colourOf(modalities, line.key)!.letter" :colour="colourOf(modalities, line.key)!.colour" decorative />{{ line.label }}
                        </span>
                        <span class="text-secondary">{{ line.share }}</span>
                        <span class="min-w-[74px] text-right font-semibold">{{ line.value }}</span>
                    </div>
                    <div class="h-1 rounded-sm bg-disabled" aria-hidden="true"><div class="h-1 rounded-sm bg-ink" :style="{ width: `${line.bar}%` }" /></div>
                </div>
            </div>
        </template>
    </aside>
</template>

<script lang="ts">
import { basisPointsOf, formatBasisPoints, formatWholeEuros } from '../../format';
import { t } from '../../i18n';
import type { ElementFigures, Money } from './figures';
import { hasCosts } from './figures';

export interface CardCounts {
    activities: number;
    clusters: number;
}

export type CardPart = 'counts' | 'forecast' | 'bar' | 'share' | 'total';

const HEIGHTS: Record<CardPart, number> = { total: 20, share: 16, bar: 12, forecast: 16, counts: 16 };
const DROP_ORDER: CardPart[] = ['counts', 'forecast', 'bar', 'share', 'total'];

export function fitParts(available: number): Set<CardPart> {
    const kept = new Set<CardPart>(DROP_ORDER);
    let used = DROP_ORDER.reduce((sum, part) => sum + HEIGHTS[part], 0);
    for (const part of DROP_ORDER) {
        if (used <= available) break;
        kept.delete(part);
        used -= HEIGHTS[part];
    }
    return kept;
}

export interface CardLines {
    empty: string | null;
    total: string;
    share: string;
    forecast: string;
    counts: string;
}

export function areaLine(total: Money, canvasTotal: Money): string {
    const share = basisPointsOf(total.budget, canvasTotal.budget);
    return t('overlay.area_total', { amount: formatWholeEuros(total.budget), percentage: share === null ? '-' : formatBasisPoints(share) });
}

export function cardLines(figures: ElementFigures, counts: CardCounts, year: number | null): CardLines {
    const { budget, forecast } = figures.total;
    const percentage = basisPointsOf(forecast, budget);
    return {
        empty: hasCosts(figures) ? null : t('overlay.empty', { year: year ?? '' }),
        total: t('overlay.total', { amount: formatWholeEuros(budget) }),
        share: t('overlay.share', { percentage: figures.shareBp === null ? '-' : formatBasisPoints(figures.shareBp) }),
        forecast: percentage === null ? t('overlay.forecast', { amount: formatWholeEuros(forecast) }) : t('overlay.forecast_line', { amount: formatWholeEuros(forecast), percentage: formatBasisPoints(percentage) }),
        counts: t('overlay.counts', { activities: counts.activities, clusters: counts.clusters }),
    };
}
</script>

<script setup lang="ts">
import { computed } from 'vue';
import type { DrawnBlock } from '../../layout/types';

const props = defineProps<{ block: DrawnBlock; figures: ElementFigures; counts: CardCounts; year: number | null; colour?: { background: string; text: string } | null }>();
const padding = 12;
const header = computed(() => props.block.textLines.length * props.block.style.lineHeight + padding);
const parts = computed(() => fitParts(props.block.drawnHeight - header.value - 4));
const lines = computed(() => cardLines(props.figures, props.counts, props.year));
const scale = computed(() => Math.max(props.figures.total.budget, props.figures.total.forecast, 1));
const fill = computed(() => `${(props.figures.total.forecast / scale.value) * 100}%`);
const stripe = computed(() => `${(props.figures.total.budget / scale.value) * 100}%`);
</script>

<template>
    <div class="absolute inset-x-0 bottom-0 overflow-hidden px-3 pb-1.5 text-[12px]" :style="{ top: `${header}px`, background: colour?.background ?? block.style.fill ?? 'transparent', color: colour?.text ?? block.style.textColour }" data-financial-card>
        <p v-if="lines.empty && parts.has('total')" class="leading-4" data-empty>{{ lines.empty }}</p>
        <template v-else-if="!lines.empty">
            <p v-if="parts.has('total')" class="truncate text-[16px] font-semibold leading-5" data-total>{{ lines.total }}</p>
            <p v-if="parts.has('share')" class="truncate leading-4" data-share>{{ lines.share }}</p>
            <div v-if="parts.has('bar')" class="relative mt-0.5 h-2 w-full rounded-sm" style="background: color-mix(in srgb, currentColor 25%, transparent)" data-bar>
                <div class="absolute inset-y-0 left-0 rounded-sm bg-current" :style="{ width: fill }" data-bar-fill />
                <div class="absolute inset-y-[-2px] w-0.5 bg-current" :style="{ left: stripe, boxShadow: `0 0 0 1px ${colour?.background ?? block.style.fill ?? '#FFFFFF'}` }" data-bar-budget />
            </div>
            <p v-if="parts.has('forecast')" class="truncate leading-4" data-forecast>{{ lines.forecast }}</p>
            <p v-if="parts.has('counts')" class="truncate leading-4" data-counts>{{ lines.counts }}</p>
        </template>
    </div>
</template>

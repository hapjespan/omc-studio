<script lang="ts">
import { basisPointsOf, formatBasisPoints, formatWholeEuros } from '../../format';
import { t } from '../../i18n';
import type { ElementFigures, Money } from './figures';
import { hasCosts } from './figures';

export interface CardCounts {
    activities: number;
    clusters: number;
}

export type CardPart = 'counts' | 'forecast' | 'bar';

const HEIGHTS: Record<CardPart, number> = { bar: 12, forecast: 16, counts: 16 };
const DROP_ORDER: CardPart[] = ['counts', 'forecast', 'bar'];

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
    header: string;
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
    const amount = formatWholeEuros(budget);
    return {
        empty: hasCosts(figures) ? null : t('overlay.empty', { year: year ?? '' }),
        header: figures.shareBp === null ? amount : t('overlay.card_header', { amount, percentage: formatBasisPoints(figures.shareBp) }),
        total: t('overlay.total', { amount }),
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
const header = computed(() => props.block.textLines.length * props.block.style.lineHeight + 12);
const available = computed(() => props.block.drawnHeight - header.value - 4);
const parts = computed(() => fitParts(available.value));
const lines = computed(() => cardLines(props.figures, props.counts, props.year));
const background = computed(() => props.colour?.background ?? props.block.style.fill ?? '#FFFFFF');
const text = computed(() => props.colour?.text ?? props.block.style.textColour);
const right = computed(() => (props.block.system ? 64 : props.block.modality ? 28 : 12));
const scale = computed(() => Math.max(props.figures.total.budget, props.figures.total.forecast, 1));
const fill = computed(() => `${(props.figures.total.forecast / scale.value) * 100}%`);
const stripe = computed(() => `max(0px, calc(${(props.figures.total.budget / scale.value) * 100}% - 2px))`);
</script>

<template>
    <div class="absolute inset-0 text-[12px]" :style="{ color: text }" data-financial-card>
        <p class="absolute top-1 truncate text-[12px] font-semibold leading-4" :style="{ right: `${right}px`, maxWidth: `calc(100% - ${right + 12}px)` }" data-total>{{ lines.header }}</p>
        <div class="absolute inset-x-0 bottom-0 overflow-hidden px-3 pb-1" :style="{ top: `${header}px`, background }" data-financial-body>
            <p v-if="lines.empty && available >= 16" class="leading-4" data-empty>{{ lines.empty }}</p>
            <template v-else-if="!lines.empty">
                <div v-if="parts.has('bar')" class="relative mt-0.5 h-2 w-full rounded-sm" style="background: color-mix(in srgb, currentColor 25%, transparent)" data-bar>
                    <div class="absolute inset-y-0 left-0 rounded-sm bg-current" :style="{ width: fill }" data-bar-fill />
                    <div class="absolute inset-y-[-2px] w-0.5 bg-current" :style="{ left: stripe, boxShadow: `0 0 0 1px ${background}` }" data-bar-budget />
                </div>
                <p v-if="parts.has('forecast')" class="truncate leading-4" data-forecast>{{ lines.forecast }}</p>
                <p v-if="parts.has('counts')" class="truncate leading-4" data-counts>{{ lines.counts }}</p>
            </template>
        </div>
    </div>
</template>

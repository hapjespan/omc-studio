<script lang="ts">
import { basisPointsOf, formatBasisPoints, formatWholeEuros } from '../../format';
import { plural, t } from '../../i18n';
import type { ElementFigures, Money } from './figures';
import { hasCosts } from './figures';

export interface CardCounts {
    activities: number;
    clusters: number;
}

export type CardPart = 'counts' | 'forecast' | 'bar';

export const AMOUNT_HEIGHT = 31;
export const OVER_BUDGET = '#9A7811';
const GAP = 6;
const RELAXED_GAP = 10;
const HEIGHTS: Record<CardPart, number> = { bar: 6, forecast: 20, counts: 16 };
const LEADING: Record<CardPart, number> = { bar: 1, forecast: 0, counts: 1 };
const DROP_ORDER: CardPart[] = ['counts', 'forecast', 'bar'];

const needed = (parts: CardPart[], gap: number): number => parts.reduce((sum, part) => sum + HEIGHTS[part] + LEADING[part] * gap, 0);

export const cardGap = (room: number): number => (RELAXED_GAP + AMOUNT_HEIGHT + needed(DROP_ORDER, RELAXED_GAP) <= room ? RELAXED_GAP : GAP);

export function fitParts(available: number, gap = GAP): Set<CardPart> {
    const kept = new Set<CardPart>(DROP_ORDER);
    let used = needed(DROP_ORDER, gap);
    for (const part of DROP_ORDER) {
        if (used <= available) break;
        kept.delete(part);
        used -= HEIGHTS[part] + LEADING[part] * gap;
    }
    return kept;
}

export interface CardLines {
    empty: string | null;
    header: string;
    amount: string;
    total: string;
    share: string;
    forecast: string;
    counts: string;
}

export interface AreaParts {
    amount: string;
    percentage: string;
}

export function areaParts(total: Money, canvasTotal: Money): AreaParts {
    const share = basisPointsOf(total.budget, canvasTotal.budget);
    return { amount: formatWholeEuros(total.budget), percentage: share === null ? '-' : formatBasisPoints(share) };
}

export const areaLine = (total: Money, canvasTotal: Money): string => t('overlay.area_total', { ...areaParts(total, canvasTotal) });

export const formatWholePercentage = (basisPoints: number): string => `${Math.round(basisPoints / 100)}%`;

export function countsText(counts: CardCounts): string {
    const parts = [counts.activities > 0 || counts.clusters === 0 ? plural('overlay.count.activities', counts.activities) : null, counts.clusters > 0 ? plural('overlay.count.clusters', counts.clusters) : null];
    return parts.filter((part) => part !== null).join(' · ');
}

export function cardLines(figures: ElementFigures, counts: CardCounts, year: number | null): CardLines {
    const { budget, forecast } = figures.total;
    const percentage = basisPointsOf(forecast, budget);
    const amount = formatWholeEuros(budget);
    return {
        empty: hasCosts(figures) ? null : t('overlay.empty', { year: year ?? '' }),
        header: figures.shareBp === null ? amount : t('overlay.card_header', { amount, percentage: formatBasisPoints(figures.shareBp) }),
        amount,
        total: t('overlay.total', { amount }),
        share: t('overlay.share', { percentage: figures.shareBp === null ? '-' : formatBasisPoints(figures.shareBp) }),
        forecast: percentage === null ? t('overlay.forecast', { amount: formatWholeEuros(forecast) }) : t('overlay.forecast_line', { amount: formatWholeEuros(forecast), percentage: formatWholePercentage(percentage) }),
        counts: countsText(counts),
    };
}
</script>

<script setup lang="ts">
import { computed } from 'vue';
import type { DrawnBlock } from '../../layout/types';

const INK = '#171B21';
const TRACK_HEADROOM = 1.2;

const props = defineProps<{ block: DrawnBlock; figures: ElementFigures; counts: CardCounts; year: number | null; colour?: { background: string; text: string } | null }>();
const top = computed(() => 13 + props.block.textLines.length * props.block.style.lineHeight);
const room = computed(() => props.block.drawnHeight - top.value - 13);
const gap = computed(() => cardGap(room.value));
const parts = computed(() => fitParts(room.value - gap.value - AMOUNT_HEIGHT, gap.value));
const lines = computed(() => cardLines(props.figures, props.counts, props.year));
const background = computed(() => props.colour?.background ?? props.block.style.fill ?? '#FFFFFF');
const text = computed(() => props.colour?.text ?? props.block.style.textColour);
const secondary = computed(() => (text.value.toUpperCase() === INK ? 'var(--color-secondary)' : text.value));
const scale = computed(() => Math.max(props.figures.total.budget * TRACK_HEADROOM, props.figures.total.forecast, 1));
const within = computed(() => `${(Math.min(props.figures.total.forecast, props.figures.total.budget) / scale.value) * 100}%`);
const over = computed(() => `${(Math.max(0, props.figures.total.forecast - props.figures.total.budget) / scale.value) * 100}%`);
const stripe = computed(() => `${(props.figures.total.budget / scale.value) * 100}%`);
</script>

<template>
    <div class="absolute inset-0" :style="{ color: text }" data-financial-card>
        <div class="absolute inset-x-px bottom-px flex flex-col overflow-hidden rounded-b-[7px] px-3" :style="{ top: `${top}px`, background, gap: `${gap}px`, paddingTop: `${gap}px` }" data-financial-body>
            <p v-if="lines.empty" class="text-[12px] leading-[16.8px]" :style="{ color: secondary }" data-empty>{{ lines.empty }}</p>
            <template v-else>
                <p class="flex items-baseline gap-2 whitespace-nowrap" data-total>
                    <span class="font-display text-[22px] font-bold leading-[30.8px] tracking-[-0.01em]" data-total-amount>{{ lines.amount }}</span>{{ ' ' }}<span class="min-w-0 truncate text-[12px] leading-[16.8px]" :style="{ color: secondary }" data-share>{{ lines.share }}</span>
                </p>
                <div v-if="parts.has('bar')" class="relative h-1.5 w-full shrink-0 rounded-[3px] bg-hairline" data-bar>
                    <div class="absolute inset-y-0 left-0 rounded-[3px] bg-current" :style="{ width: within }" data-bar-fill />
                    <div v-if="figures.total.forecast > figures.total.budget" class="absolute inset-y-0 rounded-r-[3px]" :style="{ left: within, width: over, background: OVER_BUDGET }" data-bar-over />
                    <div class="absolute -top-[3px] h-3 w-0.5 bg-current" :style="{ left: stripe }" data-bar-budget />
                </div>
                <p v-if="parts.has('forecast')" class="truncate text-[11.5px] leading-[16.1px]" :style="{ color: secondary }" data-forecast>{{ lines.forecast }}</p>
                <p v-if="parts.has('counts')" class="truncate text-[11.5px] leading-[16.1px]" :style="{ color: secondary }" data-counts>{{ lines.counts }}</p>
            </template>
        </div>
    </div>
</template>

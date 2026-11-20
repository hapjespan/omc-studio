import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { DrawnBlock, DrawnCanvas, LayoutArea, LayoutElement } from '../../layout/types';
import { t } from '../../i18n';
import { descendantCounts, modalityCounts } from './counts';
import type { ModalityCounts } from './counts';
import type { CanvasFigures, ElementFigures } from './figures';
import { areaParts, cardLines } from './FinancialCard.vue';
import type { AreaParts, CardCounts } from './FinancialCard.vue';
import { countsLine } from './ModalityOverlay.vue';
import { shareColour } from './ShareColour';

export interface OverlayInput {
    overlay: string;
    year: number | null;
    figures: CanvasFigures | null;
    areas: LayoutArea[];
    elements: LayoutElement[];
    letters: string[];
}

const NO_COUNTS: CardCounts = { activities: 0, clusters: 0 };

export function useOverlay(input: () => OverlayInput, drawn: Ref<DrawnCanvas | null>, selectedId: Ref<string | null>) {
    const costAreas = computed(() => new Set(input().areas.filter((area) => area.kind === 'activities').map((area) => area.key)));
    const domainCounts = computed(() => descendantCounts(input().elements));
    const domainModalities = computed(() => modalityCounts(input().elements, input().letters));
    const blocks = computed(() => new Map((drawn.value?.blocks ?? []).map((block) => [block.id, block])));

    const cardFigures = (block: DrawnBlock): ElementFigures | null => {
        const { figures } = input();
        return figures && block.kind === 'domain' && costAreas.value.has(block.areaKey) ? (figures.elements[block.id] ?? null) : null;
    };
    const modalityCard = (block: DrawnBlock): ModalityCounts | null =>
        input().overlay === 'modality' && block.kind === 'domain' && !block.system && costAreas.value.has(block.areaKey) ? (domainModalities.value.get(block.id) ?? { byLetter: Object.fromEntries(input().letters.map((letter) => [letter, 0])), none: 0 }) : null;
    const areaTotal = (block: DrawnBlock): AreaParts | null => {
        const { figures } = input();
        return figures && block.kind === 'area' && costAreas.value.has(block.areaKey) && figures.areas[block.id] ? areaParts(figures.areas[block.id], figures.canvasTotal) : null;
    };
    const cardColours = computed(() => Object.fromEntries([...blocks.value.values()].flatMap((block) => {
        const figures = cardFigures(block);
        return figures ? [[block.id, shareColour(figures.shareBp, block.style.fill)]] : [];
    })));

    const summaries = computed(() => {
        const map = new Map<string, string[]>();
        for (const block of blocks.value.values()) {
            const marks = modalityCard(block);
            const area = areaTotal(block);
            const figures = cardFigures(block);
            if (marks) map.set(block.id, [countsLine(marks, input().letters)]);
            else if (area) map.set(block.id, [t('overlay.area_total', { ...area })]);
            else if (figures) {
                const lines = cardLines(figures, domainCounts.value.get(block.id) ?? NO_COUNTS, input().year);
                map.set(block.id, lines.empty ? [lines.header, lines.empty] : [lines.total, lines.share, lines.forecast, lines.counts]);
            }
        }
        return map;
    });
    const figureLines = (id: string): string[] => summaries.value.get(id) ?? [];

    const inertIds: ComputedRef<Set<string>> = computed(() => {
        const covered = new Set<string>();
        if (!input().figures) return covered;
        const byId = new Map(input().elements.map((element) => [element.id, element]));
        for (const element of input().elements) {
            let root: LayoutElement | undefined = element;
            while (root?.parent) root = byId.get(root.parent);
            const block = root ? blocks.value.get(root.id) : undefined;
            if (root && root.id !== element.id && block && cardFigures(block)) covered.add(element.id);
        }
        return covered;
    });

    const selectedFigures = computed(() => {
        const element = input().elements.find((candidate) => candidate.id === selectedId.value);
        return input().figures && element && costAreas.value.has(element.area) ? (input().figures?.elements[element.id] ?? null) : null;
    });

    return { domainCounts, cardFigures, modalityCard, areaTotal, cardColours, figureLines, inertIds, selectedFigures, hoverBlock: (id: string | null): DrawnBlock | null => (id ? (blocks.value.get(id) ?? null) : null) };
}

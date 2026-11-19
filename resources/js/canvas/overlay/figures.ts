export interface Money {
    budget: number;
    forecast: number;
    run: number;
    change: number;
    undetermined: number;
}

export interface ElementFigures {
    direct: Money;
    total: Money;
    shareBp: number | null;
    fteHundredths: number;
}

export interface CountedAmount {
    budget: number;
    forecast: number;
    count: number;
}

export interface CanvasFigures {
    elements: Record<string, ElementFigures>;
    areas: Record<string, Money>;
    canvasTotal: Money;
    sidePanel: { allocated: CountedAmount; unknown: CountedAmount };
    breakdowns: { costKind: Record<string, Money>; modality: Record<string, Money>; split: { run: number; change: number; undetermined: number } };
}

export interface Contribution {
    id: string;
    name: string;
    amountCents: number;
    basisPoints: number;
}

export interface OverlayFilters {
    costKind: string | null;
    ownerId: number | string | null;
    applicationId: number | string | null;
}

export const hasCosts = (figures: ElementFigures): boolean => figures.total.budget !== 0 || figures.total.forecast !== 0;

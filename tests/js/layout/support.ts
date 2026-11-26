import type { AreaKey, AreaKind, CanvasLayoutInput, ElementKind, LayoutArea, LayoutElement } from '../../../resources/js/layout/types';

const areaSpec: [AreaKey, AreaKind, string][] = [
    ['direction', 'text', 'Koers'],
    ['steering', 'activities', 'Richten'],
    ['governing', 'activities', 'Beheersen'],
    ['actors', 'list', 'Actoren'],
    ['channels', 'list', 'Kanalen'],
    ['value_propositions', 'list', 'Waardeproposities'],
    ['primary_activities', 'activities', 'Primaire activiteiten'],
    ['supporting_activities', 'activities', 'Ondersteunende activiteiten'],
];

export const eightAreas: LayoutArea[] = areaSpec.map(([key, kind, label], index) => ({ key, kind, label, position: index + 1 }));

export const emptyInput = (): CanvasLayoutInput => ({
    areas: eightAreas,
    elements: [],
    statements: [],
    directionLabels: { mission: 'Missie', vision: 'Visie', goals: 'Strategische doelen' },
});

export const element = (id: string, area: AreaKey, kind: ElementKind, name: string, parent: string | null = null, position = 1, extra: Partial<LayoutElement> = {}): LayoutElement => ({
    id,
    area,
    kind,
    name,
    parent,
    position,
    modality: null,
    ...extra,
});

export const withElements = (elements: LayoutElement[]): CanvasLayoutInput => ({ ...emptyInput(), elements });

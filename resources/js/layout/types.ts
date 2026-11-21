import type { CanvasFontFamily } from './fonts';

export type AreaKey =
    | 'direction'
    | 'steering'
    | 'governing'
    | 'actors'
    | 'channels'
    | 'value_propositions'
    | 'primary_activities'
    | 'supporting_activities';

export type AreaKind = 'text' | 'activities' | 'list';

export type ElementKind = 'domain' | 'cluster' | 'activity';

export type BlockKind = 'area' | ElementKind;

export interface FontSpec {
    family: CanvasFontFamily;
    size: number;
    weight: number;
    italic: boolean;
    tracking?: number;
}

export interface TextMeasurer {
    width(text: string, font: FontSpec): number;
}

export interface LayoutArea {
    key: AreaKey;
    kind: AreaKind;
    position: number;
    label: string;
}

export interface LayoutElement {
    id: string;
    area: AreaKey;
    parent: string | null;
    kind: ElementKind;
    name: string;
    position: number;
    modality: string | null;
    unknownAllocation?: boolean;
}

export interface LayoutStatement {
    kind: 'mission' | 'vision' | 'goal';
    title: string | null;
    body: string;
    position: number;
}

export interface ElementStyle {
    fill?: string;
    fill_opacity?: number;
    border_colour?: string;
    border_width?: number;
    border_style?: 'solid' | 'dashed';
    corner_radius?: number;
    font?: 'inter' | 'space_grotesk';
    font_size?: number;
    font_weight?: 'normal' | 'bold';
    italic?: boolean;
    text_colour?: string;
    align_horizontal?: 'left' | 'center' | 'right';
    align_vertical?: 'top' | 'middle' | 'bottom';
}

export interface ManualRect {
    x: number;
    y: number;
    width: number;
    height: number;
    z: number;
}

export interface ElementLayout extends Partial<ManualRect> {
    mode?: 'manual' | 'automatic';
    style?: ElementStyle;
}

export interface CanvasLayoutInput {
    areas: LayoutArea[];
    elements: LayoutElement[];
    statements: LayoutStatement[];
    directionLabels: { mission: string; vision: string; goals: string };
    layouts?: Record<string, ElementLayout>;
}

export interface BlockStyle {
    fill: string | null;
    fillOpacity: number;
    border: string | null;
    borderColour: string;
    borderWidth: number;
    borderStyle: 'solid' | 'dashed';
    alignHorizontal: 'left' | 'center' | 'right';
    alignVertical: 'top' | 'middle' | 'bottom';
    textColour: string;
    labelColour: string;
    font: FontSpec;
    lineHeight: number;
    radius: number;
}

export interface DrawnBlock {
    id: string;
    kind: BlockKind;
    areaKey: AreaKey;
    parentId: string | null;
    x: number;
    y: number;
    width: number;
    drawnHeight: number;
    z: number;
    textLines: string[];
    modality: string | null;
    system: boolean;
    style: BlockStyle;
}

export type DirectionColumnKey = 'logo' | 'mission' | 'vision' | 'goals';

export interface LineMark {
    number: number;
    bold: number;
}

export interface DirectionColumn {
    key: DirectionColumnKey;
    x: number;
    width: number;
    label: string | null;
    lines: string[];
    truncated: boolean;
    marks: LineMark[];
}

export type LayoutWarning =
    | { kind: 'emptyCluster'; id: string; name: string; area: AreaKey }
    | { kind: 'domainWithoutActivities'; id: string; name: string; area: AreaKey }
    | { kind: 'beyondArtboard'; id: string; name: string; area: AreaKey }
    | { kind: 'adjusted'; id: string; name: string; area: AreaKey }
    | { kind: 'overlap'; id: string; name: string; otherId: string; other: string; area: AreaKey }
    | { kind: 'directionTextCut'; column: DirectionColumnKey };

export interface DrawnCanvas {
    blocks: DrawnBlock[];
    direction: DirectionColumn[];
    warnings: LayoutWarning[];
    beyondArtboardCount: number;
}

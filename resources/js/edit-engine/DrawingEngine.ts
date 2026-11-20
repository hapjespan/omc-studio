import type { DrawnCanvas, LayoutArea, LayoutElement } from '../layout';

export type LayoutStyle = Record<string, string | number | boolean>;

export interface LayoutRect {
    x: number;
    y: number;
    width: number;
    height: number;
    z: number;
    style?: LayoutStyle;
}

export interface SavedArea extends LayoutRect {
    style?: LayoutStyle;
}

export type SavedElement = { mode: 'automatic' } | ({ mode: 'manual'; style?: LayoutStyle } & LayoutRect);

export interface LayoutPayload {
    structure_version: number;
    layout_version: number;
    format_version: number;
    areas: Record<string, SavedArea>;
    elements: Record<string, SavedElement>;
}

export interface CanvasStructure {
    areas: LayoutArea[];
    elements: LayoutElement[];
}

export type RefusalReason = 'other_kind' | 'unknown_allocation' | 'name_taken' | 'cluster_needs_domain' | 'no_clusters_in_list';

export type MoveVerdict =
    | { valid: true; targetId: string; targetName: string; narrowed: boolean }
    | { valid: false; targetId: string; targetName: string; reason: RefusalReason };

export interface ShiftVerdictEvent {
    verdict: MoveVerdict;
    x: number;
    y: number;
}

export interface MountOptions {
    layout: LayoutPayload;
    structure?: CanvasStructure;
    onTextAttempt?: () => void;
    onPushBack?: (ids: string[]) => void;
    onMoveRefused?: (reason: RefusalReason, targetName: string) => void;
}

export interface PlacedMove {
    kind: 'move';
    elementId: string;
    targetId: string;
    layout: LayoutRect;
    narrowed: boolean;
}

export type MoveOrigin = 'drag' | 'panel' | 'undo' | 'redo';

export type MoveCommitMode = 'new' | 'undo' | 'redo';

export interface MoveIntent {
    kind: 'move';
    elementId: string;
    targetId: string;
    layout: LayoutRect | null;
    position?: number;
    narrowed?: boolean;
    origin: MoveOrigin;
}

export type FinishedAction = { kind: 'layout'; ids: string[] } | MoveIntent;

export interface BoxValues {
    x: number;
    y: number;
    width: number;
    height: number;
}

export type DrawingCommand =
    | { kind: 'setBox'; id: string; box: Partial<BoxValues> }
    | { kind: 'nudge'; dx: number; dy: number }
    | { kind: 'resizeBy'; dw: number; dh: number }
    | { kind: 'setGrid'; on: boolean }
    | { kind: 'align'; axis: 'horizontal' | 'vertical'; to: 'start' | 'centre' | 'end' }
    | { kind: 'distribute'; axis: 'horizontal' | 'vertical' }
    | { kind: 'equalWidth' }
    | { kind: 'equalHeight' }
    | { kind: 'bringForward' }
    | { kind: 'sendBackward' }
    | { kind: 'setStyle'; style: LayoutStyle }
    | { kind: 'pasteStyle'; style: LayoutStyle }
    | { kind: 'applyStyleToSiblings' }
    | { kind: 'resetToAutomatic'; scope: 'element' | 'domain' }
    | { kind: 'previewMove'; elementId: string; targetId: string }
    | { kind: 'commitMove'; elementId: string; targetId: string; layout: LayoutRect | null; mode: MoveCommitMode }
    | { kind: 'revertMove'; elementId: string }
    | { kind: 'cancelMoveHistory'; direction: 'undo' | 'redo' }
    | { kind: 'clearHistory' };

export interface DrawingEngine {
    mount(element: HTMLElement, scene: DrawnCanvas, options: MountOptions): Promise<void>;
    getLayout(): LayoutPayload;
    getSelection(): string[];
    getBox(id: string): BoxValues | null;
    canUndo(): boolean;
    canRedo(): boolean;
    onSelectionChange(listener: (ids: string[]) => void): () => void;
    onChange(listener: (change: FinishedAction) => void): () => void;
    onShiftVerdict(listener: (event: ShiftVerdictEvent | null) => void): () => void;
    select(ids: string[]): void;
    apply(command: DrawingCommand): void;
    undo(): void;
    redo(): void;
    zoomToFit(): void;
    getZoom(): number;
    zoomTo100(): void;
    getView(): { x: number; y: number; zoom: number };
    setTool(tool: 'select' | 'pan'): void;
    destroy(): void;
}

import type { LayoutPayload } from '../../edit-engine/DrawingEngine';
import { routes } from '../../routes';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'failed' | 'structureChanged' | 'layoutChanged';

export interface LayoutVersions {
    structure: number;
    layout: number;
}

export type SaveResult =
    | { outcome: 'saved'; layoutVersion: number }
    | { outcome: 'conflict'; conflict: 'structure_changed' | 'layout_changed' }
    | { outcome: 'refused'; messages: string[] }
    | { outcome: 'unreachable' };

export type LayoutSender = (canvas: string | number, payload: LayoutPayload) => Promise<SaveResult>;

export type MoveTask = (versions: LayoutVersions) => Promise<LayoutVersions>;

export type SaveFailure = { kind: 'refused'; messages: string[] } | { kind: 'unreachable' };

export interface SaveQueueOptions {
    canvas: string | number;
    versions: LayoutVersions;
    getLayout: () => LayoutPayload;
    onStatus?: (status: SaveStatus) => void;
    onFailure?: (failure: SaveFailure) => void;
    send?: LayoutSender;
}

interface LayoutAnswer {
    layout_version?: number;
    conflict?: 'structure_changed' | 'layout_changed';
    errors?: { rule?: string[] };
}

function xsrfToken(): string {
    return decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
}

export const sendLayout: LayoutSender = async (canvas, payload) => {
    const response = await fetch(routes.layout(canvas), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }).catch(() => null);

    if (response === null) return { outcome: 'unreachable' };

    const answer = (await response.json().catch(() => ({}))) as LayoutAnswer;

    if (response.ok && typeof answer.layout_version === 'number') return { outcome: 'saved', layoutVersion: answer.layout_version };
    if (response.status === 409 && answer.conflict !== undefined) return { outcome: 'conflict', conflict: answer.conflict };
    if (response.status === 422) return { outcome: 'refused', messages: answer.errors?.rule ?? [] };

    return { outcome: 'unreachable' };
};

type QueuedTask = { kind: 'save' } | { kind: 'move'; task: MoveTask };

export class SaveQueue {
    private readonly options: SaveQueueOptions;
    private readonly send: LayoutSender;
    private readonly pending: QueuedTask[] = [];
    private current: LayoutVersions;
    private state: SaveStatus = 'idle';
    private running = false;
    private paused = false;

    constructor(options: SaveQueueOptions) {
        this.options = options;
        this.send = options.send ?? sendLayout;
        this.current = { ...options.versions };
    }

    get status(): SaveStatus {
        return this.state;
    }

    get versions(): LayoutVersions {
        return { ...this.current };
    }

    changed(): void {
        if (this.pending[this.pending.length - 1]?.kind !== 'save') this.pending.push({ kind: 'save' });
        this.pump();
    }

    enqueueMove(task: MoveTask): void {
        this.pending.push({ kind: 'move', task });
        this.pump();
    }

    resume(): void {
        this.paused = false;
        this.pump();
    }

    discard(versions: LayoutVersions): void {
        this.pending.length = 0;
        this.current = { ...versions };
        this.paused = false;
        this.state = 'idle';
        this.options.onStatus?.('idle');
    }

    retry(): void {
        this.resume();
    }

    resolveStructureChange(structureVersion: number, layoutVersion: number): void {
        this.current = { structure: structureVersion, layout: layoutVersion };
        this.resume();
    }

    private pump(): void {
        if (this.running || this.paused || this.pending.length === 0) return;
        const next = this.pending.shift() as QueuedTask;
        this.running = true;
        this.set('saving');
        void (next.kind === 'save' ? this.runSave() : this.runMove(next.task));
    }

    private async runMove(task: MoveTask): Promise<void> {
        try {
            this.current = await task(this.versions);
        } catch {
            this.running = false;
            this.fail({ kind: 'unreachable' });
            return;
        }

        this.running = false;
        this.finish();
    }

    private async runSave(): Promise<void> {
        let result: SaveResult;
        try {
            result = await this.send(this.options.canvas, { ...this.options.getLayout(), structure_version: this.current.structure, layout_version: this.current.layout });
        } catch {
            result = { outcome: 'unreachable' };
        }
        this.running = false;

        if (result.outcome === 'saved') {
            this.current = { ...this.current, layout: result.layoutVersion };
            this.finish();
            return;
        }

        if (this.pending[0]?.kind !== 'save') this.pending.unshift({ kind: 'save' });

        if (result.outcome === 'conflict') {
            this.halt(result.conflict === 'structure_changed' ? 'structureChanged' : 'layoutChanged');
            return;
        }

        this.fail(result.outcome === 'refused' ? { kind: 'refused', messages: result.messages } : { kind: 'unreachable' });
    }

    private halt(status: SaveStatus): void {
        this.paused = true;
        this.set(status);
    }

    private fail(failure: SaveFailure): void {
        this.halt('failed');
        this.options.onFailure?.(failure);
    }

    private finish(): void {
        if (this.pending.length > 0) {
            this.pump();
            return;
        }

        this.set('saved');
    }

    private set(status: SaveStatus): void {
        if (status === this.state) return;
        this.state = status;
        this.options.onStatus?.(status);
    }
}

import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutPayload } from '../../../resources/js/edit-engine/DrawingEngine';
import { SaveQueue, sendLayout, type LayoutVersions, type SaveFailure, type SaveResult, type SaveStatus } from '../../../resources/js/pages/Drawing/SaveQueue';

class FakeSender {
    readonly payloads: LayoutPayload[] = [];
    private readonly replies: ((result: SaveResult) => void)[] = [];

    readonly send = (canvas: string | number, payload: LayoutPayload): Promise<SaveResult> => {
        this.canvases.push(canvas);
        this.payloads.push(payload);
        return new Promise<SaveResult>((resolve) => this.replies.push(resolve));
    };

    readonly canvases: (string | number)[] = [];

    get count(): number {
        return this.payloads.length;
    }

    async reply(result: SaveResult): Promise<void> {
        const resolve = this.replies.shift();
        if (resolve === undefined) throw new Error('no request in flight');
        resolve(result);
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();
    }

    async saved(layoutVersion: number): Promise<void> {
        await this.reply({ outcome: 'saved', layoutVersion });
    }
}

async function flush(): Promise<void> {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
}

function payload(width: number): LayoutPayload {
    return {
        structure_version: 0,
        layout_version: 0,
        format_version: 1,
        areas: { primary_activities: { x: 0, y: 200, width, height: 500, z: 0 } },
        elements: { e1: { mode: 'automatic' } },
    };
}

function open(width = 900) {
    const sender = new FakeSender();
    const statuses: SaveStatus[] = [];
    const failures: SaveFailure[] = [];
    const current = { width };
    const queue = new SaveQueue({
        canvas: 7,
        versions: { structure: 41, layout: 17 },
        getLayout: () => payload(current.width),
        onStatus: (status) => statuses.push(status),
        onFailure: (failure) => failures.push(failure),
        send: sender.send,
    });

    return { queue, sender, statuses, failures, current };
}

describe('SaveQueue', () => {
    it('sends the whole Layout with the versions it holds and reports saving then saved', async () => {
        const { queue, sender, statuses } = open();

        queue.changed();

        expect(sender.count).toBe(1);
        expect(sender.canvases).toEqual([7]);
        expect(sender.payloads[0]).toEqual({ ...payload(900), structure_version: 41, layout_version: 17 });
        expect(queue.status).toBe('saving');

        await sender.saved(18);

        expect(queue.status).toBe('saved');
        expect(statuses).toEqual(['saving', 'saved']);
    });

    it('keeps one request in flight and coalesces the actions finished meanwhile into one extra save with the returned version', async () => {
        const { queue, sender, statuses, current } = open();

        queue.changed();
        current.width = 910;
        queue.changed();
        current.width = 920;
        queue.changed();

        expect(sender.count).toBe(1);

        await sender.saved(18);

        expect(sender.count).toBe(2);
        expect(sender.payloads[1].layout_version).toBe(18);
        expect(sender.payloads[1].structure_version).toBe(41);
        expect(sender.payloads[1].areas.primary_activities.width).toBe(920);
        expect(statuses).toEqual(['saving']);

        await sender.saved(19);

        expect(sender.count).toBe(2);
        expect(statuses).toEqual(['saving', 'saved']);
        expect(queue.versions).toEqual({ structure: 41, layout: 19 });
    });

    it('runs a confirmed move and the saves around it one at a time in arrival order', async () => {
        const { queue, sender, current } = open();
        const seen: LayoutVersions[] = [];
        let finishMove: (versions: LayoutVersions) => void = () => {};

        queue.changed();
        queue.enqueueMove((versions) => {
            seen.push(versions);
            current.width = 930;
            return new Promise<LayoutVersions>((resolve) => {
                finishMove = resolve;
            });
        });
        queue.changed();

        expect(sender.count).toBe(1);
        expect(seen).toEqual([]);

        await sender.saved(18);

        expect(sender.count).toBe(1);
        expect(seen).toEqual([{ structure: 41, layout: 18 }]);

        finishMove({ structure: 42, layout: 19 });
        await flush();

        expect(sender.count).toBe(2);
        expect(sender.payloads[1].structure_version).toBe(42);
        expect(sender.payloads[1].layout_version).toBe(19);
        expect(sender.payloads[1].areas.primary_activities.width).toBe(930);

        await sender.saved(20);

        expect(queue.versions).toEqual({ structure: 42, layout: 20 });
        expect(queue.status).toBe('saved');
    });

    it('pauses on a newer structure version and sends the then-current Layout once the dialog resolves it', async () => {
        const { queue, sender, statuses, current } = open();

        queue.changed();
        await sender.reply({ outcome: 'conflict', conflict: 'structure_changed' });

        expect(queue.status).toBe('structureChanged');
        expect(statuses).toEqual(['saving', 'structureChanged']);

        current.width = 940;
        queue.changed();
        queue.enqueueMove(async () => ({ structure: 99, layout: 99 }));
        await flush();

        expect(sender.count).toBe(1);
        expect(queue.versions).toEqual({ structure: 41, layout: 17 });

        queue.resolveStructureChange(42, 19);

        expect(sender.count).toBe(2);
        expect(sender.payloads[1].structure_version).toBe(42);
        expect(sender.payloads[1].layout_version).toBe(19);
        expect(sender.payloads[1].areas.primary_activities.width).toBe(940);

        await sender.saved(20);

        expect(queue.versions).toEqual({ structure: 99, layout: 99 });
    });

    it('stops on a newer Layout version', async () => {
        const { queue, sender, statuses } = open();

        queue.changed();
        await sender.reply({ outcome: 'conflict', conflict: 'layout_changed' });

        expect(statuses).toEqual(['saving', 'layoutChanged']);

        queue.changed();
        await flush();

        expect(sender.count).toBe(1);
    });

    it('reports a refusal with its rule messages and sends the then-current Layout again only on retry', async () => {
        const { queue, sender, statuses, failures, current } = open();

        queue.changed();
        await sender.reply({ outcome: 'refused', messages: ['Domein Zorg: breedte minstens 40'] });

        expect(statuses).toEqual(['saving', 'failed']);
        expect(failures).toEqual([{ kind: 'refused', messages: ['Domein Zorg: breedte minstens 40'] }]);

        current.width = 950;
        queue.changed();
        await flush();

        expect(sender.count).toBe(1);

        queue.retry();

        expect(sender.count).toBe(2);
        expect(sender.payloads[1].areas.primary_activities.width).toBe(950);

        await sender.saved(18);

        expect(queue.status).toBe('saved');
        expect(statuses).toEqual(['saving', 'failed', 'saving', 'saved']);
    });

    it('reports an unreachable server and keeps the save for a retry', async () => {
        const { queue, sender, statuses, failures } = open();

        queue.changed();
        await sender.reply({ outcome: 'unreachable' });

        expect(statuses).toEqual(['saving', 'failed']);
        expect(failures).toEqual([{ kind: 'unreachable' }]);

        queue.retry();

        expect(sender.count).toBe(2);

        await sender.saved(18);

        expect(queue.versions).toEqual({ structure: 41, layout: 18 });
    });

    it('stops at a move that could not be sent and never repeats it', async () => {
        const { queue, sender, statuses, failures } = open();

        queue.enqueueMove(() => Promise.reject(new Error('offline')));
        queue.changed();
        await flush();

        expect(statuses).toEqual(['saving', 'failed']);
        expect(failures).toEqual([{ kind: 'unreachable' }]);
        expect(sender.count).toBe(0);

        queue.retry();

        expect(sender.count).toBe(1);
    });

    it('has no manual save, so no Save button can reach it', () => {
        const { queue } = open();

        expect(['save', 'flush', 'submit', 'saveNow'].filter((name) => name in queue)).toEqual([]);
    });
});

describe('the sender of SaveQueue', () => {
    const body = payload(900);

    function stub(answer: () => Promise<unknown>): { calls: [string, RequestInit][] } {
        const calls: [string, RequestInit][] = [];
        vi.stubGlobal('document', { cookie: 'other=1; XSRF-TOKEN=a%20token' });
        vi.stubGlobal('fetch', (url: string, init: RequestInit) => {
            calls.push([url, init]);
            return answer();
        });

        return { calls };
    }

    afterEach(() => vi.unstubAllGlobals());

    const answering = (status: number, json: unknown) => () => Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(json) });

    it('puts the whole Layout to the Canvas with the XSRF cookie and returns the new Layout version', async () => {
        const { calls } = stub(answering(200, { layout_version: 18 }));

        const result = await sendLayout(7, body);

        expect(result).toEqual({ outcome: 'saved', layoutVersion: 18 });
        expect(calls[0][0]).toBe('/canvases/7/layout');
        expect(calls[0][1].method).toBe('PUT');
        expect(calls[0][1].credentials).toBe('same-origin');
        expect(calls[0][1].headers).toEqual({ 'X-XSRF-TOKEN': 'a token', Accept: 'application/json', 'Content-Type': 'application/json' });
        expect(JSON.parse(String(calls[0][1].body))).toEqual(body);
    });

    it('reads a conflict, a refusal, an ended session and an unreachable server', async () => {
        stub(answering(409, { conflict: 'layout_changed' }));
        expect(await sendLayout(7, body)).toEqual({ outcome: 'conflict', conflict: 'layout_changed' });

        stub(answering(422, { errors: { rule: ['Domein Zorg: breedte minstens 40'] } }));
        expect(await sendLayout(7, body)).toEqual({ outcome: 'refused', messages: ['Domein Zorg: breedte minstens 40'] });

        stub(answering(419, {}));
        expect(await sendLayout(7, body)).toEqual({ outcome: 'unreachable' });

        stub(() => Promise.reject(new Error('offline')));
        expect(await sendLayout(7, body)).toEqual({ outcome: 'unreachable' });

        stub(answering(200, {}));
        expect(await sendLayout(7, body)).toEqual({ outcome: 'unreachable' });
    });

    it('is the sender a queue uses when none is given', async () => {
        const { calls } = stub(answering(200, { layout_version: 18 }));
        const queue = new SaveQueue({ canvas: 7, versions: { structure: 41, layout: 17 }, getLayout: () => body });

        queue.changed();
        await flush();
        await flush();

        expect(calls.length).toBe(1);
        expect(queue.versions).toEqual({ structure: 41, layout: 18 });
    });
});

describe('SaveQueue discard and robustness', () => {
    it('drops pending saves and carries the given versions after a reload', async () => {
        const sent: LayoutPayload[] = [];
        const results = [{ outcome: 'conflict', conflict: 'layout_changed' } as const, { outcome: 'saved', layoutVersion: 9 } as const];
        const queue = new SaveQueue({ canvas: 1, versions: { structure: 1, layout: 1 }, getLayout: () => payload(), send: async (_canvas, body) => (sent.push(body), results[sent.length - 1]) });
        queue.changed();
        await flush();
        expect(queue.status).toBe('layoutChanged');
        queue.discard({ structure: 3, layout: 8 });
        await flush();
        expect(sent).toHaveLength(1);
        expect(queue.status).toBe('idle');
        queue.changed();
        await flush();
        expect(sent[1]).toMatchObject({ structure_version: 3, layout_version: 8 });
    });

    it('fails instead of stalling when the sender throws', async () => {
        const failures: string[] = [];
        const queue = new SaveQueue({ canvas: 1, versions: { structure: 1, layout: 1 }, getLayout: () => payload(900), onFailure: (failure) => failures.push(failure.kind), send: async () => Promise.reject(new Error('down')) });
        queue.changed();
        await flush();
        expect(queue.status).toBe('failed');
        expect(failures).toEqual(['unreachable']);
    });
});

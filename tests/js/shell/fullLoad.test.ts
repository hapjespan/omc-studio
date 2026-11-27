import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const handlers: Array<(event: unknown) => void> = [];
const visit = vi.fn();
vi.mock('@inertiajs/vue3', () => ({ router: { on: (_: string, handler: (event: unknown) => void) => handlers.push(handler), visit: (...args: unknown[]) => visit(...args) } }));

const assign = vi.fn();
const { leaveGuards, loadDrawingWithFullPages, proceed } = await import('../../../resources/js/shell/fullLoad');

const fire = (url: string, method: string): { preventDefault: ReturnType<typeof vi.fn> } => {
    const event = { detail: { visit: { url, method, data: {} } }, preventDefault: vi.fn() };
    handlers.forEach((handler) => handler(event));

    return event;
};

beforeEach(() => {
    handlers.length = 0;
    vi.stubGlobal('window', { location: { pathname: '/canvases/1/drawing', assign } });
    loadDrawingWithFullPages();
});
afterEach(() => {
    leaveGuards.clear();
    vi.clearAllMocks();
});

describe('drawing full-page navigation', () => {
    it('leaves a sign-out POST to the router', () => {
        const event = fire('http://localhost/sign-out', 'post');
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(assign).not.toHaveBeenCalled();
    });

    it('turns a GET visit away from the drawing into a full page load', () => {
        const event = fire('http://localhost/canvases/1/view', 'get');
        expect(event.preventDefault).toHaveBeenCalled();
        expect(assign).toHaveBeenCalledWith('http://localhost/canvases/1/view');
    });

    it('hands a guard the original method', () => {
        const guard = vi.fn(() => true);
        leaveGuards.add(guard);
        fire('http://localhost/sign-out', 'post');
        expect(guard).toHaveBeenCalledWith({ url: 'http://localhost/sign-out', method: 'post', data: {} });
        expect(assign).not.toHaveBeenCalled();
    });

    it('proceeds with a held POST as a router visit and a held GET as a page load', () => {
        proceed({ url: 'http://localhost/sign-out', method: 'post', data: {} });
        expect(visit).toHaveBeenCalledWith('http://localhost/sign-out', { method: 'post', data: {} });
        expect(assign).not.toHaveBeenCalled();
        proceed({ url: 'http://localhost/x', method: 'get', data: {} });
        expect(assign).toHaveBeenCalledWith('http://localhost/x');
    });
});

import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const visit = vi.fn();
vi.mock('@inertiajs/vue3', () => ({ router: { on: vi.fn(), visit: (...args: unknown[]) => visit(...args) } }));

const { leaveGuards } = await import('../../../resources/js/shell/fullLoad');
const { createLeaveGuard } = await import('../../../resources/js/pages/Drawing/leaveGuard');

const signOut = { url: 'http://localhost/sign-out', method: 'post' as const, data: {} };

const setup = (initial: string) => {
    vi.stubGlobal('window', { addEventListener: vi.fn(), removeEventListener: vi.fn() });
    const status = ref(initial as never);
    const guard = createLeaveGuard(status, vi.fn());
    guard.install();

    return { status, guard, run: () => [...leaveGuards].some((g) => g(signOut)) };
};

afterEach(() => {
    vi.unstubAllGlobals();
    leaveGuards.clear();
    vi.clearAllMocks();
});

describe('leave guard', () => {
    it('lets a visit pass when saved', () => {
        expect(setup('saved').run()).toBe(false);
    });

    it('holds a visit during a save and hands it over once on saved', () => {
        const { guard, run } = setup('saving');
        expect(run()).toBe(true);
        expect(guard.target.value).toBeNull();
        expect(guard.destination()).toEqual(signOut);
        expect(guard.destination()).toBeNull();
    });

    it('opens the dialog when a held visit meets a failed save', () => {
        const { guard, run } = setup('saving');
        run();
        guard.failed();
        expect(guard.target.value).toEqual(signOut);
        expect(guard.destination()).toEqual(signOut);
    });

    it.each(['layoutChanged', 'structureChanged'] as const)('drops the held visit on %s so nothing is sent or shown', (conflict) => {
        const { guard, run } = setup('saving');
        run();
        expect(guard.settle(conflict)).toBeNull();
        expect(guard.target.value).toBeNull();
        expect(guard.settle('saved')).toBeNull();
    });

    it('opens the dialog on failed and keeps the visit', () => {
        const { guard, run } = setup('saving');
        run();
        expect(guard.settle('failed')).toBeNull();
        expect(guard.target.value).toEqual(signOut);
    });

    it.each(['idle', 'saving'] as const)('keeps the held visit on %s', (next) => {
        const { guard, run } = setup('saving');
        run();
        expect(guard.settle(next)).toBeNull();
        expect(guard.target.value).toBeNull();
        expect(guard.settle('saved')).toEqual(signOut);
    });

    it('hands a held visit over on saved', () => {
        const { guard, run } = setup('saving');
        run();
        expect(guard.settle('saved')).toEqual(signOut);
    });

    it('drops the held visit when staying, so a later save sends nothing', () => {
        const { guard, run } = setup('saving');
        run();
        guard.failed();
        guard.target.value = null;
        expect(guard.destination()).toBeNull();
    });

    it('does nothing on failure without a held visit', () => {
        const { guard } = setup('failed');
        guard.failed();
        expect(guard.target.value).toBeNull();
    });

    it('sends the single visit when discarding', () => {
        const { guard, run } = setup('saving');
        run();
        guard.failed();
        guard.discard();
        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit).toHaveBeenCalledWith(signOut.url, { method: 'post', data: {} });
    });
});

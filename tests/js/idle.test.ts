import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { startIdleTimer } from '../../resources/js/shell/idle';

const MINUTE = 60_000;

describe('idle timer', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('warns after 118 minutes without activity', () => {
        const warn = vi.fn();
        startIdleTimer(warn);
        vi.advanceTimersByTime(118 * MINUTE - 1);
        expect(warn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(warn).toHaveBeenCalledTimes(1);
    });

    it('restarts the countdown on activity', () => {
        const warn = vi.fn();
        const idle = startIdleTimer(warn);
        vi.advanceTimersByTime(100 * MINUTE);
        idle.touch();
        vi.advanceTimersByTime(117 * MINUTE);
        expect(warn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(MINUTE);
        expect(warn).toHaveBeenCalledTimes(1);
    });

    it('stops without warning', () => {
        const warn = vi.fn();
        startIdleTimer(warn).stop();
        vi.advanceTimersByTime(200 * MINUTE);
        expect(warn).not.toHaveBeenCalled();
    });
});

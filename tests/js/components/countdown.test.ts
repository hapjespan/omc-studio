import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Countdown } from '../../../resources/js/components/list/countdown';

describe('Countdown', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('finishes after its duration', () => {
        const done = vi.fn();
        new Countdown(10_000, done).start();

        vi.advanceTimersByTime(9_999);
        expect(done).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(done).toHaveBeenCalledOnce();
    });

    it('pauses while any hold is active and keeps the time left', () => {
        const done = vi.fn();
        const countdown = new Countdown(10_000, done);
        countdown.start();

        vi.advanceTimersByTime(4_000);
        countdown.hold('focus', true);
        countdown.hold('pointer', true);
        vi.advanceTimersByTime(60_000);
        expect(done).not.toHaveBeenCalled();

        countdown.hold('focus', false);
        vi.advanceTimersByTime(60_000);
        expect(done).not.toHaveBeenCalled();

        countdown.hold('pointer', false);
        vi.advanceTimersByTime(5_999);
        expect(done).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(done).toHaveBeenCalledOnce();
    });

    it('does not finish once stopped', () => {
        const done = vi.fn();
        const countdown = new Countdown(1_000, done);
        countdown.start();
        countdown.stop();

        vi.advanceTimersByTime(5_000);
        expect(done).not.toHaveBeenCalled();
    });
});

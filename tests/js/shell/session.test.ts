import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reauth, scheduleReauth } from '../../../resources/js/shell/session';

const twelveHours = 12 * 60 * 60;

const endsIn = (seconds: number) => Math.floor(Date.now() / 1000) + seconds;

beforeEach(() => {
    vi.useFakeTimers();
    reauth.value = false;
});

afterEach(() => {
    scheduleReauth(null);
    vi.useRealTimers();
});

describe('session deadline', () => {
    it('leaves the dialog closed until the last ten minutes of the period', () => {
        scheduleReauth(endsIn(twelveHours));

        vi.advanceTimersByTime((twelveHours - 660) * 1000);
        expect(reauth.value).toBe(false);

        vi.advanceTimersByTime(120_000);
        expect(reauth.value).toBe(true);
    });

    it('offers the dialog again after it was dismissed inside the last ten minutes', () => {
        scheduleReauth(endsIn(300));
        expect(reauth.value).toBe(true);

        reauth.value = false;
        vi.advanceTimersByTime(60_000);

        expect(reauth.value).toBe(true);
    });

    it('opens the dialog at once on a page opened inside the last ten minutes', () => {
        scheduleReauth(endsIn(300));

        vi.advanceTimersByTime(0);

        expect(reauth.value).toBe(true);
    });

    it('postpones the dialog when a renewed period arrives', () => {
        scheduleReauth(endsIn(twelveHours));

        vi.advanceTimersByTime((twelveHours - 700) * 1000);
        scheduleReauth(endsIn(twelveHours));
        vi.advanceTimersByTime(700 * 1000);

        expect(reauth.value).toBe(false);
    });

    it('keeps the dialog closed without a deadline', () => {
        scheduleReauth(null);

        vi.advanceTimersByTime(twelveHours * 1000);

        expect(reauth.value).toBe(false);
    });
});

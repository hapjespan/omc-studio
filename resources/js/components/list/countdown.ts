export class Countdown {
    private remaining: number;
    private startedAt = 0;
    private timer: ReturnType<typeof setTimeout> | undefined;
    private readonly holds = new Set<string>();
    private stopped = false;

    constructor(duration: number, private readonly onDone: () => void) {
        this.remaining = duration;
    }

    start(): void {
        if (this.stopped || this.timer !== undefined || this.holds.size > 0) return;
        this.startedAt = Date.now();
        this.timer = setTimeout(this.onDone, this.remaining);
    }

    hold(source: string, on: boolean): void {
        if (on) this.holds.add(source);
        else this.holds.delete(source);

        if (this.holds.size > 0 && this.timer !== undefined) {
            clearTimeout(this.timer);
            this.timer = undefined;
            this.remaining -= Date.now() - this.startedAt;
        } else if (this.holds.size === 0) {
            this.start();
        }
    }

    stop(): void {
        this.stopped = true;
        clearTimeout(this.timer);
        this.timer = undefined;
    }
}

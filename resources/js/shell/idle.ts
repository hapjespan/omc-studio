const IDLE_WARNING_MS = 118 * 60_000;

export function startIdleTimer(onWarn: () => void): { touch: () => void; stop: () => void } {
    let handle: ReturnType<typeof setTimeout> | undefined;

    function touch(): void {
        clearTimeout(handle);
        handle = setTimeout(onWarn, IDLE_WARNING_MS);
    }

    touch();

    return { touch, stop: () => clearTimeout(handle) };
}

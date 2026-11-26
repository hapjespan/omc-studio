import { execFileSync } from 'node:child_process';

const SCRIPT = `${process.cwd()}/tests/e2e/support/seed.php`;
const ATTEMPTS = 3;
const PAUSE_MS = 500;

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

const pause = (): void => void Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, PAUSE_MS);

export function marked(output: string, marker: string): string {
    const found = new RegExp(`${marker}=(.*)`).exec(output);

    if (found === null) throw new Error(`seed output without ${marker}=: ${output.slice(0, 1500)}`);

    return found[1].trim();
}

export function seedPhp(code: string): string {
    for (let attempt = 1; ; attempt++) {
        try {
            return execFileSync('php', [SCRIPT], { env, input: `${code};`, maxBuffer: 64 * 1024 * 1024, timeout: 60_000 }).toString();
        } catch (error) {
            if (attempt >= ATTEMPTS) {
                const stderr = (error as { stderr?: Buffer }).stderr?.toString().trim();
                throw new Error(`seed failed after ${ATTEMPTS} attempts: ${(stderr || String(error)).slice(0, 1500)}`);
            }
            pause();
        }
    }
}

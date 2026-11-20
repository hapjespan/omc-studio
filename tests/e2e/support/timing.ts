import { arch, cpus, platform, release, totalmem } from 'node:os';
import { expect } from '@playwright/test';
import { percentile } from './percentile';

export const PERFORMANCE_TAG = '@performance';
export const SAMPLES = 20;
export const BUDGET_MS = 1000;

const machine = (browser: string): string => `${platform()} ${release()} ${arch()}, ${cpus().length} x ${cpus()[0]?.model ?? 'cpu'}, ${Math.round(totalmem() / 2 ** 30)} GiB, ${browser}`;

export async function sample(measure: () => Promise<number>, count = SAMPLES): Promise<number[]> {
    const samples: number[] = [];

    for (let index = 0; index < count; index++) samples.push(Math.round(await measure()));

    return samples;
}

export function expectWithinBudget(samples: number[], label: string, browser: string): void {
    const sorted = [...samples].sort((left, right) => left - right);

    console.log(`machine: ${machine(browser)}; ${label}; ${samples.length} samples; median ${percentile(samples, 50)} ms; p95 ${percentile(samples, 95)} ms; max ${sorted[sorted.length - 1]} ms; all ${sorted.join(' ')}`);
    expect(percentile(samples, 95), `${label}: 95th percentile of ${samples.length} samples`).toBeLessThan(BUDGET_MS);
}

export function percentile(samples: number[], rank: number): number {
    if (samples.length === 0) throw new Error('percentile of no samples');

    const sorted = [...samples].sort((left, right) => left - right);

    return sorted[Math.ceil((rank / 100) * sorted.length) - 1] as number;
}

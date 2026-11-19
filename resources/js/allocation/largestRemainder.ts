export function largestRemainder(total: number, weights: number[]): number[] {
    const sum = weights.reduce((a, b) => a + b, 0);
    if (sum === 0) {
        return weights.map(() => 0);
    }
    const bigTotal = BigInt(total);
    const bigSum = BigInt(sum);
    const parts = weights.map((w) => Number((bigTotal * BigInt(w)) / bigSum));
    const remainders = weights.map((w) => Number((bigTotal * BigInt(w)) % bigSum));
    const order = weights.map((_, i) => i).sort((a, b) => remainders[b] - remainders[a] || a - b);
    const left = total - parts.reduce((a, b) => a + b, 0);
    order.slice(0, left).forEach((i) => parts[i]++);
    return parts;
}

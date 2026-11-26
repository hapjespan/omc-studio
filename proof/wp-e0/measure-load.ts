export interface LoadReport {
    readyAt: number;
    resources: { name: string; bytes: number }[];
    totalBytes: number;
    heapMegabytes: number | null;
}

export function collectLoad(readyAt: number): LoadReport {
    const resources = performance
        .getEntriesByType('resource')
        .map((entry) => ({ name: new URL(entry.name).pathname, bytes: (entry as PerformanceResourceTiming).encodedBodySize || (entry as PerformanceResourceTiming).transferSize }));
    const memory = (performance as unknown as { memory?: { usedJSHeapSize: number } }).memory;
    return {
        readyAt,
        resources,
        totalBytes: resources.reduce((sum, resource) => sum + resource.bytes, 0),
        heapMegabytes: memory ? +(memory.usedJSHeapSize / 1048576).toFixed(1) : null,
    };
}

export function scaledFixture<T extends { blocks: any[] }>(fixture: T, count: number): T {
    const columns = 20;
    const extra = Array.from({ length: count }, (_, index) => ({
        id: `bulk-${index}`,
        kind: 'activity',
        parent: null,
        x: 20 + (index % columns) * 82,
        y: 230 + Math.floor(index / columns) * 36,
        width: 76,
        fill: '#ffd166',
        text: `Item ${index}`,
        fontFamily: index % 2 ? 'Inter' : 'Space Grotesk',
        fontSize: 10,
        fontWeight: 400,
        textColor: '#111111',
        height: 30,
    }));
    return { ...fixture, blocks: [fixture.blocks[0], ...extra] };
}

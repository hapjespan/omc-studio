import { afterEach, describe, expect, it, vi } from 'vitest';

class FakeSessionStorage {
    private items = new Map<string, string>();

    get length(): number {
        return this.items.size;
    }

    getItem(key: string): string | null {
        return this.items.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.items.set(key, value);
    }

    removeItem(key: string): void {
        this.items.delete(key);
    }

    clear(): void {
        this.items.clear();
    }

    key(index: number): string | null {
        return this.names()[index] ?? null;
    }

    names(): string[] {
        return [...this.items.keys()];
    }
}

function stubBrowser(storage: FakeSessionStorage): void {
    const navigator = { userAgent: 'node' };
    vi.stubGlobal('navigator', navigator);
    vi.stubGlobal('document', {
        addEventListener() {},
        removeEventListener() {},
        createElement: () => ({ style: {}, setAttribute() {}, addEventListener() {} }),
    });
    vi.stubGlobal('window', {
        navigator,
        sessionStorage: storage,
        location: { href: 'http://localhost/' },
        history: { state: null },
        addEventListener() {},
        removeEventListener() {},
    });
}

describe('inertia history', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('clearHistory empties the stored history', async () => {
        const storage = new FakeSessionStorage();
        storage.setItem('historyKey', '[17,42,255]');
        storage.setItem('historyIv', '[1,2,3]');
        stubBrowser(storage);

        const { clearHistory } = await import('../../../resources/js/security/history');
        clearHistory();

        expect(storage.names()).toEqual([]);
    });
});

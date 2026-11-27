import { shallowReactive } from 'vue';

export class SelectionHistory {
    private readonly seen = shallowReactive(new Set<string>());

    get size(): number {
        return this.seen.size;
    }

    record(ids: string[]): void {
        for (const id of ids) this.seen.add(id);
    }

    unseen(ids: string[]): string[] {
        return ids.filter((id) => !this.seen.has(id));
    }
}

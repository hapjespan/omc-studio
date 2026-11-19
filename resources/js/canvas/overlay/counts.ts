import type { LayoutElement } from '../../layout/types';
import type { CardCounts } from './FinancialCard.vue';

export function descendantCounts(elements: LayoutElement[]): Map<string, CardCounts> {
    const byId = new Map(elements.map((element) => [element.id, element]));
    const counts = new Map<string, CardCounts>();
    for (const element of elements) {
        if (element.kind === 'domain') continue;
        let root: LayoutElement | undefined = element;
        while (root?.parent) root = byId.get(root.parent);
        if (!root) continue;
        const entry = counts.get(root.id) ?? { activities: 0, clusters: 0 };
        entry[element.kind === 'activity' ? 'activities' : 'clusters']++;
        counts.set(root.id, entry);
    }
    return counts;
}

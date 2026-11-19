import { largestRemainder } from './largestRemainder';

export const WHOLE = 10000;

export interface TargetNode {
    id: number | string;
    kind: 'domain' | 'cluster' | 'activity';
    children?: TargetNode[];
}

export function remaining(basisPoints: number[]): number {
    return WHOLE - basisPoints.reduce((a, b) => a + b, 0);
}

export function restToLine(basisPoints: number[], index: number): number[] {
    const rest = remaining(basisPoints);
    return basisPoints.map((value, i) => (i === index ? Math.max(0, value + rest) : value));
}

export function splitEvenly(count: number): number[] {
    return count > 0 ? largestRemainder(WHOLE, new Array<number>(count).fill(1)) : [];
}

export function activitiesOf(domain: TargetNode): TargetNode[] {
    return (domain.children ?? []).flatMap((child) => (child.kind === 'activity' ? [child] : activitiesOf(child)));
}

export function splitOverDomainActivities(domain: TargetNode): { id: number | string; basisPoints: number }[] {
    const activities = activitiesOf(domain);
    const shares = splitEvenly(activities.length);
    return activities.map((activity, i) => ({ id: activity.id, basisPoints: shares[i] }));
}

export function lineAmounts(totalCents: number, basisPoints: number[]): number[] {
    return largestRemainder(totalCents, basisPoints);
}

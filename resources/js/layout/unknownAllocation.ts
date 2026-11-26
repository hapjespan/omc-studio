import type { LayoutElement } from './types';

export const compareUnknownLast = (a: LayoutElement, b: LayoutElement): number => Number(!!a.unknownAllocation) - Number(!!b.unknownAllocation);

import type { FontSpec } from '../layout';

type Face = Pick<FontSpec, 'family' | 'weight'>;

const OFFSETS: Record<string, number> = {};

export const textOffset = (font: Face): number => OFFSETS[`${font.family}:${font.weight}`] ?? 0;

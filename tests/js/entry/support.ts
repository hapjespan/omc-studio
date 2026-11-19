import type { EntryElement } from '../../../resources/js/pages/Canvas/entry';
import { element } from '../layout/support';

export const entryElement = (...args: Parameters<typeof element>): EntryElement => ({ ...element(...args), description: null, rationale: null, icon: null, unknownAllocation: false, archived: false, ...args[6] });

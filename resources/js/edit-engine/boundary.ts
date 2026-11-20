import type { IconProvider } from '@open-pencil/core/icons';
import { fontManager } from '@open-pencil/core/text';

const WEB_FONT_PROVIDERS = { google: false, fontsource: false, bunny: false, fontshare: false };

export const bundledIcons: IconProvider = {
    search: async () => ({ icons: [], total: 0, collections: {} }),
    icons: async () => new Map(),
    previews: async () => new Map(),
    collections: async () => [],
    browse: async () => [],
};

export function closeNetworkBoundary(): void {
    fontManager.setOnlineFontProviders(WEB_FONT_PROVIDERS);
    fontManager.setWebFontFetch(null);
}

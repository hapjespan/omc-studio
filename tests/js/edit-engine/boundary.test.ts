import { readFileSync, readdirSync } from 'node:fs';
import { ESLint } from 'eslint';
import { fontManager } from '@open-pencil/core/text';
import { describe, expect, it, vi } from 'vitest';
import { bundledIcons, closeNetworkBoundary } from '../../../resources/js/edit-engine/boundary';

const ALLOWED_ENTRY_POINTS = ['@open-pencil/core/canvaskit', '@open-pencil/core/editor', '@open-pencil/core/icons', '@open-pencil/core/text', '@open-pencil/scene-graph', '@open-pencil/vue'];
const UNWANTED_PACKAGES = ['@open-pencil/cloud', '@open-pencil/mcp', '@open-pencil/cli', '@open-pencil/harness'];
const sample = "import { SceneGraph } from '@open-pencil/scene-graph';\nexport const graph = SceneGraph;\n";

function adapterImports(): string[] {
    const directory = 'resources/js/edit-engine';
    const found = readdirSync(directory).filter((name) => /\.(ts|vue)$/.test(name));
    return found.flatMap((name) => [...readFileSync(`${directory}/${name}`, 'utf8').matchAll(/from '(@open-pencil\/[^']+)'/g)].map((match) => match[1]));
}

describe('drawing library boundary', () => {
    it('keeps every online font source off', async () => {
        const fetcher = vi.fn();
        vi.stubGlobal('fetch', fetcher);
        expect(fontManager.enabledOnlineFontProviders().length).toBeGreaterThan(0);

        closeNetworkBoundary();

        expect(fontManager.enabledOnlineFontProviders()).toEqual([]);
        expect(await fontManager.loadRemoteFont('Roboto', 'Regular')).toBeNull();
        expect(fetcher).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('serves icons from the application itself, never from an outside source', async () => {
        const fetcher = vi.fn();
        vi.stubGlobal('fetch', fetcher);

        expect(await bundledIcons.search('home')).toEqual({ icons: [], total: 0, collections: {} });
        expect(await bundledIcons.collections()).toEqual([]);
        expect(await bundledIcons.browse('lucide')).toEqual([]);
        expect((await bundledIcons.icons(['lucide:home'], 16)).size).toBe(0);
        expect((await bundledIcons.previews(['lucide:home'])).size).toBe(0);
        expect(fetcher).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('pins the drawing library exactly and installs no cloud, command line or model package', () => {
        const manifest = JSON.parse(readFileSync('package.json', 'utf8'));
        const lock = JSON.parse(readFileSync('package-lock.json', 'utf8'));
        const pinned = Object.entries(manifest.dependencies as Record<string, string>).filter(([name]) => name.startsWith('@open-pencil/'));
        const resolved = Object.entries(lock.packages as Record<string, { version?: string }>)
            .filter(([path]) => /node_modules\/@open-pencil\/[^/]+$/.test(path))
            .map(([, entry]) => entry.version);

        expect(pinned.length).toBeGreaterThan(0);
        for (const [, version] of pinned) expect(version).toMatch(/^\d+\.\d+\.\d+$/);
        expect([...new Set(resolved)]).toEqual(['0.16.0']);
        for (const name of UNWANTED_PACKAGES) expect(manifest.dependencies[name]).toBeUndefined();
    });

    it('imports only the entry points that hold no telemetry, model, collaboration or cloud code', () => {
        const imports = adapterImports();

        expect(imports.length).toBeGreaterThan(0);
        for (const specifier of imports.filter((entry) => !/^@open-pencil\/core\/assets\/[\w-]+\.ttf\?url$/.test(entry))) expect(ALLOWED_ENTRY_POINTS).toContain(specifier);
    });

    it('lets only the adapter import the drawing library', { timeout: 30000 }, async () => {
        const eslint = new ESLint();

        const [outside] = await eslint.lintText(sample, { filePath: 'resources/js/canvas/fixture.ts' });
        const [subpath] = await eslint.lintText("import { createEditor } from '@open-pencil/core/editor';\nexport const create = createEditor;\n", { filePath: 'resources/js/canvas/fixture.ts' });
        const [inside] = await eslint.lintText(sample, { filePath: 'resources/js/edit-engine/fixture.ts' });

        expect(outside.messages.map((message) => message.ruleId)).toContain('no-restricted-imports');
        expect(subpath.messages.map((message) => message.ruleId)).toContain('no-restricted-imports');
        expect(inside.messages.map((message) => message.ruleId)).not.toContain('no-restricted-imports');
    });
});

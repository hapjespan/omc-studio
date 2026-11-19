import { readFileSync, readdirSync, statSync, writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const allowed = ['http://www.w3.org/2000/svg', 'http://www.w3.org/1999/xlink', 'http://www.w3.org/1998/Math/MathML', 'http://www.w3.org/1999/xhtml'];
const categories = [
    ['provider endpoints that would be requested if enabled', /fonts\.googleapis|fonts\.google\.com|fonts\.bunny|fontshare|fontsource|jsdelivr|typekit|iconify|unsplash|pexels/],
    ['AI provider key pages', /openrouter|anthropic|openai|aistudio|deepseek|docs\.z\.ai|minimax/],
    ['XML namespace identifiers', /www\.w3\.org/],
];

function files(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? files(path) : [path];
    });
}

export function report(root) {
    const found = new Map();
    for (const file of files(root)) {
        if (/\.(wasm|ttf)$/.test(file)) continue;
        for (const url of readFileSync(file, 'utf8').match(/https?:\/\/[^\s"'`)\\]+/g) ?? []) {
            if (allowed.some((prefix) => url === prefix || url.startsWith(prefix + '/'))) continue;
            found.set(url.replace(/\$\{[^}]*\}.*$/, ''), file.slice(root.length + 1));
        }
    }
    const grouped = {};
    for (const [url, file] of found) {
        const category = categories.find(([, pattern]) => pattern.test(url))?.[0] ?? 'documentation and example links';
        (grouped[category] ??= []).push(`${url} (${file})`);
    }
    return { total: found.size, grouped };
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const result = report(fileURLToPath(new URL('./dist-page/', import.meta.url)));
    const evidence = fileURLToPath(new URL('../../specs/001-omc-studio-v1/wp-e0-evidence/', import.meta.url));
    mkdirSync(evidence, { recursive: true });
    writeFileSync(evidence + 'p6-assets.json', JSON.stringify(result, null, 2));
    console.log(result.total, Object.fromEntries(Object.entries(result.grouped).map(([k, v]) => [k, v.length])));
    process.exit(result.total === 0 ? 0 : 1);
}

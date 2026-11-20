import { existsSync, readFileSync } from 'node:fs';
import { allFiles, relativeTo } from './walk.mjs';

const root = process.argv[2] ?? 'public/build';
const vendored = new Set(readFileSync(process.argv[3] ?? 'scripts/vendored-urls.txt', 'utf8').split('\n').filter(Boolean));
const sources = process.argv[4] ?? 'resources';
const identifiers = [
    'http://www.w3.org/2000/svg',
    'http://www.w3.org/1999/xlink',
    'http://www.w3.org/1998/Math/MathML',
    'http://www.w3.org/1999/xhtml',
    'https://vuejs.org/error-reference/',
    'https://tailwindcss.com',
    'http://localhost',
];

const isIdentifier = (url) => identifiers.some((identifier) => url.startsWith(identifier) && /^([/:#?]|$)/.test(url.slice(identifier.length)) || identifier.endsWith('/') && url.startsWith(identifier));

let failed = false;
function scan(dir, allowVendored) {
    if (!existsSync(dir)) {
        if (allowVendored) {
            console.error(`no build at ${dir}`);
            failed = true;
        }
        return;
    }
    for (const file of allFiles(dir)) {
        const urls = readFileSync(file, 'utf8').match(/https?:\/\/[^\s"'`)\\]+/g) ?? [];
        for (const url of urls) {
            if (isIdentifier(url) || (allowVendored && vendored.has(url.replace(/\$\{[^}]*\}.*$/, '')))) continue;
            console.error(`outside URL in ${relativeTo(dir, file)}: ${url}`);
            failed = true;
        }
    }
}

scan(root, true);
scan(sources, false);

if (failed) process.exit(1);
console.log('check-assets passed');

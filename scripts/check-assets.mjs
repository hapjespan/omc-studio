import { readFileSync } from 'node:fs';
import { allFiles, relativeTo } from './walk.mjs';

const root = process.argv[2] ?? 'public/build';
const identifiers = [
    'http://www.w3.org/2000/svg',
    'http://www.w3.org/1999/xlink',
    'http://www.w3.org/1998/Math/MathML',
    'http://www.w3.org/1999/xhtml',
    'https://vuejs.org/error-reference/',
    'https://tailwindcss.com',
    'http://localhost',
];

let failed = false;
for (const file of allFiles(root)) {
    const urls = readFileSync(file, 'utf8').match(/https?:\/\/[^\s"'`)\\]+/g) ?? [];
    for (const url of urls) {
        if (identifiers.some((identifier) => url.startsWith(identifier) && /^([/:#?]|$)/.test(url.slice(identifier.length)) || identifier.endsWith('/') && url.startsWith(identifier))) continue;
        console.error(`outside URL in ${relativeTo(root, file)}: ${url}`);
        failed = true;
    }
}

if (failed) process.exit(1);
console.log('check-assets passed');

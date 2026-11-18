import { readFileSync } from 'node:fs';
import { versionedFiles, relativeTo } from './walk.mjs';

const root = process.argv[2] ?? '.';
const adapter = 'resources/js/edit-engine/';
let failed = false;

for (const file of versionedFiles(root)) {
    const path = relativeTo(root, file);
    if (!/^resources\/js\/.*\.(ts|vue|js)$/.test(path) || path.startsWith(adapter)) continue;
    if (/from\s+['"]@open-pencil\/|import\(\s*['"]@open-pencil\/|require\(\s*['"]@open-pencil\//.test(readFileSync(file, 'utf8'))) {
        console.error(`@open-pencil import outside the adapter: ${path}`);
        failed = true;
    }
}

if (failed) process.exit(1);
console.log('check-imports passed');

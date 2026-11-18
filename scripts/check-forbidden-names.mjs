import { existsSync, readFileSync } from 'node:fs';
import { versionedFiles, relativeTo } from './walk.mjs';

const root = process.argv[2] ?? '.';
const listPath = process.argv[3] ?? process.env.FORBIDDEN_NAMES_FILE ?? '.forbidden-names';

if (!existsSync(listPath)) {
    console.log('overgeslagen: lijst verboden namen ontbreekt');
    process.exit(0);
}

const names = readFileSync(listPath, 'utf8').split('\n').map((line) => line.trim().toLowerCase()).filter(Boolean);
let failed = false;
for (const file of versionedFiles(root)) {
    let content;
    try {
        content = readFileSync(file, 'utf8').toLowerCase();
    } catch {
        continue;
    }
    const lowerPath = relativeTo(root, file).toLowerCase();
    for (const name of names) {
        if (content.includes(name) || lowerPath.includes(name)) {
            console.error(`forbidden name in ${relativeTo(root, file)}`);
            failed = true;
        }
    }
}

if (failed) process.exit(1);
console.log('check-forbidden-names passed');

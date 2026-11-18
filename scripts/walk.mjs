import { execFileSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const skipped = new Set(['node_modules', 'vendor', '.git', 'storage']);

function walk(dir, found = []) {
    for (const name of readdirSync(dir)) {
        if (skipped.has(name)) continue;
        const path = join(dir, name);
        if (statSync(path).isDirectory()) walk(path, found);
        else found.push(path);
    }
    return found;
}

export function versionedFiles(root) {
    try {
        const out = execFileSync('git', ['ls-files', '-z'], { cwd: root, stdio: ['ignore', 'pipe', 'ignore'], maxBuffer: 1 << 28 });
        return out.toString().split('\0').filter(Boolean).map((file) => join(root, file));
    } catch {
        return walk(root);
    }
}

export function allFiles(root) {
    return walk(root);
}

export function relativeTo(root, file) {
    return relative(root, file);
}

import { readFileSync } from 'node:fs';
import { strict as assert } from 'node:assert';
import { pathToFileURL } from 'node:url';

const exact = /^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/;

export function pinProblems(pkg, lock) {
    const problems = [];
    const declared = { ...pkg.dependencies, ...pkg.devDependencies, ...pkg.optionalDependencies };
    for (const [name, range] of Object.entries(declared)) {
        if (!exact.test(range)) problems.push(`not pinned exactly: ${name}@${range}`);
    }
    for (const [path, entry] of Object.entries(lock.packages ?? {})) {
        const name = path.replace(/^.*node_modules\//, '');
        if (!name.startsWith('@open-pencil/')) continue;
        if (!exact.test(entry.version ?? '')) problems.push(`lockfile version not exact: ${name}@${entry.version}`);
        if (declared[name] && declared[name] !== entry.version) {
            problems.push(`lockfile differs from the pin: ${name} declared ${declared[name]}, locked ${entry.version}`);
        }
    }
    return problems;
}

function selfCheck() {
    assert.equal(pinProblems({ dependencies: { a: '1.0.0' } }, { packages: {} }).length, 0);
    assert.equal(pinProblems({ dependencies: { a: '^1.0.0' } }, { packages: {} }).length, 1);
    assert.equal(pinProblems({ dependencies: { a: '~1.0.0' } }, { packages: {} }).length, 1);
    assert.equal(pinProblems({ dependencies: {} }, { packages: { 'node_modules/@open-pencil/core': { version: '0.16.0' } } }).length, 0);
    assert.equal(
        pinProblems({ dependencies: { '@open-pencil/core': '0.16.0' } }, { packages: { 'node_modules/@open-pencil/core': { version: '0.15.0' } } }).length,
        1,
    );
    console.log('check-pins self-check passed');
}

const runAsScript = process.argv[1] !== undefined && import.meta.url === pathToFileURL(process.argv[1]).href;

if (runAsScript && process.argv.includes('--self-check')) {
    selfCheck();
} else if (runAsScript) {
    const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8'));
    const lock = JSON.parse(readFileSync(new URL('./package-lock.json', import.meta.url), 'utf8'));
    const problems = pinProblems(pkg, lock);
    for (const problem of problems) console.error(problem);
    if (problems.length > 0) process.exit(1);
    const versions = Object.entries(lock.packages)
        .filter(([path]) => path.includes('node_modules/@open-pencil/'))
        .map(([path, entry]) => `${path.replace(/^.*node_modules\//, '')}@${entry.version}`)
        .sort();
    console.log(`check-pins passed, @open-pencil in the tree: ${versions.join(', ')}`);
}

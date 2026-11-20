import { spawnSync } from 'node:child_process';
import { appendFileSync } from 'node:fs';

const SKIPPED = 3;
const checks = [
    ['audit', 'scripts/check-audit.mjs'],
    ['assets', 'scripts/check-assets.mjs'],
    ['forbidden names', 'scripts/check-forbidden-names.mjs'],
    ['imports', 'scripts/check-imports.mjs'],
];

const results = checks.map(([label, script]) => {
    const run = spawnSync('node', [script], { stdio: 'inherit' });
    return { label, status: run.status === 0 ? 'passed' : run.status === SKIPPED ? 'skipped' : 'failed' };
});

const count = (status) => results.filter((result) => result.status === status).length;
const skipped = results.filter((result) => result.status === 'skipped').map((result) => result.label);
const summary = `checks: ${count('passed')} passed, ${count('skipped')} skipped${skipped.length ? ` (${skipped.join(', ')})` : ''}, ${count('failed')} failed`;
console.log(summary);
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${summary}\n`);

process.exit(count('failed') > 0 ? 1 : 0);

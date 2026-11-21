import { spawnSync } from 'node:child_process';
import { appendFileSync } from 'node:fs';

const SKIPPED = 3;
const checks = process.env.RUN_CHECKS_LIST
    ? JSON.parse(process.env.RUN_CHECKS_LIST)
    : [
          ['audit', 'scripts/check-audit.mjs'],
          ['assets', 'scripts/check-assets.mjs'],
          ['forbidden names', 'scripts/check-forbidden-names.mjs'],
          ['imports', 'scripts/check-imports.mjs'],
      ];
const allowed = (process.env.ALLOWED_SKIPS ?? '').split(',').map((label) => label.trim()).filter(Boolean);

const results = checks.map(([label, script]) => {
    const run = spawnSync('node', [script], { stdio: 'inherit' });
    if (run.status === 0) return { label, status: 'passed' };
    if (run.status === SKIPPED) return { label, status: allowed.includes(label) ? 'skipped' : 'unallowed skip' };

    return { label, status: 'failed' };
});

const count = (status) => results.filter((result) => result.status === status).length;
const names = (status) => results.filter((result) => result.status === status).map((result) => result.label);
const summary = [
    `checks: ${count('passed')} passed`,
    `${count('skipped')} skipped${count('skipped') ? ` (${names('skipped').join(', ')})` : ''}`,
    `${count('unallowed skip')} skipped without permission${count('unallowed skip') ? ` (${names('unallowed skip').join(', ')})` : ''}`,
    `${count('failed')} failed`,
].join(', ');
console.log(summary);
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${summary}\n`);

process.exit(count('failed') + count('unallowed skip') > 0 ? 1 : 0);

import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const accepted = {
    '@open-pencil/core': [],
    '@open-pencil/vue': [],
    pptxgenjs: [],
    'image-size': ['GHSA-5p2g-fcmc-qvqq', 'GHSA-w3rx-r6r6-pgpr'],
};

function load() {
    if (process.argv[2]) return readFileSync(process.argv[2], 'utf8');
    return spawnSync('npm', ['audit', '--json'], { encoding: 'utf8', maxBuffer: 1 << 26 }).stdout;
}

let vulnerabilities;
try {
    vulnerabilities = JSON.parse(load()).vulnerabilities;
} catch (error) {
    console.error(`audit report unreadable: ${error.message}`);
    process.exit(1);
}

const problems = [];
for (const [name, finding] of Object.entries(vulnerabilities ?? {})) {
    if (!['high', 'critical'].includes(finding.severity)) continue;
    if (!(name in accepted)) {
        problems.push(`${finding.severity} finding outside the accepted set: ${name}`);
        continue;
    }
    for (const via of finding.via) {
        const id = typeof via === 'object' ? via.url?.split('/').pop() : null;
        if (id && !accepted[name].includes(id)) problems.push(`new advisory on accepted package ${name}: ${id}`);
    }
}

for (const problem of problems) console.error(problem);
if (problems.length > 0) process.exit(1);
console.log('check-audit passed');

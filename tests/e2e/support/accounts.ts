import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function createUser(): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const password = `pw-${randomUUID()}`;
    execFileSync('php', ['artisan', 'tinker', `--execute=App\\Models\\User::factory()->create(['email' => '${email}', 'password' => '${password}']);`], { env });

    return { email, password };
}

export function createPendingAdministrator(): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const code = `$u = App\\Models\\User::factory()->administrator()->create(['email' => '${email}']); echo 'OTP=' . app(App\\Services\\Accounts\\OneTimePassword::class)->issueTo($u);`;
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();

    return { email, password: /OTP=(\S+)/.exec(output)?.[1] ?? '' };
}

export function oneTimePassword(): string {
    const log = execFileSync('cat', ['.scratch/e2e-install.log']).toString();

    const password = /One-time password: (\S+)/.exec(log)?.[1];
    if (!password) throw new Error('No one-time password in .scratch/e2e-install.log: run scripts/serve-e2e.sh to install a fresh database');

    return password;
}

export function installedAdministratorEmail(): string {
    const log = execFileSync('cat', ['.scratch/e2e-install.log']).toString();

    const email = /Administrator: (\S+)/.exec(log)?.[1];
    if (!email) throw new Error('No administrator e-mail in .scratch/e2e-install.log: run scripts/serve-e2e.sh to install a fresh database');

    return email;
}

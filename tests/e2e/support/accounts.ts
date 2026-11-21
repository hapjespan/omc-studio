import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function createUser(): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const password = `pw-${randomUUID()}`;
    execFileSync('php', ['artisan', 'tinker', `--execute=App\\Models\\User::factory()->create(['email' => '${email}', 'password' => '${password}']);`], { env });

    return { email, password };
}

export function oneTimePassword(): string {
    const log = execFileSync('cat', ['.scratch/e2e-install.log']).toString();

    return /One-time password: (\S+)/.exec(log)?.[1] ?? '';
}

export function firstAdministratorEmail(): string {
    const log = execFileSync('cat', ['.scratch/e2e-install.log']).toString();

    return /Administrator: (\S+)/.exec(log)?.[1] ?? '';
}

export type FirstAdministratorState = 'pending' | 'changed' | 'missing';

export function firstAdministratorState(): FirstAdministratorState {
    const code = `$u = App\\Models\\User::where('email', '${firstAdministratorEmail()}')->first(); echo 'STATE=' . ($u === null ? 'missing' : ($u->must_change_password ? 'pending' : 'changed'));`;

    return (/STATE=(\w+)/.exec(execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString())?.[1] ?? 'missing') as FirstAdministratorState;
}

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

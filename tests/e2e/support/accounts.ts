import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { seedPhp } from './seed';

export function createUser(): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const password = `pw-${randomUUID()}`;
    seedPhp(`App\\Models\\User::factory()->create(['email' => '${email}', 'password' => '${password}']);`);

    return { email, password };
}

export function createPendingAdministrator(): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const output = seedPhp(`$u = App\\Models\\User::factory()->administrator()->create(['email' => '${email}']); echo 'OTP=' . app(App\\Services\\Accounts\\OneTimePassword::class)->issueTo($u);`);

    return { email, password: /OTP=(\S+)/.exec(output)?.[1] ?? '' };
}

export function oneTimePassword(): string {
    const log = execFileSync('cat', ['.scratch/e2e-install.log']).toString();

    const password = /One-time password: (\S+)/.exec(log)?.[1];
    if (!password) throw new Error('No one-time password in .scratch/e2e-install.log: run scripts/serve-e2e.sh to install a fresh database');

    return password;
}

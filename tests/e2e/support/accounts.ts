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
    const code = `$u = App\\Models\\User::factory()->administrator()->create(['email' => '${email}']); echo 'OTP=' . app(App\\Services\\Accounts\\OneTimePassword::class)->issueTo($u);`;
    const output = seedPhp(code);

    return { email, password: /OTP=(\S+)/.exec(output)?.[1] ?? '' };
}

export function oneTimePassword(): string {
    const log = execFileSync('cat', ['.scratch/e2e-install.log']).toString();

    return /One-time password: (\S+)/.exec(log)?.[1] ?? '';
}

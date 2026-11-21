import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { seedPhp } from './seed';

export function createUser(): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const password = `pw-${randomUUID()}`;
    seedPhp(`App\\Models\\User::factory()->create(['email' => '${email}', 'password' => '${password}']);`);

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

    return (/STATE=(\w+)/.exec(seedPhp(code))?.[1] ?? 'missing') as FirstAdministratorState;
}

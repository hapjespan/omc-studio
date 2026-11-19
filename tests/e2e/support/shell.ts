import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function createAccount(role: 'administrator' | 'reader'): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const password = `pw-${randomUUID()}`;
    const state = role === 'administrator' ? '->administrator()' : '';
    execFileSync('php', ['artisan', 'tinker', `--execute=App\\Models\\User::factory()${state}->create(['email' => '${email}', 'password' => '${password}']);`], { env });

    return { email, password };
}

export async function signInAs(page: Page, role: 'administrator' | 'reader'): Promise<void> {
    const account = createAccount(role);
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(account.email);
    await page.getByLabel('Wachtwoord').fill(account.password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.getByRole('heading', { level: 1 }).waitFor();
}

export function createCanvas(): number {
    const code = "$c = App\\Models\\Canvas::factory()->create(); echo 'ID=' . $c->id;";
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

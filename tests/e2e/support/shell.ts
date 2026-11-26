import { randomUUID } from 'node:crypto';
import type { Page } from '@playwright/test';
import { marked, seedPhp } from './seed';

export function createAccount(role: 'administrator' | 'reader'): { email: string; password: string } {
    const email = `${randomUUID()}@example.test`;
    const password = `pw-${randomUUID()}`;
    const state = role === 'administrator' ? '->administrator()' : '';
    seedPhp(`App\\Models\\User::factory()${state}->create(['email' => '${email}', 'password' => '${password}']);`);

    return { email, password };
}

export async function signInAs(page: Page, role: 'administrator' | 'reader'): Promise<void> {
    const account = createAccount(role);
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(account.email);
    await page.getByLabel('Wachtwoord').fill(account.password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname !== '/sign-in');
    await page.getByRole('heading', { level: 1 }).waitFor();
}

const quoted = (value: string): string => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;

export function createCanvas(names?: { organisation: string; canvas: string }): number {
    const code = names
        ? `$o = App\\Models\\Organisation::factory()->create(['name' => ${quoted(names.organisation)}]); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id, 'name' => ${quoted(names.canvas)}]); echo 'ID=' . $c->id;`
        : "$c = App\\Models\\Canvas::factory()->create(); echo 'ID=' . $c->id;";
    const output = seedPhp(code);

    return Number(marked(output, 'ID'));
}

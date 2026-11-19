import { appendFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

test('records whether a Secure session cookie is accepted on the loopback origin', async ({ page, context, browserName }) => {
    await page.goto('/sign-in');

    const cookies = await context.cookies();
    const session = cookies.find((cookie) => cookie.httpOnly);
    const accepted = session !== undefined && session.secure;
    const line = `- ${browserName}: Secure session cookie ${accepted ? 'accepted' : 'refused'} on ${page.url()}\n`;

    if (process.env.EVIDENCE_FILE) appendFileSync(process.env.EVIDENCE_FILE, line);
    test.info().annotations.push({ type: 'secure-cookie', description: line.trim() });

    expect(session, 'the application must send a session cookie').toBeDefined();
    expect(session?.secure, 'the cookie must carry the Secure flag').toBe(true);
});

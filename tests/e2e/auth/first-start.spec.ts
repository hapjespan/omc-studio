import { randomUUID } from "node:crypto";
import type { Page } from "@playwright/test";
import { expectNoAxeViolations, expect, test } from "../support/guards";
import {
    createPendingAdministrator,
    oneTimePassword,
} from "../support/accounts";

const INSTALLED_EMAIL = process.env.ADMIN_EMAIL ?? "admin@localhost";

async function firstStart(
    page: Page,
    administrator: { email: string; password: string },
): Promise<void> {
    const chosen = `new-${randomUUID()}`;

    await page.goto("/sign-in");
    await expectNoAxeViolations(page);
    await page.getByLabel("E-mailadres").fill(administrator.email);
    await page.getByLabel("Wachtwoord").fill(administrator.password);
    await page.getByRole("button", { name: "Inloggen" }).click();

    await expect(
        page.getByRole("heading", { name: "Nieuw wachtwoord kiezen" }),
    ).toBeVisible();
    await page.goto("/");
    await expect(
        page.getByRole("heading", { name: "Nieuw wachtwoord kiezen" }),
    ).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByLabel("Wachtwoord", { exact: true }).fill("short");
    await page.getByLabel("Herhaal wachtwoord").fill("short");
    await page.getByRole("button", { name: "Wachtwoord opslaan" }).click();
    await expect(
        page.getByRole("link", {
            name: "Wachtwoord: gebruik minstens 12 tekens",
        }),
    ).toBeVisible();

    await page.getByLabel("Wachtwoord", { exact: true }).fill(chosen);
    await page.getByLabel("Herhaal wachtwoord").fill(chosen);
    await page.getByRole("button", { name: "Wachtwoord opslaan" }).click();
    await expect(
        page.getByRole("heading", { name: "Overzicht" }),
    ).toBeVisible();

    await page.getByRole("button", { name: "Administrator" }).click();
    await page.getByRole("menuitem", { name: "Uitloggen" }).click();
    await expect(page.getByRole("heading", { name: "Inloggen" })).toBeVisible();

    await page.getByLabel("E-mailadres").fill(administrator.email);
    await page.getByLabel("Wachtwoord").fill(chosen);
    await page.getByRole("button", { name: "Inloggen" }).click();
    await expect(
        page.getByRole("heading", { name: "Overzicht" }),
    ).toBeVisible();
}

test("clean start: sign in with the password printed by the installer, choose a new one, sign out, sign in again", async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name !== "chromium",
        "the installed account changes its password once per database",
    );
    test.slow();
    await firstStart(page, {
        email: INSTALLED_EMAIL,
        password: oneTimePassword(),
    });
});

test("first start with a pending administrator created through tinker", async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name === "chromium",
        "chromium walks the installed account",
    );
    test.slow();
    await firstStart(page, createPendingAdministrator());
});

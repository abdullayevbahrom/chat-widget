// @ts-check
import { test, expect } from '@playwright/test';

test.describe('User clickthrough', () => {
  test('tenant user flow boshidan oxirigacha ishlaydi', async ({ page }) => {
    const id = Date.now();
    const email = `user-flow-${id}@example.com`;
    const password = `UserFlow${id}!`;
    const projectName = `Project ${id}`;
    const domainName = `flow-${id}.example.com`;

    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Widget.*Real-time|ChatWidget/);
    await page.getByRole('link', { name: /Get Started/i }).first().click();

    await expect(page).toHaveURL(/\/auth\/register$/);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.getByRole('button', { name: 'Create Account' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('text=Total Projects')).toBeVisible();
    await expect(page.locator('text=Total Conversations')).toBeVisible();

    await page.getByRole('link', { name: 'Projects' }).click();
    await expect(page).toHaveURL(/\/dashboard\/projects$/);
    await page.getByRole('link', { name: /New Project|Create First Project/ }).first().click();

    await expect(page).toHaveURL(/\/dashboard\/projects\/create$/);
    await page.locator('#name').fill(projectName);
    await page.locator('#width').fill('420');
    await page.locator('#height').fill('640');
    await page.locator('#primary_color').fill('#4f46e5');
    await page.getByRole('button', { name: /Create Project|Save Project|Create/i }).click();

    await expect(page).toHaveURL(/\/dashboard\/projects\/\d+\/edit$/);
    await expect(page.locator('text=Project created successfully')).toBeVisible();
    await page.getByRole('link', { name: 'Cancel' }).click();

    await expect(page).toHaveURL(/\/dashboard\/projects$/);
    await expect(page.locator('tr', { hasText: projectName })).toBeVisible();
    await page.locator('tr', { hasText: projectName }).getByTitle('Edit').click();

    await expect(page).toHaveURL(/\/dashboard\/projects\/\d+\/edit$/);
    await page.getByRole('button', { name: 'Regenerate Key' }).click();
    await expect(page.locator('text=Widget key regenerated successfully')).toBeVisible();
    await page.getByRole('link', { name: 'Cancel' }).click();

    await expect(page).toHaveURL(/\/dashboard\/projects$/);
    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('tr', { hasText: projectName }).getByTitle('Delete').click();
    await expect(page).toHaveURL(/\/dashboard\/projects$/);

    await page.getByRole('link', { name: 'Domains' }).click();
    await expect(page).toHaveURL(/\/dashboard\/tenant-domains$/);
    await page.getByRole('link', { name: /Add Domain|Add First Domain/ }).first().click();

    await expect(page).toHaveURL(/\/dashboard\/tenant-domains\/create$/);
    await page.locator('input[name="domain"]').fill(domainName);
    await page.locator('textarea[name="notes"]').fill('Playwright domain flow');
    await page.getByRole('button', { name: /Add Domain|Save/i }).click();

    await expect(page).toHaveURL(/\/dashboard\/tenant-domains$/);
    await expect(page.locator('tr', { hasText: domainName })).toBeVisible();
    await page.locator('tr', { hasText: domainName }).getByTitle('Verify Domain').click();
    await expect(page.locator('text=Verification token generated')).toBeVisible();

    await page.locator('tr', { hasText: domainName }).getByTitle('Edit').click();
    await expect(page).toHaveURL(/\/dashboard\/tenant-domains\/\d+\/edit$/);
    await page.getByRole('link', { name: 'Cancel' }).click();

    await expect(page).toHaveURL(/\/dashboard\/tenant-domains$/);
    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('tr', { hasText: domainName }).getByTitle('Delete').click();
    await expect(page).toHaveURL(/\/dashboard\/tenant-domains$/);

    await page.getByRole('link', { name: 'Settings' }).click();
    await expect(page).toHaveURL(/\/dashboard\/tenant-profile$/);
    await page.locator('#company_name').fill(`Company ${id}`);
    await page.locator('#contact_email').fill(`contact-${id}@example.com`);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page).toHaveURL(/\/dashboard\/tenant-profile$/);
    await expect(page.locator('text=Profile updated successfully')).toBeVisible();

    await page.getByRole('link', { name: 'Telegram Bot' }).click();
    await expect(page).toHaveURL(/\/dashboard\/telegram-bot-settings$/);
    await page.locator('#chat_id').fill(`12345${id}`);
    await page.getByRole('button', { name: 'Save Settings' }).click();
    await expect(page).toHaveURL(/\/dashboard\/telegram-bot-settings$/);
    await expect(page.locator('text=Telegram bot settings updated successfully')).toBeVisible();

    await page.getByRole('link', { name: 'Conversations' }).click();
    await expect(page).toHaveURL(/\/dashboard\/conversations/);
    await expect(page.getByRole('heading', { name: 'Conversations', exact: true })).toBeVisible();

    await page.getByTitle('Logout').click();
    await expect(page).toHaveURL(/\/auth\/login$/);
  });
});

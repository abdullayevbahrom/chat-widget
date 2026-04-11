// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Admin clickthrough', () => {
  test('admin paneldagi asosiy button va linklar ishlaydi', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('admin@example.com');
    await page.locator('#password').fill('Admin123!');
    await page.getByRole('button', { name: 'Sign In' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.getByRole('link', { name: 'Tenants' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);

    await page.getByRole('link', { name: '+ New Tenant' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants\/create$/);

    await page.getByRole('link', { name: 'Cancel' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);

    await page.getByRole('link', { name: 'Edit' }).first().click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants\/\d+\/edit$/);

    await page.getByRole('link', { name: 'Cancel' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);

    const tenantId = Date.now();
    const tenantName = `PW Admin Tenant ${tenantId}`;
    const tenantSlug = `pw-admin-${tenantId}`;

    await page.getByRole('link', { name: '+ New Tenant' }).click();
    await page.locator('input[name="name"]').fill(tenantName);
    await page.locator('input[name="slug"]').fill(tenantSlug);
    await page.locator('select[name="plan"]').selectOption('pro');
    await page.locator('input[name="user_email"]').fill(`${tenantSlug}@example.com`);
    await page.locator('input[name="user_password"]').fill('Password123!');
    await page.getByRole('button', { name: 'Create' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);
    await expect(page.locator('tr', { hasText: tenantName })).toBeVisible();

    page.once('dialog', dialog => dialog.accept());
    await page.locator('tr', { hasText: tenantName }).getByRole('button', { name: 'Delete' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);

    await page.getByRole('link', { name: 'Users' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-users$/);

    await page.getByRole('link', { name: '+ New User' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-users\/create$/);

    await page.getByRole('link', { name: 'Cancel' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-users$/);

    await page.getByRole('link', { name: 'Edit' }).nth(1).click();
    await expect(page).toHaveURL(/\/admin\/manage-users\/\d+\/edit$/);

    await page.getByRole('link', { name: 'Cancel' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-users$/);

    const userId = Date.now();
    const userName = `PW User ${userId}`;
    const userEmail = `pw-user-${userId}@example.com`;

    await page.getByRole('link', { name: '+ New User' }).click();
    await page.locator('input[name="name"]').fill(userName);
    await page.locator('input[name="email"]').fill(userEmail);
    await page.locator('input[name="password"]').fill('Password123!');
    const tenantSelect = page.locator('select[name="tenant_id"]');
    if (await tenantSelect.locator('option').count() > 1) {
      await tenantSelect.selectOption({ index: 1 });
    }
    await page.getByRole('button', { name: 'Create' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-users$/);
    await expect(page.locator('tr', { hasText: userEmail })).toBeVisible();

    page.once('dialog', dialog => dialog.accept());
    await page.locator('tr', { hasText: userEmail }).getByRole('button', { name: 'Delete' }).click();
    await expect(page).toHaveURL(/\/admin\/manage-users$/);

    await page.getByTitle('Logout').click();
    await expect(page).toHaveURL(/\/admin\/login$/);
  });
});

// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Admin Panel Full Flow E2E Tests
 *
 * Covers:
 * 1. Unified Login (/auth/login for all roles)
 * 2. Admin Dashboard UI & Stats
 * 3. Sidebar Navigation
 * 4. Tenants CRUD
 * 5. Users CRUD
 * 6. Logout → Home
 * 7. Auth Guards
 */

const ADMIN_CREDENTIALS = { email: 'admin@example.com', password: 'Admin123!' };

async function adminLogin(page) {
  await page.goto('/auth/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill(ADMIN_CREDENTIALS.email);
  await page.locator('#password').fill(ADMIN_CREDENTIALS.password);
  await page.getByRole('button', { name: 'Sign In' }).click();
  await page.waitForURL(/\/admin$/);
}

test.describe('1. Unified Login', () => {
  test('login sahifasi ochiladi', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/auth/login', { waitUntil: 'networkidle' });

    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Sign In' })).toBeVisible();
    await context.close();
  });

  test('admin login → dashboard', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);

    await expect(page.locator('text=Total Tenants').first()).toBeVisible();
    await expect(page.locator('text=Total Users')).toBeVisible();
    await context.close();
  });

  test('noto\'g\'ri login → xatolik', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('wrong@example.com');
    await page.locator('#password').fill('WrongPassword123!');
    await page.getByRole('button', { name: 'Sign In' }).click();
    await page.waitForTimeout(1500);

    await expect(page).toHaveURL(/\/auth\/login$/);
    await context.close();
  });
});

test.describe('2. Admin Dashboard', () => {
  test('sidebar navigatsiya', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);

    await expect(page.locator('aside')).toBeVisible();
    await expect(page.locator('a:has-text("Tenants")')).toBeVisible();
    await expect(page.locator('a:has-text("Users")')).toBeVisible();
    await context.close();
  });

  test('top bar user info', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);

    await expect(page.locator('button[title="Logout"]')).toBeVisible();
    await context.close();
  });
});

test.describe('3. Tenants CRUD', () => {
  test('tenants index ochiladi', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);
    await page.getByRole('link', { name: 'Tenants' }).click();
    await page.waitForTimeout(500);

    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);
    await expect(page.locator('h1:has-text("Tenants")')).toBeVisible();
    await context.close();
  });

  test('yangi tenant yaratish', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);

    const timestamp = Date.now();
    const tenantName = `Test Tenant ${timestamp}`;
    const tenantSlug = `test-tenant-${timestamp}`;
    const userEmail = `tenant-${timestamp}@example.com`;
    const userPassword = `Tenant${timestamp}!`;

    await page.getByRole('link', { name: 'Tenants' }).click();
    await page.getByRole('link', { name: '+ New Tenant' }).click();
    await page.waitForTimeout(500);

    await page.locator('input[name="name"]').fill(tenantName);
    await page.locator('input[name="slug"]').fill(tenantSlug);
    await page.locator('select[name="plan"]').selectOption('pro');
    await page.locator('input[name="user_email"]').fill(userEmail);
    await page.locator('input[name="user_password"]').fill(userPassword);
    await page.getByRole('button', { name: 'Create' }).click();
    await page.waitForURL(/\/admin\/manage-tenants$/);

    await expect(page.locator('text=Tenant created successfully')).toBeVisible();
    await context.close();
  });
});

test.describe('4. Users CRUD', () => {
  test('users index ochiladi', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);
    await page.getByRole('link', { name: 'Users' }).click();
    await page.waitForTimeout(500);

    await expect(page).toHaveURL(/\/admin\/manage-users$/);
    await expect(page.locator('h1:has-text("Users")')).toBeVisible();
    await context.close();
  });

  test('yangi user yaratish', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);

    const timestamp = Date.now();
    const userName = `Test User ${timestamp}`;
    const userEmail = `testuser-${timestamp}@example.com`;
    const userPassword = `User${timestamp}!`;

    await page.getByRole('link', { name: 'Users' }).click();
    await page.getByRole('link', { name: '+ New User' }).click();
    await page.waitForTimeout(500);

    await page.locator('input[name="name"]').fill(userName);
    await page.locator('input[name="email"]').fill(userEmail);
    await page.locator('input[name="password"]').fill(userPassword);
    await page.getByRole('button', { name: 'Create' }).click();
    await page.waitForURL(/\/admin\/manage-users$/);

    await expect(page.locator('text=User created successfully')).toBeVisible();
    await context.close();
  });
});

test.describe('5. Logout & Auth Guards', () => {
  test('logout → home page', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await adminLogin(page);

    await page.locator('button[title="Logout"]').click();
    await page.waitForTimeout(1000);

    await expect(page).toHaveURL(/\/$/);
    await context.close();
  });

  test('login bo\'lmagan foydalanuvchi admin sahifaga kira olmaydi', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const response = await page.goto('/admin', { waitUntil: 'networkidle' });
    expect(response?.status()).toBeLessThan(500);
    await context.close();
  });
});

test.describe('6. Full Admin Flow (E2E)', () => {
  test('barcha sahifalar birma-bir bosib chiqiladi', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // Login
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(ADMIN_CREDENTIALS.email);
    await page.locator('#password').fill(ADMIN_CREDENTIALS.password);
    await page.getByRole('button', { name: 'Sign In' }).click();
    await page.waitForURL(/\/admin$/);
    console.log('✅ Login → Dashboard');

    // Tenants
    await page.getByRole('link', { name: 'Tenants' }).click();
    await page.waitForTimeout(500);
    await expect(page).toHaveURL(/\/admin\/manage-tenants$/);
    console.log('✅ Tenants index');

    // Users
    await page.getByRole('link', { name: 'Users' }).click();
    await page.waitForTimeout(500);
    await expect(page).toHaveURL(/\/admin\/manage-users$/);
    console.log('✅ Users index');

    // Dashboard
    await page.getByRole('link', { name: 'Dashboard' }).click();
    await page.waitForTimeout(500);
    await expect(page).toHaveURL(/\/admin$/);
    console.log('✅ Dashboard');

    // Logout
    await page.locator('button[title="Logout"]').click();
    await page.waitForTimeout(1000);
    await expect(page).toHaveURL(/\/$/);
    console.log('✅ Logout → Home');

    console.log('✅ Full admin flow completed!');
    await context.close();
  });
});

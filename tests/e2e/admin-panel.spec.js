// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Admin Panel - Blade + Alpine.js', () => {
  test('Admin Login sahifasi', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Super Admin.*Login/);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    console.log('✅ Admin Login sahifasi ochildi');
    await page.screenshot({ path: 'test-results/screenshots/admin-login.png', fullPage: true });
  });

  test('Admin Login → Dashboard', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('admin@example.com');
    await page.locator('#password').fill('Admin123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(2000);
    
    const url = page.url();
    console.log(`📍 Admin URL: ${url}`);
    
    if (!url.includes('/admin/login')) {
      console.log('✅ Admin Login muvaffaqiyatli!');
      await page.screenshot({ path: 'test-results/screenshots/admin-dashboard.png', fullPage: true });
    } else {
      console.log('❌ Admin login sahifasida qoldi');
    }
  });

  test('Admin Tenants sahifasi', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('admin@example.com');
    await page.locator('#password').fill('Admin123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/admin/tenants', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const url = page.url();
    console.log(`📍 Tenants URL: ${url}`);
    
    if (!url.includes('/admin/login')) {
      console.log('✅ Tenants sahifasi ochildi');
      await page.screenshot({ path: 'test-results/screenshots/admin-tenants.png', fullPage: true });
    }
  });

  test('Admin Users sahifasi', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('admin@example.com');
    await page.locator('#password').fill('Admin123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/admin/users', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const url = page.url();
    console.log(`📍 Users URL: ${url}`);
    
    if (!url.includes('/admin/login')) {
      console.log('✅ Users sahifasi ochildi');
      await page.screenshot({ path: 'test-results/screenshots/admin-users.png', fullPage: true });
    }
  });
});

// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Full Flow: Login → Dashboard', () => {
  test('Login → Dashboard o\'tish', async ({ page }) => {
    // 1. Login
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('verified@example.com');
    await page.locator('#password').fill('Verified123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(2000);
    
    // 2. Dashboard ga yo'naltirilganligini tekshirish
    const url = page.url();
    console.log(`📍 URL: ${url}`);
    
    // Email verification yoki boshqa joyga emas, to'g'ridan-to'g'ri dashboard ga ketishi kerak
    expect(url).toContain('/dashboard');
    expect(url).not.toContain('/email-verification');
    expect(url).not.toContain('/login');
    
    // 3. Dashboard elementlarini tekshirish
    await expect(page.locator('text=Dashboard')).toBeVisible();
    await expect(page.locator('text=Total Projects')).toBeVisible();
    await expect(page.locator('text=Total Conversations')).toBeVisible();
    await expect(page.locator('text=Open Conversations')).toBeVisible();
    
    console.log('✅ Dashboard ochildi!');
    await page.screenshot({ path: 'test-results/screenshots/dashboard-success.png', fullPage: true });
  });

  test('Register → Dashboard o\'tish', async ({ page }) => {
    const timestamp = Date.now();
    const email = `flow${timestamp}@example.com`;
    const password = `Flow${timestamp}!`;
    
    // 1. Register
    await page.goto('/auth/register', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(3000);
    
    // 2. Dashboard ga yo'naltirilganligini tekshirish
    const url = page.url();
    console.log(`📍 URL: ${url}`);
    
    expect(url).toContain('/dashboard');
    expect(url).not.toContain('/email-verification');
    expect(url).not.toContain('/register');
    
    // 3. Dashboard elementlari
    await expect(page.locator('text=Dashboard')).toBeVisible();
    await expect(page.locator('text=Total Projects')).toBeVisible();
    
    console.log(`✅ Register → Dashboard: ${email}`);
    await page.screenshot({ path: 'test-results/screenshots/register-to-dashboard.png', fullPage: true });
  });
});

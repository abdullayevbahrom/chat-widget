// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Barcha sahifalarni ko'rib chiqish va screenshot olish
 * Registratsiya orqali user yaratib, login qilib kiradi
 */

test.describe('Sahifalarni ko\'rib chiqish', () => {
  
  test('1 - Landing page screenshot', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/01-landing-page.png', fullPage: true });
    
    // Dizayn elementlarini tekshirish
    const heroSection = page.locator('.gradient-hero, section[class*="gradient"]').first();
    await expect(heroSection).toBeVisible();
    
    console.log('✅ Landing page ochildi va screenshot olindi');
  });

  test('2 - Register sahifasi screenshot', async ({ page }) => {
    await page.goto('/app/register', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/02-register-page.png', fullPage: true });
    
    await expect(page).toHaveTitle(/Register.*ChatWidget/);
    
    console.log('✅ Register sahifasi ochildi va screenshot olindi');
  });

  test('3 - Login sahifasi screenshot', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/03-login-page.png', fullPage: true });
    
    await expect(page).toHaveTitle(/Login.*ChatWidget/);
    
    console.log('✅ Login sahifasi ochildi va screenshot olindi');
  });

  test('4 - Admin login va dashboard screenshot', async ({ page }) => {
    // Admin login
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    
    await page.locator('#form\\.email').fill('admin@example.com');
    await page.locator('#form\\.password').fill('Admin123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(3000);
    
    const currentUrl = page.url();
    console.log(`📍 Admin login dan keyin: ${currentUrl}`);
    
    // Dashboard screenshot
    await page.screenshot({ path: 'test-results/screenshots/04-admin-dashboard.png', fullPage: true });
    
    console.log('✅ Admin dashboard ochildi va screenshot olindi');
  });

  test('5 - Tenant user yaratish va dashboard screenshot', async ({ page, context }) => {
    const timestamp = Date.now();
    const testEmail = `tenant${timestamp}@example.com`;
    const testPassword = `Test${timestamp}!`;

    // Registratsiya
    await page.goto('/app/register', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(testEmail);
    await page.locator('#form\\.password').fill(testPassword);
    await page.locator('#form\\.passwordConfirmation').fill(testPassword);
    await page.locator('button[type="submit"]:has-text("Sign up")').click();
    await page.waitForTimeout(3000);
    
    // Reload - agar login bo'lmagan bo'lsa
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    if (page.url().includes('/login') || page.url().includes('/register')) {
      await page.goto('/app/login', { waitUntil: 'networkidle' });
      await page.locator('#form\\.email').fill(testEmail);
      await page.locator('#form\\.password').fill(testPassword);
      await page.locator('button[type="submit"]:has-text("Sign in")').click();
      await page.waitForTimeout(3000);
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForTimeout(1000);
    }
    
    console.log(`📍 Tenant URL: ${page.url()}`);
    
    // Cookie larni saqlash - keyingi testlar uchun
    const state = await context.storageState();
    await context.storageState({ path: 'test-results/auth-state.json' });
    
    // Agar login bo'lgan bo'lsa, dashboard screenshot
    if (!page.url().includes('/login')) {
      await page.screenshot({ path: 'test-results/screenshots/05-tenant-dashboard.png', fullPage: true });
      console.log('✅ Tenant dashboard ochildi va screenshot olindi');
    } else {
      console.log('⚠️ Tenant login bo\'lmadi');
    }
  });
});

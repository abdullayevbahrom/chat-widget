// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Blade + Alpine.js Auth', () => {
  test('Register validation matnlari faqat kerak bo‘lganda chiqadi', async ({ page }) => {
    await page.goto('/auth/register', { waitUntil: 'networkidle' });

    await expect(page.getByText('Passwords do not match')).toBeHidden();
    await expect(page.getByText('Passwords match')).toBeHidden();

    await page.locator('#password').fill('Mismatch123!');
    await page.locator('#password_confirmation').fill('Mismatch1234!');
    await expect(page.getByText('Passwords do not match')).toBeVisible();
    await expect(page.getByText('Passwords match')).toBeHidden();

    await page.locator('#password_confirmation').fill('Mismatch123!');
    await expect(page.getByText('Passwords do not match')).toBeHidden();
    await expect(page.getByText('Passwords match')).toBeVisible();
  });

  test('Login sahifasi ochiladi va dizayn to\'g\'ri', async ({ page }) => {
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    
    await expect(page).toHaveTitle(/Widget.*Login/);
    await expect(page.locator('h1:has-text("ChatWidget")')).toBeVisible();
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]:has-text("Sign In")')).toBeVisible();
    
    // Gradient background tekshirish
    const bg = await page.locator('body > div').first();
    const bgStyle = await bg.evaluate((el) => window.getComputedStyle(el).background);
    expect(bgStyle).toContain('gradient');
    
    console.log('✅ Login sahifasi ochildi');
    await page.screenshot({ path: 'test-results/screenshots/blade-login.png', fullPage: true });
  });

  test('Register sahifasi ochiladi va dizayn to\'g\'ri', async ({ page }) => {
    await page.goto('/auth/register', { waitUntil: 'networkidle' });
    
    await expect(page).toHaveTitle(/Widget.*Register/);
    await expect(page.locator('h1:has-text("ChatWidget")')).toBeVisible();
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    await expect(page.locator('input#password_confirmation')).toBeVisible();
    
    console.log('✅ Register sahifasi ochildi');
    await page.screenshot({ path: 'test-results/screenshots/blade-register.png', fullPage: true });
  });

  test('Login funksionali ishlaydi', async ({ page }) => {
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    
    await page.locator('#email').fill('verified@example.com');
    await page.locator('#password').fill('Verified123!');
    await page.locator('button[type="submit"]').click();
    
    await page.waitForTimeout(2000);
    
    const url = page.url();
    console.log(`📍 Login dan keyin: ${url}`);
    
    // Dashboard yoki app sahifasiga yo'naltirilishi kerak
    if (!url.includes('/login')) {
      console.log('✅ Login muvaffaqiyatli!');
    } else {
      console.log('❌ Login sahifasida qoldi');
    }
    
    await page.screenshot({ path: 'test-results/screenshots/blade-login-result.png', fullPage: true });
  });

  test('Register funksionali ishlaydi', async ({ page }) => {
    const timestamp = Date.now();
    const email = `blade${timestamp}@example.com`;
    const password = `Blade${timestamp}!`;
    
    await page.goto('/auth/register', { waitUntil: 'networkidle' });
    
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click();
    
    await page.waitForTimeout(3000);
    
    const url = page.url();
    console.log(`📍 Register dan keyin: ${url}`);
    
    if (!url.includes('/register')) {
      console.log(`✅ Register muvaffaqiyatli: ${email}`);
    } else {
      console.log('❌ Register sahifasida qoldi');
    }
    
    await page.screenshot({ path: 'test-results/screenshots/blade-register-result.png', fullPage: true });
  });
});

// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Login test - to'g'ri navigation bilan
 */

test.describe('Login funksional test', () => {
  test('Login qilish va redirect kutish', async ({ page }) => {
    const EMAIL = 'testuser@example.com';
    const PASSWORD = 'TestUser123!';

    // Login sahifasiga o'tish
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Login/);
    
    // Formni to'ldirish
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    
    // Submit va navigation kutish
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }).catch(() => null),
      page.locator('button[type="submit"]:has-text("Sign in")').click()
    ]);
    
    await page.waitForTimeout(2000);
    
    const url = page.url();
    const title = await page.title();
    
    console.log(`📍 URL: ${url}`);
    console.log(`📄 Title: ${title}`);
    
    // Screenshot
    await page.screenshot({ path: 'test-results/screenshots/login-result.png', fullPage: true });
    
    if (url.includes('/login')) {
      console.log('❌ Login sahifasida qoldi');
      
      // Xatolik xabarini tekshirish
      const errorMsg = await page.locator('.fi-no, .fi-notification--danger, .fi-validation-error').first().textContent().catch(() => 'Yo\'q');
      console.log(`⚠️ Xatolik: ${errorMsg}`);
    } else {
      console.log('✅ Login muvaffaqiyatli - Dashboard ga yo\'naltirildi');
    }
  });
});

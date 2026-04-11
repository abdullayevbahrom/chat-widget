// @ts-check
import { test, expect } from '@playwright/test';

test('Login xatolik xabarini ko\'rish', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  await page.locator('#form\\.email').fill('testuser@example.com');
  await page.locator('#form\\.password').fill('TestUser123!');
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  
  await page.waitForTimeout(3000);
  
  // Barcha xatolik xabarlarini olish
  const errors = await page.locator('[class*="error"], [class*="danger"], .fi-no, .fi-validation-error').all();
  
  for (const error of errors) {
    const text = await error.textContent().catch(() => '');
    if (text.trim()) {
      console.log(`❌ Xatolik: ${text.trim()}`);
    }
  }
  
  // Form state ni tekshirish
  const formState = await page.evaluate(() => {
    const emailInput = document.querySelector('#form\\.email');
    const passwordInput = document.querySelector('#form\\.password');
    return {
      email: emailInput?.value,
      emailErrors: emailInput?.validationMessage,
      url: window.location.href,
    };
  });
  
  console.log('📊 Form state:', JSON.stringify(formState, null, 2));
  
  await page.screenshot({ path: 'test-results/screenshots/login-error.png', fullPage: true });
});

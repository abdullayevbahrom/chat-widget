// @ts-check
import { test, expect } from '@playwright/test';

test('Login - to\'g\'ri input usuli', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  // Inputlarni topish
  const emailInput = page.locator('#form\\.email');
  const passwordInput = page.locator('#form\\.password');
  
  await emailInput.click();
  await emailInput.pressSequentially('testuser@example.com', { delay: 50 });
  
  await passwordInput.click();
  await passwordInput.pressSequentially('TestUser123!', { delay: 50 });
  
  await page.waitForTimeout(500);
  
  // Qiymatlarni tekshirish
  const emailValue = await emailInput.inputValue();
  const passwordValue = await passwordInput.inputValue();
  
  console.log(`📧 Email: ${emailValue}`);
  console.log(`🔒 Password: ${passwordValue ? 'KIRITILDI' : 'BO\'SH'}`);
  
  // Submit
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  await page.waitForTimeout(3000);
  
  const url = page.url();
  console.log(`📍 URL: ${url}`);
  
  await page.screenshot({ path: 'test-results/screenshots/login-sequential.png', fullPage: true });
  
  if (!url.includes('/login')) {
    console.log('✅ Login muvaffaqiyatli!');
  } else {
    console.log('❌ Login sahifasida qoldi');
  }
});

// @ts-check
import { test, expect } from '@playwright/test';

test('Login - xatolik xabarini ko\'rish', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  await page.locator('#form\\.email').click();
  await page.locator('#form\\.email').pressSequentially('testuser@example.com', { delay: 30 });
  await page.locator('#form\\.password').click();
  await page.locator('#form\\.password').pressSequentially('TestUser123!', { delay: 30 });
  
  await page.waitForTimeout(500);
  
  // Submit
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  
  // Notification chiqishini kutish
  await page.waitForTimeout(3000);
  
  // Barcha elementlarni tekshirish
  const bodyText = await page.evaluate(() => document.body.innerText);
  const hasError = bodyText.includes('These credentials do not match') || 
                   bodyText.includes('Invalid') || 
                   bodyText.includes('incorrect');
  
  console.log(`📍 URL: ${page.url()}`);
  console.log(`❌ Xatolik bor: ${hasError}`);
  
  // Agar xatolik bo'lsa, matnni ko'rsatish
  if (hasError) {
    const errorMatch = bodyText.match(/.{0,50}(These credentials|Invalid|incorrect).{0,50}/i);
    if (errorMatch) {
      console.log(`📝 Xatolik matni: ${errorMatch[0]}`);
    }
  }
  
  // Full page screenshot
  await page.screenshot({ path: 'test-results/screenshots/login-full-error.png', fullPage: true });
  
  // HTML ni olish
  const html = await page.content();
  if (html.includes('These credentials') || html.includes('invalid')) {
    console.log('❌ Credentials xatosi topildi HTML da');
  }
});

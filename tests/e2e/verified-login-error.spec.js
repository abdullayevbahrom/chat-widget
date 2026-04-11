// @ts-check
import { test, expect } from '@playwright/test';

test('Verified login with error check', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  // Inputlarni to'ldirish
  await page.locator('#form\\.email').fill('verified@example.com');
  await page.locator('#form\\.password').fill('Verified123!');
  await page.waitForTimeout(500);
  
  // Submit
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  await page.waitForTimeout(3000);
  
  // Sahifa matnini olish
  const bodyText = await page.evaluate(() => document.body.innerText);
  
  // Xatolik xabarini qidirish
  const errorPatterns = ['These credentials', 'Invalid', 'incorrect', 'failed', 'error', 'do not match'];
  for (const pattern of errorPatterns) {
    if (bodyText.toLowerCase().includes(pattern.toLowerCase())) {
      const regex = new RegExp(`.{0,80}${pattern}.{0,80}`, 'gi');
      const match = bodyText.match(regex);
      if (match) {
        console.log(`❌ Xatolik topildi: "${match[0].trim()}"`);
      }
    }
  }
  
  console.log(`📍 URL: ${page.url()}`);
  
  // HTML da xatolik klasslarini qidirish
  const errorHtml = await page.evaluate(() => {
    const errorElements = document.querySelectorAll('[class*="error"], [class*="danger"], [class*="fail"]');
    return Array.from(errorElements).map(el => el.textContent?.trim()).filter(Boolean);
  });
  
  if (errorHtml.length > 0) {
    console.log(`🚨 Error elements: ${errorHtml.join(' | ')}`);
  }
  
  await page.screenshot({ path: 'test-results/screenshots/verified-login-error.png', fullPage: true });
});

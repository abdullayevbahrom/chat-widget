// @ts-check
import { test, expect } from '@playwright/test';

test('Verified user bilan login', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  await page.locator('#form\\.email').fill('verified@example.com');
  await page.locator('#form\\.password').fill('Verified123!');
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  await page.waitForTimeout(3000);
  
  const url = page.url();
  const title = await page.title();
  
  console.log(`📍 URL: ${url}`);
  console.log(`📄 Title: ${title}`);
  
  await page.screenshot({ path: 'test-results/screenshots/verified-login.png', fullPage: true });
  
  if (!url.includes('/login') && !url.includes('/email-verification')) {
    console.log('✅ Login muvaffaqiyatli - Dashboard ga yo\'naltirildi!');
  } else {
    console.log(`❌ Hali ham auth sahifasida: ${url}`);
  }
});

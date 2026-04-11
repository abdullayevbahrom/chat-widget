// @ts-check
import { test, expect } from '@playwright/test';

test('Login button debug', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  // Button topish
  const submitButton = page.locator('button[type="submit"]');
  const count = await submitButton.count();
  console.log(`🔘 Submit button count: ${count}`);
  
  for (let i = 0; i < count; i++) {
    const btn = submitButton.nth(i);
    const text = await btn.textContent();
    const isVisible = await btn.isVisible();
    const isDisabled = await btn.isDisabled();
    console.log(`   Button ${i}: text="${text?.trim()}", visible=${isVisible}, disabled=${isDisabled}`);
  }
  
  // Form ni tekshirish
  const forms = await page.locator('form');
  const formCount = await forms.count();
  console.log(`📋 Form count: ${formCount}`);
  
  // Inputlarni to'ldirish
  await page.locator('#form\\.email').fill('testuser@example.com');
  await page.locator('#form\\.password').fill('TestUser123!');
  await page.waitForTimeout(500);
  
  // Enter bosish
  console.log('⌨️ Enter bosish...');
  await page.locator('#form\\.password').press('Enter');
  await page.waitForTimeout(5000);
  
  console.log(`📍 URL: ${page.url()}`);
  
  await page.screenshot({ path: 'test-results/screenshots/login-enter.png', fullPage: true });
});

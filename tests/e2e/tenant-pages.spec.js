// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Barcha tenant sahifalarini ochish va screenshot olish
 * Pre-created user: testuser@example.com / TestUser123!
 */

test.describe('Tenant sahifalarini ko\'rib chiqish', () => {
  const EMAIL = 'testuser@example.com';
  const PASSWORD = 'TestUser123!';

  test('1 - Login va Dashboard', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(3000);
    
    const url = page.url();
    console.log(`📍 Login dan keyin: ${url}`);
    
    await page.screenshot({ path: 'test-results/screenshots/tenant-01-dashboard.png', fullPage: true });
    console.log('✅ Dashboard screenshot olindi');
  });

  test('2 - Projects sahifasi', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/app/projects', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/tenant-02-projects.png', fullPage: true });
    console.log('✅ Projects screenshot olindi');
  });

  test('3 - Conversations sahifasi', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/app/conversations', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/tenant-03-conversations.png', fullPage: true });
    console.log('✅ Conversations screenshot olindi');
  });

  test('4 - Domains sahifasi', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/app/tenant-domains', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/tenant-04-domains.png', fullPage: true });
    console.log('✅ Domains screenshot olindi');
  });

  test('5 - Tenant Profile sahifasi', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/app/tenant-profile', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/tenant-05-profile.png', fullPage: true });
    console.log('✅ Profile screenshot olindi');
  });

  test('6 - Telegram Bot Settings', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(EMAIL);
    await page.locator('#form\\.password').fill(PASSWORD);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(2000);
    
    await page.goto('/app/telegram-bot-settings', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: 'test-results/screenshots/tenant-06-telegram-settings.png', fullPage: true });
    console.log('✅ Telegram Settings screenshot olindi');
  });
});

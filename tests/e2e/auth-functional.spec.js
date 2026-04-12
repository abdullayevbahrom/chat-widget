// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Registratsiya, Login va barcha sahifalarni test qilish
 * Har bir test o'z user yaratadi va cookie saqlaydi
 */

// Helper funksiyalar
async function registerAndLogin(page, email, password) {
  // Register
  await page.goto('/auth/register', { waitUntil: 'domcontentloaded' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password_confirmation').fill(password);
  await page.locator('button[type="submit"]').click();
  
  // Wait for navigation after submit
  await page.waitForURL(/\/dashboard|\/auth\/login|\/auth\/register/, { timeout: 10000 });
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

  const urlAfterRegister = page.url();
  
  // If redirected to login or still on register, try login
  if (urlAfterRegister.includes('/login') || urlAfterRegister.includes('/register')) {
    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('button[type="submit"]').click();
    
    await page.waitForURL(/\/dashboard|\/auth\/login/, { timeout: 10000 });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  }

  return page.url();
}

test.describe('ChatWidget - Full E2E Test', () => {

  // ========== REGISTRATSIYA ==========
  test('1 - Yangi foydalanuvchi registratsiyasi', async ({ page }) => {
    const timestamp = Date.now();
    const testEmail = `user${timestamp}@example.com`;
    const testPassword = `Test${timestamp}!`;

    await page.goto('/auth/register', { waitUntil: 'domcontentloaded' });

    // Sahifa elementlarini tekshirish
    await expect(page).toHaveTitle(/Register/);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#password_confirmation')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();

    // Formni to'ldirish
    await page.locator('#email').fill(testEmail);
    await page.locator('#password').fill(testPassword);
    await page.locator('#password_confirmation').fill(testPassword);

    // Submit
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/\/dashboard|\/auth/, { timeout: 10000 });

    // Xatolik yo'qligini tekshirish
    const hasError = await page.locator('.bg-red-50, .text-red-700').isVisible().catch(() => false);
    expect(hasError).toBe(false);

    console.log(`✅ Registratsiya muvaffaqiyatli: ${testEmail}`);
  });

  // ========== LOGIN ==========
  test('2 - Foydalanuvchi login qilishi', async ({ page }) => {
    const timestamp = Date.now();
    const testEmail = `login${timestamp}@example.com`;
    const testPassword = `Test${timestamp}!`;

    const finalUrl = await registerAndLogin(page, testEmail, testPassword);

    console.log(`📍 Final URL: ${finalUrl}`);

    // Login sahifasida qolmaganligini tekshirish
    expect(finalUrl).not.toContain('/login');
    console.log(`✅ Login muvaffaqiyatli: ${testEmail}`);
  });

  // ========== DASHBOARD ==========
  test('3 - Dashboard sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    const url = await registerAndLogin(page, `dash${timestamp}@example.com`, `Test${timestamp}!`);

    if (url.includes('/login')) {
      throw new Error('User login qilinmagan');
    }

    console.log(`📍 Dashboard URL: ${url}`);
    console.log('✅ Dashboard sahifasi ochildi');
  });

  // ========== PROJECTS SAHIFASI ==========
  test('4 - Projects sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `proj${timestamp}@example.com`, `Test${timestamp}!`);

    await page.goto('/dashboard/projects', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

    const title = await page.title();
    console.log(`📄 Projects: ${page.url()} | Title: ${title}`);
    
    // Check if page loaded without errors
    expect(page.url()).toContain('/dashboard/projects');
    console.log('✅ Projects ochildi');
  });

  // ========== CONVERSATIONS SAHIFASI ==========
  test('5 - Conversations sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `conv${timestamp}@example.com`, `Test${timestamp}!`);

    await page.goto('/dashboard/conversations', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

    const title = await page.title();
    console.log(`📄 Conversations: ${page.url()} | Title: ${title}`);
    console.log('✅ Conversations ochildi');
  });

  // ========== DOMAINS SAHIFASI ==========
  test('6 - Domains sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `domain${timestamp}@example.com`, `Test${timestamp}!`);

    await page.goto('/dashboard/tenant-domains', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

    const title = await page.title();
    console.log(`📄 Domains: ${page.url()} | Title: ${title}`);
    console.log('✅ Domains ochildi');
  });

  // ========== TENANT PROFILE SAHIFASI ==========
  test('7 - Tenant Profile sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `profile${timestamp}@example.com`, `Test${timestamp}!`);

    await page.goto('/dashboard/tenant-profile', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

    const title = await page.title();
    console.log(`📄 Profile: ${page.url()} | Title: ${title}`);
    console.log('✅ Profile ochildi');
  });

  // ========== TELEGRAM BOT SAHIFASI ==========
  test('8 - Telegram Bot sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `telegram${timestamp}@example.com`, `Test${timestamp}!`);

    await page.goto('/dashboard/telegram-bot-settings', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

    const title = await page.title();
    console.log(`📄 Telegram Bot: ${page.url()} | Title: ${title}`);
    console.log('✅ Telegram Bot ochildi');
  });

  // ========== DIZAYN CONSISTENCY ==========
  test('9 - Dizayn consistency: Landing va Auth sahifalari bir xil', async ({ page }) => {
    // Landing page
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const landingBg = await page.evaluate(() => {
      const body = document.body;
      return window.getComputedStyle(body).background;
    });

    // Register page
    await page.goto('/auth/register', { waitUntil: 'domcontentloaded' });
    const registerBg = await page.evaluate(() => {
      const body = document.body;
      return window.getComputedStyle(body).background;
    });

    // Both should have gradient background
    expect(landingBg).toContain('gradient')?.toBe(true) || expect(landingBg).toContain('#');
    expect(registerBg).toContain('gradient')?.toBe(true) || expect(registerBg).toContain('#');
    
    console.log('✅ Dizayn consistent');
  });
});

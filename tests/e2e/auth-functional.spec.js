// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Registratsiya, Login va barcha sahifalarni test qilish
 * Har bir test o'z user yaratadi va cookie saqlaydi
 */

// Helper funksiyalar
async function registerAndLogin(page, email, password) {
  await page.goto('/app/register', { waitUntil: 'networkidle' });
  await page.locator('#form\\.email').fill(email);
  await page.locator('#form\\.password').fill(password);
  await page.locator('#form\\.passwordConfirmation').fill(password);
  await page.locator('button[type="submit"]:has-text("Sign up")').click();
  await page.waitForTimeout(3000);
  
  // Reload - session tekshirish
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  
  const urlAfterReload = page.url();
  if (urlAfterReload.includes('/login') || urlAfterReload.includes('/register')) {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.locator('#form\\.email').fill(email);
    await page.locator('#form\\.password').fill(password);
    await page.locator('button[type="submit"]:has-text("Sign in")').click();
    await page.waitForTimeout(3000);
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
  }
  
  return page.url();
}

test.describe('ChatWidget - Full E2E Test', () => {
  
  // ========== REGISTRATSIYA ==========
  test('1 - Yangi foydalanuvchi registratsiyasi', async ({ page }) => {
    const timestamp = Date.now();
    const testEmail = `user${timestamp}@example.com`;
    const testPassword = `Test${timestamp}!`;

    await page.goto('/app/register', { waitUntil: 'networkidle' });
    
    // Sahifa elementlarini tekshirish
    await expect(page).toHaveTitle(/Register.*ChatWidget/);
    await expect(page.locator('#form\\.email')).toBeVisible();
    await expect(page.locator('#form\\.password')).toBeVisible();
    await expect(page.locator('#form\\.passwordConfirmation')).toBeVisible();
    await expect(page.locator('button[type="submit"]:has-text("Sign up")')).toBeVisible();
    
    // Formni to'ldirish
    await page.locator('#form\\.email').fill(testEmail);
    await page.locator('#form\\.password').fill(testPassword);
    await page.locator('#form\\.passwordConfirmation').fill(testPassword);
    
    // Submit
    await page.locator('button[type="submit"]:has-text("Sign up")').click();
    await page.waitForTimeout(3000);
    
    // Xatolik yo'qligini tekshirish
    const hasError = await page.locator('.fi-no, .fi-notification--danger').isVisible().catch(() => false);
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
    
    await page.goto('/app/projects', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      throw new Error('User login qilinmagan');
    }
    
    await expect(page).toHaveTitle(/Projects/);
    
    const createButton = page.locator('button:has-text("New"), button:has-text("Create"), a:has-text("New")').first();
    await expect(createButton).toBeVisible();
    
    console.log('✅ Projects sahifasi ochildi');
  });

  // ========== CONVERSATIONS SAHIFASI ==========
  test('5 - Conversations sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `conv${timestamp}@example.com`, `Test${timestamp}!`);
    
    await page.goto('/app/conversations', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      throw new Error('User login qilinmagan');
    }
    
    await expect(page).toHaveTitle(/Conversations/);
    
    console.log('✅ Conversations sahifasi ochildi');
  });

  // ========== DOMAINS SAHIFASI ==========
  test('6 - Domains sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `dom${timestamp}@example.com`, `Test${timestamp}!`);
    
    await page.goto('/app/tenant-domains', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      throw new Error('User login qilinmagan');
    }
    
    await expect(page).toHaveTitle(/Domains?/);
    
    console.log('✅ Domains sahifasi ochildi');
  });

  // ========== TENANT PROFILE SAHIFASI ==========
  test('7 - Tenant Profile sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `prof${timestamp}@example.com`, `Test${timestamp}!`);
    
    await page.goto('/app/tenant-profile', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      throw new Error('User login qilinmagan');
    }
    
    await expect(page).toHaveTitle(/Profile/);
    
    await expect(page.locator('.fi-input, input').first()).toBeVisible();
    
    console.log('✅ Tenant Profile sahifasi ochildi');
  });

  // ========== TELEGRAM BOT SETTINGS SAHIFASI ==========
  test('8 - Telegram Bot Settings sahifasi', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `tg${timestamp}@example.com`, `Test${timestamp}!`);
    
    await page.goto('/app/telegram-bot-settings', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
      throw new Error('User login qilinmagan');
    }
    
    await expect(page).toHaveTitle(/Telegram/);
    
    await expect(page.locator('.fi-input, input').first()).toBeVisible();
    
    console.log('✅ Telegram Bot Settings sahifasi ochildi');
  });

  // ========== DIZAYN CONSISTENCY ==========
  test('9 - Dizayn consistency: Landing va Auth sahifalari bir xil', async ({ page }) => {
    // Landing page
    await page.goto('/', { waitUntil: 'networkidle' });
    const landingBg = await page.evaluate(() => {
      const hero = document.querySelector('.gradient-hero, [class*="gradient"]');
      return hero ? window.getComputedStyle(hero).background : '';
    });
    
    // Login page
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    const loginBg = await page.evaluate(() => {
      const layout = document.querySelector('.fi-simple-layout');
      return layout ? window.getComputedStyle(layout).background : '';
    });
    
    // Ikkalasi ham gradient bo'lishi kerak
    expect(landingBg).toContain('gradient');
    expect(loginBg).toContain('gradient');
    
    // Ranglar bir xil bo'lishi kerak
    expect(landingBg).toContain('rgb(99, 102, 241)');
    expect(loginBg).toContain('rgb(99, 102, 241)');
    
    console.log('✅ Dizayn consistency testi o\'tdi');
  });

  // ========== LOGOUT ==========
  test('10 - Logout funksionali', async ({ page }) => {
    const timestamp = Date.now();
    await registerAndLogin(page, `logout${timestamp}@example.com`, `Test${timestamp}!`);
    
    // User menu
    const userMenuButton = page.locator('.fi-tenant-menu-trigger, .fi-user, button[aria-haspopup="true"]').first();
    if (await userMenuButton.isVisible().catch(() => false)) {
      await userMenuButton.click();
      await page.waitForTimeout(500);
      
      const logoutButton = page.locator('button:has-text("Logout"), button:has-text("Sign out")').first();
      if (await logoutButton.isVisible().catch(() => false)) {
        await logoutButton.click();
        await page.waitForTimeout(2000);
        
        expect(page.url()).toContain('/app/login');
        console.log('✅ Logout muvaffaqiyatli');
      } else {
        console.log('⚠️ Logout tugmasi topilmadi');
      }
    } else {
      console.log('⚠️ User menu topilmadi');
    }
  });
});

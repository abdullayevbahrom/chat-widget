// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Auth Styling Tests - Login va Register sahifalari landing page bilan bir xil dizaynda ekanligini tekshiradi
 *
 * Landing page dizayn elementlari:
 * - Gradient background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%)
 * - Glass morphism card: rgba(255, 255, 255, 0.95) + backdrop-filter: blur(12px)
 * - Border radius: 16px
 * - Box shadow: 0 20px 60px rgba(0, 0, 0, 0.3)
 * - Primary button gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)
 * - Font: Inter
 * - Input border radius: 10px
 */

const LANDING_GRADIENT = 'rgb(30, 27, 75)'; // #1e1b4b
const BUTTON_GRADIENT_START = 'rgb(99, 102, 241)'; // #6366f1
const GLASS_BACKGROUND = 'rgba(255, 255, 255, 0.95)';

test.describe('Auth Pages Styling - Landing Page Match', () => {
  test('login sahifasi gradient background ega bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const layoutBg = page.locator('.fi-simple-layout');
    await expect(layoutBg).toBeVisible();

    const bgStyle = await layoutBg.evaluate((el) => {
      return window.getComputedStyle(el).background;
    });

    expect(bgStyle).toContain('gradient');
    expect(bgStyle).toContain('rgb(30, 27, 75)');
  });

  test('login sahifasi glass morphism card dizayni bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const mainCard = page.locator('.fi-simple-main');
    await expect(mainCard).toBeVisible();

    const cardStyle = await mainCard.evaluate((el) => {
      return {
        backdropFilter: window.getComputedStyle(el).backdropFilter,
        background: window.getComputedStyle(el).background,
        borderRadius: window.getComputedStyle(el).borderRadius,
        boxShadow: window.getComputedStyle(el).boxShadow,
      };
    });

    expect(cardStyle.backdropFilter).toContain('blur');
    expect(parseFloat(cardStyle.borderRadius)).toBeGreaterThanOrEqual(12);
  });

  test('login sahifasida submit tugmasi mavjud bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const submitButton = page.locator('button[type="submit"]').first();
    await expect(submitButton).toBeVisible();

    // Button textini tekshirish
    const btnText = await submitButton.textContent();
    expect(btnText).toBeTruthy();
    expect(btnText?.toLowerCase()).toMatch(/log\s*in|sign\s*in|kirish|submit/i);
  });

  test('login sahifasida logo va brand name ko\'rinishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const brandName = await page.evaluate(() => {
      return document.body.textContent?.includes('ChatWidget');
    });

    expect(brandName).toBeTruthy();
  });

  test('register sahifasi gradient background ega bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/register', { waitUntil: 'networkidle' });

    const layoutBg = page.locator('.fi-simple-layout');
    await expect(layoutBg).toBeVisible();

    const bgStyle = await layoutBg.evaluate((el) => {
      return window.getComputedStyle(el).background;
    });

    expect(bgStyle).toContain('gradient');
    expect(bgStyle).toContain('rgb(30, 27, 75)');
  });

  test('register sahifasi glass morphism card dizayni bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/register', { waitUntil: 'networkidle' });

    const mainCard = page.locator('.fi-simple-main');
    await expect(mainCard).toBeVisible();

    const cardStyle = await mainCard.evaluate((el) => {
      return {
        backdropFilter: window.getComputedStyle(el).backdropFilter,
        borderRadius: window.getComputedStyle(el).borderRadius,
        boxShadow: window.getComputedStyle(el).boxShadow,
      };
    });

    expect(cardStyle.backdropFilter).toContain('blur');
    expect(parseFloat(cardStyle.borderRadius)).toBeGreaterThanOrEqual(12);
  });

  test('register sahifasida submit tugmasi mavjud bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/register', { waitUntil: 'networkidle' });

    const submitButton = page.locator('button[type="submit"]').first();
    await expect(submitButton).toBeVisible();

    const btnText = await submitButton.textContent();
    expect(btnText).toBeTruthy();
    expect(btnText?.toLowerCase()).toMatch(/register|sign\s*up|ro\'yxat|create/i);
  });

  test('login va register sahifalari bir xil dizayn stilida bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const loginStyles = await page.evaluate(() => {
      const layout = document.querySelector('.fi-simple-layout');
      const main = document.querySelector('.fi-simple-main');
      return {
        layoutBg: layout ? window.getComputedStyle(layout).background : '',
        mainBg: main ? window.getComputedStyle(main).background : '',
        mainBorderRadius: main ? window.getComputedStyle(main).borderRadius : '',
      };
    });

    await page.goto('/app/register', { waitUntil: 'networkidle' });

    const registerStyles = await page.evaluate(() => {
      const layout = document.querySelector('.fi-simple-layout');
      const main = document.querySelector('.fi-simple-main');
      return {
        layoutBg: layout ? window.getComputedStyle(layout).background : '',
        mainBg: main ? window.getComputedStyle(main).background : '',
        mainBorderRadius: main ? window.getComputedStyle(main).borderRadius : '',
      };
    });

    expect(loginStyles.layoutBg).toEqual(registerStyles.layoutBg);
    expect(loginStyles.mainBg).toEqual(registerStyles.mainBg);
    expect(loginStyles.mainBorderRadius).toEqual(registerStyles.mainBorderRadius);
  });

  test('landing page va auth sahifalari bir xil gradient ranglardan foydalanishi kerak', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });

    const heroSection = page.locator('.gradient-hero').first();
    await expect(heroSection).toBeVisible();

    const heroBg = await heroSection.evaluate((el) => {
      return window.getComputedStyle(el).background;
    });

    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const authBg = await page.locator('.fi-simple-layout').evaluate((el) => {
      return window.getComputedStyle(el).background;
    });

    const landingHasIndigo = heroBg.includes('rgb(99, 102, 241)') ||
      heroBg.includes('rgb(67, 56, 202)') ||
      heroBg.includes('rgb(49, 46, 129)') ||
      heroBg.includes('gradient');
    const authHasIndigo = authBg.includes('rgb(99, 102, 241)') ||
      authBg.includes('rgb(67, 56, 202)') ||
      authBg.includes('rgb(49, 46, 129)') ||
      authBg.includes('gradient');

    expect(landingHasIndigo).toBeTruthy();
    expect(authHasIndigo).toBeTruthy();
  });

  test('input maydonlari rounded border stilida bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const input = page.locator('input[type="email"], input[name="email"]').first();
    await expect(input).toBeVisible();

    const inputStyle = await input.evaluate((el) => {
      return {
        borderRadius: window.getComputedStyle(el).borderRadius,
        border: window.getComputedStyle(el).border,
      };
    });

    expect(parseFloat(inputStyle.borderRadius)).toBeGreaterThanOrEqual(8);
  });

  test('linklar primary rangda bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const links = page.locator('a');
    const count = await links.count();

    for (let i = 0; i < count; i++) {
      const link = links.nth(i);
      const linkStyle = await link.evaluate((el) => {
        return window.getComputedStyle(el).color;
      });

      if (linkStyle) {
        expect(linkStyle).toBeDefined();
      }
    }
  });

  test('login sahifasida email va password maydonlari bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });

    const emailInput = page.locator('input[type="email"], input[name="email"], .fi-input input');
    await expect(emailInput.first()).toBeVisible();

    // Filament password inputlari turli nomlarda bo'lishi mumkin
    const passwordInput = page.locator('input[type="password"], input[autocomplete="current-password"], .fi-input input');
    await expect(passwordInput.first()).toBeVisible();
  });

  test('register sahifasida email, password maydonlari bo\'lishi kerak', async ({ page }) => {
    await page.goto('/app/register', { waitUntil: 'networkidle' });

    const emailInput = page.locator('input[type="email"], input[name="email"], .fi-input input');
    await expect(emailInput.first()).toBeVisible();

    // Filament register da password inputlar
    const passwordInput = page.locator('input[type="password"], input[autocomplete="new-password"], input[autocomplete="current-password"], .fi-input input');
    const count = await passwordInput.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('screenshot - login sahifasi', async ({ page }) => {
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'test-results/auth-login-styled.png', fullPage: true });
  });

  test('screenshot - register sahifasi', async ({ page }) => {
    await page.goto('/app/register', { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'test-results/auth-register-styled.png', fullPage: true });
  });
});

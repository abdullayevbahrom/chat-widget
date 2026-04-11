// @ts-check
import { test, expect } from '@playwright/test';

test.describe('Barcha Tenant Sahifalari', () => {
  const pages = [
    { name: 'Dashboard', url: '/dashboard', expected: 'Dashboard' },
    { name: 'Projects', url: '/dashboard/projects', expected: 'Projects' },
    { name: 'Conversations', url: '/dashboard/conversations', expected: 'Conversations' },
    { name: 'Domains', url: '/dashboard/tenant-domains', expected: 'Domains' },
    { name: 'Profile', url: '/dashboard/tenant-profile', expected: 'Settings' },
    { name: 'Telegram Bot', url: '/dashboard/telegram-bot-settings', expected: 'Telegram' },
  ];

  for (const page of pages) {
    test(`${page.name} sahifasi ochiladi`, async ({ browser }) => {
      const context = await browser.newContext();
      const p = await context.newPage();
      
      // Login
      await p.goto('/auth/login', { waitUntil: 'networkidle' });
      await p.locator('#email').fill('verified@example.com');
      await p.locator('#password').fill('Verified123!');
      await p.locator('button[type="submit"]').click();
      await p.waitForTimeout(2000);
      
      // Sahifaga o'tish
      await p.goto(page.url, { waitUntil: 'networkidle' });
      await p.waitForTimeout(1000);
      
      const url = p.url();
      const title = await p.title();
      
      console.log(`📄 ${page.name}: ${url} | Title: ${title}`);
      
      // Login sahifasiga yo'naltirilmaganligini tekshirish
      expect(url).not.toContain('/auth/login');
      expect(url).toContain(page.url.split('?')[0]);
      
      // Screenshot
      await p.screenshot({ path: `test-results/screenshots/all-pages-${page.name.toLowerCase()}.png`, fullPage: true });
      
      console.log(`✅ ${page.name} ochildi`);
      await context.close();
    });
  }
});

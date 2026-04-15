// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Admin Dashboard, Projects va Conversations sahifalariga kirish testi
 */

const ADMIN_CREDENTIALS = { email: 'widget-chat@gmail.com', password: '!1Widgetchat' };

test.describe('Admin Dashboard Full Access', () => {
  test('admin login → dashboard → projects → conversations', async ({ page }) => {
    // 1. Login
    console.log('🔐 Login qilinyapti...');
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(ADMIN_CREDENTIALS.email);
    await page.locator('#password').fill(ADMIN_CREDENTIALS.password);
    await page.getByRole('button', { name: /Sign In|Kirish/i }).click();

    // Admin dashboard yoki home page ga yo'naltiriladi
    await page.waitForURL(/\/admin|\/$/, { timeout: 10000 });
    console.log('✅ Login muvaffaqiyatli');

    // 2. Dashboard ga o'tish
    console.log('📊 Dashboard ga o\'tilyapti...');
    await page.goto('/dashboard', { waitUntil: 'networkidle' });
    await expect(page).toHaveURL(/\/dashboard$/);

    // Dashboard elementlarini tekshirish
    await expect(page.locator('text=Total Projects').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Total Conversations')).toBeVisible();
    await expect(page.locator('text=Open Conversations')).toBeVisible();
    console.log('✅ Dashboard ochildi');

    // 3. Projects sahifasiga o'tish
    console.log('📁 Projects sahifasiga o\'tilyapti...');
    await page.getByRole('link', { name: /Projects/i }).click();
    await page.waitForURL(/\/dashboard\/projects/, { timeout: 10000 });
    await expect(page).toHaveURL(/\/dashboard\/projects$/);
    await expect(page.locator('h1:has-text("Projects")')).toBeVisible({ timeout: 10000 });
    console.log('✅ Projects sahifasi ochildi');

    // 4. Conversations sahifasiga o'tish
    console.log('💬 Conversations sahifasiga o\'tilyapti...');
    await page.getByRole('link', { name: /Conversations/i }).click();
    await page.waitForURL(/\/dashboard\/conversations/, { timeout: 10000 });
    await expect(page).toHaveURL(/\/dashboard\/conversations$/);

    // Conversations sahifasi yuklanganligini tekshirish (title yoki content)
    const pageContent = await page.content();
    expect(pageContent.includes('Conversations') || pageContent.includes('conversation')).toBeTruthy();
    console.log('✅ Conversations sahifasi ochildi');

    // 5. Qayta Dashboard ga o'tish
    console.log('🔄 Qayta Dashboard ga o\'tilyapti...');
    await page.goto('/dashboard', { waitUntil: 'networkidle' });
    await expect(page).toHaveURL(/\/dashboard$/);
    console.log('✅ Barcha sahifalar muvaffaqiyatli ochildi');

    console.log('🎉 Admin dashboard, projects va conversations test muvaffaqiyatli tugadi!');
  });

  test('admin dashboard stats ko\'rsatiladi', async ({ page }) => {
    // Login
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(ADMIN_CREDENTIALS.email);
    await page.locator('#password').fill(ADMIN_CREDENTIALS.password);
    await page.getByRole('button', { name: /Sign In|Kirish/i }).click();
    await page.waitForURL(/\/admin|\/$/, { timeout: 10000 });

    // Dashboard
    await page.goto('/dashboard', { waitUntil: 'networkidle' });

    // Stats kartochkalari
    const statsCards = page.locator('.glass.rounded-2xl');
    await expect(statsCards.first()).toBeVisible({ timeout: 10000 });

    // Statistika raqamlari
    const statNumbers = page.locator('p.text-3xl.font-bold');
    await expect(statNumbers.first()).toBeVisible();

    const statsCount = await statNumbers.count();
    expect(statsCount).toBeGreaterThanOrEqual(3);
    console.log(`✅ ${statsCount} ta statistika kartochkasi topildi`);
  });

  test('projects sahifasida projectlar ro\'yxati ko\'rsatiladi', async ({ page }) => {
    // Login
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(ADMIN_CREDENTIALS.email);
    await page.locator('#password').fill(ADMIN_CREDENTIALS.password);
    await page.getByRole('button', { name: /Sign In|Kirish/i }).click();
    await page.waitForURL(/\/admin|\/$/, { timeout: 10000 });

    // Projects
    await page.goto('/dashboard/projects', { waitUntil: 'networkidle' });

    // Projects table yoki grid
    const pageContent = await page.content();
    const hasProjectsTable = pageContent.includes('Projects') || pageContent.includes('project');
    expect(hasProjectsTable).toBeTruthy();
    console.log('✅ Projects sahifasi yuklandi');
  });

  test('conversations sahifasida suhbatlar ro\'yxati ko\'rsatiladi', async ({ page }) => {
    // Login
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(ADMIN_CREDENTIALS.email);
    await page.locator('#password').fill(ADMIN_CREDENTIALS.password);
    await page.getByRole('button', { name: /Sign In|Kirish/i }).click();
    await page.waitForURL(/\/admin|\/$/, { timeout: 10000 });

    // Conversations
    await page.goto('/dashboard/conversations', { waitUntil: 'networkidle' });

    // Conversations table
    const pageContent = await page.content();
    const hasConversationsTable = pageContent.includes('Conversations') || pageContent.includes('conversation');
    expect(hasConversationsTable).toBeTruthy();
    console.log('✅ Conversations sahifasi yuklandi');
  });
});

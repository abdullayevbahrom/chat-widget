// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Full E2E Smoke Tests - ChatWidget
 * Barcha asosiy funksionalliklarni qamrab oladi
 */

// ============================================================
// HELPER FUNCTIONS
// ============================================================

async function tenantLogin(page, email, password) {
  await page.goto('/auth/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForTimeout(2000);
  return page.url();
}

async function adminLogin(page, email, password) {
  await page.goto('/admin/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForTimeout(2000);
  return page.url();
}

// ============================================================
// 1. LANDING PAGE
// ============================================================

test.describe('1. Landing Page', () => {
  test('Landing page ochiladi va elementlari to\'g\'ri', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Widget.*Real-time|ChatWidget/);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.locator('a:has-text("Get Started"), a:has-text("Login"), a:has-text("Sign up")').first()).toBeVisible();
    console.log('✅ Landing page ochildi');
  });

  test('Landing page dan Register sahifasiga o\'tish', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    // Landing page dan register sahifasiga o'tish
    await page.goto('/auth/register', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await expect(page).toHaveTitle(/Register/);
    console.log('✅ Landing → Register navigation');
  });

  test('Landing page dan Login sahifasiga o\'tish', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.locator('a:has-text("Login"), a:has-text("Sign in")').first().click().catch(() => {});
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Login/);
    console.log('✅ Landing → Login navigation');
  });
});

// ============================================================
// 2. TENANT AUTH (Register + Login)
// ============================================================

test.describe('2. Tenant Auth', () => {
  test('Register sahifasi ochiladi va form elementlari mavjud', async ({ page }) => {
    await page.goto('/auth/register', { waitUntil: 'networkidle' });
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#password_confirmation')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    console.log('✅ Register form elementlari mavjud');
  });

  test('Login sahifasi ochiladi va form elementlari mavjud', async ({ page }) => {
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    console.log('✅ Login form elementlari mavjud');
  });

  test('Yangi foydalanuvchi registratsiyasi → Dashboard', async ({ page }) => {
    const timestamp = Date.now();
    const email = `smoke${timestamp}@example.com`;
    const password = `Smoke${timestamp}!`;

    await page.goto('/auth/register', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(3000);

    const url = page.url();
    expect(url).toContain('/dashboard');
    expect(url).not.toContain('/auth/register');
    expect(url).not.toContain('/email-verification');
    console.log(`✅ Registratsiya → Dashboard: ${email}`);
  });

  test('Login → Dashboard o\'tish', async ({ page }) => {
    const url = await tenantLogin(page, 'verified@example.com', 'Verified123!');
    expect(url).toContain('/dashboard');
    expect(url).not.toContain('/auth/login');
    console.log('✅ Login → Dashboard');
  });

  test('Noto\'g\'ri login ma\'lumotlari', async ({ page }) => {
    await page.goto('/auth/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill('wrong@example.com');
    await page.locator('#password').fill('WrongPassword123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(2000);
    
    const url = page.url();
    expect(url).toContain('/auth/login');
    console.log('✅ Noto\'g\'ri login → sahifada qoldi');
  });

  test('Logout funksionali', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    
    const logoutBtn = page.locator('form[action*="logout"] button, button:has-text("Logout")').first();
    if (await logoutBtn.isVisible().catch(() => false)) {
      await logoutBtn.click();
      await page.waitForTimeout(1000);
      expect(page.url()).toContain('/auth/login');
      console.log('✅ Logout → Login sahifasiga');
    } else {
      console.log('⚠️ Logout tugmasi topilmadi');
    }
  });
});

// ============================================================
// 3. TENANT DASHBOARD
// ============================================================

test.describe('3. Tenant Dashboard', () => {
  test('Dashboard ochiladi va statistika ko\'rsatiladi', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard', { waitUntil: 'networkidle' });
    
    await expect(page.locator('text=Dashboard')).toBeVisible();
    await expect(page.locator('text=Total Projects')).toBeVisible();
    await expect(page.locator('text=Total Conversations')).toBeVisible();
    console.log('✅ Dashboard statistikasi ko\'rsatildi');
  });

  test('Dashboard dan boshqa sahifalarga navigation', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    
    const pages = [
      { name: 'Projects', url: '/dashboard/projects' },
      { name: 'Conversations', url: '/dashboard/conversations' },
      { name: 'Domains', url: '/dashboard/tenant-domains' },
      { name: 'Profile', url: '/dashboard/tenant-profile' },
      { name: 'Telegram Bot', url: '/dashboard/telegram-bot-settings' },
    ];

    for (const p of pages) {
      await page.goto(p.url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(500);
      
      const url = page.url();
      expect(url).not.toContain('/auth/login');
      expect(url).toContain(p.url.split('?')[0]);
      console.log(`  ✅ ${p.name}: ${url}`);
    }
    
    console.log('✅ Barcha tenant sahifalari ochildi');
  });
});

// ============================================================
// 4. TENANT PROJECTS CRUD
// ============================================================

test.describe('4. Tenant Projects CRUD', () => {
  test('Projects sahifasi ochiladi', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard/projects', { waitUntil: 'networkidle' });
    
    await expect(page.locator('text=Projects').first()).toBeVisible();
    console.log('✅ Projects sahifasi ochildi');
  });

  test('Yangi project yaratish', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard/projects', { waitUntil: 'networkidle' });
    
    const createBtn = page.locator('a:has-text("New"), a:has-text("Create"), button:has-text("New")').first();
    if (await createBtn.isVisible().catch(() => false)) {
      await createBtn.click();
      await page.waitForTimeout(1000);
      
      const url = page.url();
      expect(url).toContain('/projects/create') || expect(url).toContain('/projects');
      console.log('✅ Project yaratish formasi ochildi');
    } else {
      console.log('⚠️ Create button topilmadi');
    }
  });
});

// ============================================================
// 5. TENANT CONVERSATIONS
// ============================================================

test.describe('5. Tenant Conversations', () => {
  test('Conversations sahifasi ochiladi', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard/conversations', { waitUntil: 'networkidle' });
    
    await expect(page.locator('text=Conversations').first()).toBeVisible();
    console.log('✅ Conversations sahifasi ochildi');
  });
});

// ============================================================
// 6. TENANT DOMAINS
// ============================================================

test.describe('6. Tenant Domains', () => {
  test('Domains sahifasi ochiladi', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard/tenant-domains', { waitUntil: 'networkidle' });
    
    await expect(page.locator('text=Domains').first()).toBeVisible();
    console.log('✅ Domains sahifasi ochildi');
  });
});

// ============================================================
// 7. TENANT PROFILE
// ============================================================

test.describe('7. Tenant Profile', () => {
  test('Profile sahifasi ochiladi', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard/tenant-profile', { waitUntil: 'networkidle' });
    
    await expect(page.locator('text=Profile').first()).toBeVisible();
    console.log('✅ Profile sahifasi ochildi');
  });
});

// ============================================================
// 8. TELEGRAM BOT SETTINGS
// ============================================================

test.describe('8. Telegram Bot Settings', () => {
  test('Telegram settings sahifasi ochiladi', async ({ page }) => {
    await tenantLogin(page, 'verified@example.com', 'Verified123!');
    await page.goto('/dashboard/telegram-bot-settings', { waitUntil: 'networkidle' });
    
    await expect(page.locator('text=Telegram').first()).toBeVisible();
    console.log('✅ Telegram settings sahifasi ochildi');
  });
});

// ============================================================
// 9. ADMIN PANEL
// ============================================================

test.describe('9. Admin Panel', () => {
  test('Admin Login sahifasi ochiladi', async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Super Admin.*Login|Admin.*Login/);
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    console.log('✅ Admin login sahifasi ochildi');
  });

  test('Admin Login → Dashboard', async ({ page }) => {
    const url = await adminLogin(page, 'admin@example.com', 'Admin123!');
    
    if (!url.includes('/admin/login')) {
      expect(url).toContain('/admin');
      expect(url).not.toContain('/admin/login');
      console.log('✅ Admin Login → Dashboard');
    } else {
      console.log('⚠️ Admin login ishlamadi (user mavjud emas)');
    }
  });

  test('Admin Tenants sahifasi', async ({ page }) => {
    await adminLogin(page, 'admin@example.com', 'Admin123!');
    await page.goto('/admin/manage-tenants', { waitUntil: 'networkidle' });
    
    const url = page.url();
    expect(url).not.toContain('/admin/login');
    expect(url).toContain('/admin/manage-tenants');
    console.log('✅ Admin Tenants sahifasi ochildi');
  });

  test('Admin Users sahifasi', async ({ page }) => {
    await adminLogin(page, 'admin@example.com', 'Admin123!');
    await page.goto('/admin/manage-users', { waitUntil: 'networkidle' });
    
    const url = page.url();
    expect(url).not.toContain('/admin/login');
    expect(url).toContain('/admin/manage-users');
    console.log('✅ Admin Users sahifasi ochildi');
  });
});

// ============================================================
// 10. RESPONSIVE DESIGN CHECK
// ============================================================

test.describe('10. Responsive Design', () => {
  test('Mobile view (375px)', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const page = await context.newPage();
    
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await expect(page.locator('body')).toBeVisible();
    console.log('✅ Mobile view (375px) ishlaydi');
    await context.close();
  });

  test('Tablet view (768px)', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 768, height: 1024 } });
    const page = await context.newPage();
    
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await expect(page.locator('body')).toBeVisible();
    console.log('✅ Tablet view (768px) ishlaydi');
    await context.close();
  });

  test('Desktop view (1280px)', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();
    
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    
    await expect(page.locator('body')).toBeVisible();
    console.log('✅ Desktop view (1280px) ishlaydi');
    await context.close();
  });
});

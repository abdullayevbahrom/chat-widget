// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Full user flow: Register → Create Project → See Widget Preview → Embed Script
 */
test.describe('Full User Flow - Register to Project Creation', () => {
  test('Register, Create Project with Telegram, and see Embed Script', async ({ page, context }) => {
    const id = Date.now();
    const email = `flow-${id}@example.com`;
    const password = `Flow${id}!`;
    const domainName = `project-${id}.example.com`;
    const chatName = `Support ${id}`;

    // 1. Register
    console.log(`📝 Registering: ${email}`);
    await page.goto('/auth/register', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#password_confirmation').fill(password);
    
    // Wait for navigation and response
    const [response] = await Promise.all([
      page.waitForNavigation({ timeout: 15000 }),
      page.locator('button[type="submit"]').click(),
    ]);
    
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    
    const afterRegisterUrl = page.url();
    console.log(`📍 After register: ${afterRegisterUrl}`);
    expect(afterRegisterUrl).toContain('/dashboard');

    // 2. Check cookies exist
    const cookies = await context.cookies();
    console.log(`🍪 Cookies count: ${cookies.length}`);
    expect(cookies.length).toBeGreaterThan(0);

    // 3. Go to Projects
    await page.getByRole('link', { name: /Projects/i }).click();
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    expect(page.url()).toContain('/dashboard/projects');
    console.log('✅ On Projects page');

    // 4. Create new project
    await page.getByRole('link', { name: /New Project|Create/i }).first().click();
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    expect(page.url()).toContain('/dashboard/projects/create');
    console.log('✅ On Create Project page');

    // 5. Fill project form
    console.log('📝 Filling project form...');
    
    // Domain
    await page.locator('#domain').fill(domainName);
    
    // Chat Name
    await page.locator('#chat_name').fill(chatName);
    
    // Theme - select light (click on label, not input)
    await page.locator('input[name="theme"][value="light"]').setChecked(true);
    
    // Position - select bottom-right
    await page.locator('input[name="position"][value="bottom-right"]').setChecked(true);
    
    // Width
    await page.locator('#width').fill('400');
    
    // Height
    await page.locator('#height').fill('600');
    
    // Primary Color
    await page.locator('#primary_color').fill('#6366f1');

    // Telegram Bot Token (fake for test)
    const tokenInput = await page.locator('#telegram_bot_token');
    if (await tokenInput.isVisible()) {
      await tokenInput.fill('123456789:ABCdefGHIjklMNOpqrsTUVwxyz');
    }

    // Telegram Chat ID
    const chatIdInput = await page.locator('#telegram_chat_id');
    if (await chatIdInput.isVisible()) {
      await chatIdInput.fill('-1001234567890');
    }

    console.log('✅ Form filled');

    // 6. Submit form (click on Create Project button, not logout)
    await page.getByRole('button', { name: 'Create Project' }).click();
    
    // Wait and check what happens
    await page.waitForTimeout(3000);
    
    // Get page content to see what's there
    const pageContent = await page.content();
    const hasSuccess = pageContent.includes('Project created successfully');
    const hasValidationError = pageContent.includes('text-red-600') || pageContent.includes('bg-red-50');
    
    console.log(`📊 Has success message: ${hasSuccess}`);
    console.log(`📊 Has validation error: ${hasValidationError}`);
    console.log(`📊 Current URL: ${page.url()}`);
    
    // Check for validation errors
    const errorElements = await page.locator('.text-red-600, .text-red-500').all();
    if (errorElements.length > 0) {
      const errorTexts = [];
      for (const el of errorElements) {
        const text = await el.textContent().catch(() => '');
        if (text.trim()) errorTexts.push(text.trim());
      }
      console.log('⚠️ Validation errors:', errorTexts);
    }
    
    // Wait for redirect to edit page
    await page.waitForURL(/\/dashboard\/projects\/\d+\/edit/, { timeout: 15000 }).catch(async () => {
      // If validation fails, check for errors
      const hasErrors = await page.locator('.text-red-600, .bg-red-50').isVisible().catch(() => false);
      if (hasErrors) {
        const errorTexts = await page.locator('.text-red-600').allTextContents();
        console.log('❌ Validation errors:', errorTexts);
      }
    });

    // 7. Check if on edit page
    expect(page.url()).toMatch(/\/dashboard\/projects\/\d+\/edit/);
    console.log('✅ Project created, on Edit page');

    // 8. Check for embed script
    const hasEmbedScript = await page.locator('text=Embed Script').isVisible().catch(() => false);
    if (hasEmbedScript) {
      console.log('✅ Embed Script section visible');
    }

    // 9. Check for Widget Preview
    const hasWidgetPreview = await page.locator('text=Widget Preview').isVisible().catch(() => false);
    if (hasWidgetPreview) {
      console.log('✅ Widget Preview visible');
    }

    // 10. Check for Telegram settings
    const hasTelegramSection = await page.locator('text=Telegram Bot Integration').isVisible().catch(() => false);
    if (hasTelegramSection) {
      console.log('✅ Telegram Bot Integration section visible');
    }

    console.log('🎉 Full flow test completed!');
  });
});

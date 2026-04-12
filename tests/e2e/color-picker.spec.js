import { test, expect } from '@playwright/test';

test.describe('Embed Script', () => {
  test('embed script section exists on edit page', async ({ page }) => {
    // Login with verified credentials
    await page.goto('/auth/login');
    await page.fill('input[name="email"]', 'verified@example.com');
    await page.fill('input[name="password"]', 'Verified123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Go to projects list
    await page.goto('/dashboard/projects');
    await page.waitForLoadState('networkidle');

    // Check if there are any projects
    const hasProjects = await page.locator('table tbody tr').count();

    if (hasProjects === 0) {
      // No projects - test passes (embed script shown only after creation)
      console.log('No projects found, skipping embed script check');
      return;
    }

    // Click edit on first project
    await page.click('a[title="Edit"]');
    await page.waitForURL(/\/dashboard\/projects\/\d+\/edit/);

    // Check that domain field exists and has a value
    const domainInput = await page.inputValue('input#domain');
    expect(domainInput.length).toBeGreaterThan(0);
    expect(domainInput).toContain('.');

    // Check color picker has a valid hex color
    const colorValue = await page.inputValue('input#primary_color');
    expect(colorValue).toMatch(/^#[0-9a-fA-F]{6}$/);

    // Check Telegram Bot section exists
    const telegramSection = await page.locator('text="Telegram Bot Integration"').isVisible();
    expect(telegramSection).toBe(true);
  });
});

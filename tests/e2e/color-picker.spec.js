import { test, expect } from '@playwright/test';

test.describe('Domain Project', () => {
  test('create project with domain', async ({ page }) => {
    // Login with verified credentials
    await page.goto('/auth/login');
    await page.fill('input[name="email"]', 'verified@example.com');
    await page.fill('input[name="password"]', 'Verified123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Go to create project
    await page.goto('/dashboard/projects/create');
    await page.waitForLoadState('networkidle');

    // Check domain input exists
    const domainInput = await page.inputValue('input#domain');
    expect(domainInput).toBe('');

    // Fill domain
    await page.fill('input#domain', 'test.example.com');

    // Check the form loads correctly
    const domainValue = await page.inputValue('input#domain');
    expect(domainValue).toBe('test.example.com');
  });
});

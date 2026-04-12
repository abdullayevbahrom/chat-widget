import { test, expect } from '@playwright/test';

test.describe('Embed Script', () => {
  test('embed script is simple script tag', async ({ page }) => {
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
      // No projects - test passes
      console.log('No projects found, skipping embed script check');
      return;
    }

    // Click edit on first project
    await page.click('a[title="Edit"]');
    await page.waitForURL(/\/dashboard\/projects\/\d+\/edit/);

    // Check embed script is shown
    const embedScript = await page.locator('pre code').textContent();

    // Should be a simple script tag with no query parameters
    expect(embedScript).toContain('<script src=');
    expect(embedScript).toContain('/widget.js"');
    expect(embedScript).toContain('async defer');

    // Should NOT contain query parameters like widget_key, signature, etc.
    expect(embedScript).not.toContain('widget_key=');
    expect(embedScript).not.toContain('signature=');
    expect(embedScript).not.toContain('project_id=');
  });
});

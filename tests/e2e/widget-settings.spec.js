import { test, expect } from '@playwright/test';

const appURL = process.env.APP_BASE_URL || 'http://localhost:7080';

async function registerTenant(page, email, password) {
  await page.goto(`${appURL}/auth/register`, { waitUntil: 'networkidle' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="password_confirmation"]').fill(password);
  await page.getByRole('button', { name: /create account/i }).click();
  await page.waitForURL(/\/dashboard$/, { timeout: 15000 });
}

async function createProject(page, domain) {
  await page.goto(`${appURL}/dashboard/projects/create`, { waitUntil: 'networkidle' });
  await page.locator('input[name="domain"]').fill(domain);
  await page.locator('input[name="chat_name"]').fill('Custom Support');
  await page.locator('textarea[name="greeting_message"]').fill('Configured greeting');
  await page.locator('input[name="privacy_policy_url"]').fill('https://example.com/privacy-policy');
  await page.locator('input[name="width"]').fill('420');
  await page.locator('input[name="height"]').fill('640');
  await page.locator('input[name="primary_color"]').fill('#22c55e');
  await page.locator('textarea[name="custom_css"]').fill('#widget-window { outline: 3px solid rgb(255, 0, 0) !important; }');
  await page.locator('input[name="theme"][value="light"]').check({ force: true });
  await page.locator('input[name="position"][value="top-left"]').check({ force: true });
  await page.getByRole('button', { name: /create project/i }).click();
  await page.waitForURL(/\/dashboard\/projects\/\d+\/edit$/, { timeout: 15000 });
}

async function openWidget(page, widgetBaseURL) {
  await page.addInitScript((url) => {
    window.WIDGET_API_BASE = url;
  }, appURL);
  await page.goto(`${widgetBaseURL}/test-widget`, { waitUntil: 'networkidle' });
  const bubble = page.locator('#widget-bubble');
  await expect(bubble).toBeVisible({ timeout: 15000 });
  await bubble.click();
}

test('widget project settings are applied in real widget', async ({ browser }) => {
  const email = `widget-settings-${Date.now()}@example.com`;
  const password = 'E2ePass123!';
  const domain = `widget-settings-${Date.now()}.lvh.me`;
  const widgetBaseURL = `http://${domain}:7080`;

  const adminPage = await browser.newPage();
  await adminPage.setViewportSize({ width: 1440, height: 1200 });
  await registerTenant(adminPage, email, password);
  await createProject(adminPage, domain);

  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 1200 });
  await openWidget(page, widgetBaseURL);

  const bubble = page.locator('#widget-bubble');
  const widgetWindow = page.locator('#widget-window');
  const title = page.locator('#widget-project-name');
  const privacyLink = page.locator('.widget-prechat-checkbox a');

  await expect(title).toHaveText('Start chat');
  await expect(privacyLink).toHaveAttribute('href', 'https://example.com/privacy-policy');

  const bubbleStyle = await bubble.evaluate((el) => {
    const style = getComputedStyle(el);
    return {
      backgroundColor: style.backgroundColor,
      top: style.top,
      left: style.left,
    };
  });
  expect(bubbleStyle.backgroundColor).toBe('rgb(34, 197, 94)');
  expect(bubbleStyle.top).toBe('24px');
  expect(bubbleStyle.left).toBe('24px');

  const windowStyle = await widgetWindow.evaluate((el) => {
    const style = getComputedStyle(el);
    return {
      top: style.top,
      left: style.left,
      width: style.width,
      height: style.height,
      backgroundColor: style.backgroundColor,
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      outlineColor: style.outlineColor,
    };
  });
  expect(windowStyle.top).toBe('90px');
  expect(windowStyle.left).toBe('24px');
  expect(windowStyle.width).toBe('420px');
  expect(windowStyle.height).toBe('640px');
  expect(windowStyle.backgroundColor).toBe('rgb(255, 255, 255)');
  expect(windowStyle.outlineStyle).toBe('solid');
  expect(windowStyle.outlineWidth).toBe('3px');
  expect(windowStyle.outlineColor).toBe('rgb(255, 0, 0)');

  await page.locator('#widget-prechat-name').fill('Theme Tester');
  await page.locator('#widget-prechat-privacy').check();
  await page.locator('#widget-prechat-submit').click();

  await expect(title).toHaveText('Custom Support');
  await expect(page.locator('.widget-message.inbound').first()).toContainText('Configured greeting');

  const headerAvatarStyle = await page.locator('.widget-header-avatar').evaluate((el) => {
    const style = getComputedStyle(el);
    return { backgroundColor: style.backgroundColor };
  });
  expect(headerAvatarStyle.backgroundColor).toBe('rgb(34, 197, 94)');

  await page.locator('#widget-message-input').fill('Check button color');
  const sendBtnStyle = await page.locator('#widget-send-btn').evaluate((el) => {
    const style = getComputedStyle(el);
    return { backgroundImage: style.backgroundImage };
  });
  expect(sendBtnStyle.backgroundImage).toContain('rgb(34, 197, 94)');
});

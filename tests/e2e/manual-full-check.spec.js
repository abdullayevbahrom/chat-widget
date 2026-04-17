import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const appURL = process.env.APP_BASE_URL || 'http://localhost:7080';
const reportDir = path.resolve('playwright-report/full-walkthrough');

function ensureDir() {
  fs.mkdirSync(reportDir, { recursive: true });
}

function shot(name) {
  return path.join(reportDir, `${name}.png`);
}

function phpEval(code) {
  const encoded = Buffer.from(code, 'utf8').toString('base64');

  return execFileSync(
    'docker',
    [
      'exec',
      'widget-php-1',
      'php',
      '-r',
      `require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); eval(base64_decode("${encoded}"));`,
    ],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
  ).trim();
}

async function registerTenant(page, email, password) {
  await page.goto(`${appURL}/auth/register`, { waitUntil: 'networkidle' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="password_confirmation"]').fill(password);
  await page.getByRole('button', { name: /create account/i }).click();
  await page.waitForURL(/\/dashboard$/, { timeout: 15000 });
}

async function createProject(page, domain, chatName, privacyUrl) {
  await page.goto(`${appURL}/dashboard/projects/create`, { waitUntil: 'networkidle' });
  await page.locator('input[name="domain"]').fill(domain);
  await page.locator('input[name="chat_name"]').fill(chatName);
  await page.locator('textarea[name="greeting_message"]').fill('Hello from Playwright walkthrough');
  await page.locator('input[name="privacy_policy_url"]').fill(privacyUrl);
  await page.getByRole('button', { name: /^add$/i }).click();
  const row = page.locator('.telegram-admin-row').first();
  await row.locator('[data-role="chat-id"]').fill('123456789');
  await row.locator('[data-role="name"]').fill('Primary Admin');
  await page.getByRole('button', { name: /create project/i }).click();
  await page.waitForURL(/\/dashboard\/projects\/\d+\/edit$/, { timeout: 15000 });
}

async function openWidget(page, widgetBaseURL) {
  await page.addInitScript((url) => {
    window.WIDGET_API_BASE = url;
  }, appURL);
  await page.goto(`${widgetBaseURL}/test-widget`, { waitUntil: 'networkidle' });
  await page.locator('#widget-bubble').click();
}

test.describe.configure({ mode: 'serial' });

test('full walkthrough with screenshots', async ({ browser, page }) => {
  test.setTimeout(180000);
  ensureDir();

  const email = `walkthrough-${Date.now()}@example.com`;
  const password = 'Walkthrough123!';
  const domain = `walkthrough-${Date.now()}.lvh.me`;
  const widgetBaseURL = `http://${domain}:7080`;
  const chatName = 'Walkthrough Support';
  const privacyUrl = 'https://example.com/privacy';
  const visitorName = 'Widget Visitor';
  const visitorMessage = `Visitor message ${Date.now()}`;
  const dashboardReply = `Dashboard reply ${Date.now()}`;
  const miniReply = `Mini app reply ${Date.now()}`;

  await page.goto(appURL, { waitUntil: 'networkidle' });
  await page.screenshot({ path: shot('01-home-page'), fullPage: true });

  await page.goto(`${appURL}/auth/login`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: shot('02-login-page'), fullPage: true });

  await registerTenant(page, email, password);
  await page.screenshot({ path: shot('03-dashboard-page'), fullPage: true });

  await page.goto(`${appURL}/dashboard/projects`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: shot('04-projects-page'), fullPage: true });

  await page.goto(`${appURL}/dashboard/projects/create`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: shot('05-project-create-page'), fullPage: true });

  await createProject(page, domain, chatName, privacyUrl);
  await page.screenshot({ path: shot('06-project-edit-page'), fullPage: true });

  const visitorPage = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
  await openWidget(visitorPage, widgetBaseURL);
  await expect(visitorPage.locator('#widget-prechat-name')).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('07-widget-prechat-page'), fullPage: true });

  await visitorPage.locator('#widget-prechat-name').fill(visitorName);
  await visitorPage.locator('#widget-prechat-privacy').check();
  await visitorPage.locator('#widget-prechat-submit').click();
  await visitorPage.locator('#widget-message-input').fill(visitorMessage);
  await visitorPage.locator('#widget-send-btn').click();
  await expect(visitorPage.locator('.widget-message').filter({ hasText: visitorMessage })).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('08-widget-chat-page'), fullPage: true });

  await page.goto(`${appURL}/dashboard/conversations`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: shot('09-conversations-page'), fullPage: true });

  const conversationRow = page.locator('tr').filter({ hasText: visitorMessage }).first();
  await expect(conversationRow).toBeVisible({ timeout: 15000 });
  await conversationRow.click();
  await page.waitForURL(/\/dashboard\/conversations\/\d+$/, { timeout: 15000 });
  await page.screenshot({ path: shot('10-conversation-detail-page'), fullPage: true });

  await visitorPage.locator('#widget-close').click();
  await page.locator('textarea[name="body"]').fill(dashboardReply);
  await page.getByRole('button', { name: /send reply/i }).click();
  await expect(page.locator('body')).toContainText(dashboardReply);
  await visitorPage.locator('#widget-bubble-badge.active').waitFor({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('11-widget-notification-badge-page'), fullPage: true });

  await visitorPage.locator('#widget-bubble').click();
  await expect(visitorPage.locator('.widget-message').filter({ hasText: dashboardReply })).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('12-widget-after-dashboard-reply-page'), fullPage: true });

  const signedMiniAppListUrl = phpEval(`
    $project = \\App\\Models\\Project::query()->where('domain', ${JSON.stringify(domain)})->latest('id')->firstOrFail();
    echo \\Illuminate\\Support\\Facades\\URL::signedRoute('telegram.mini-app', ['project' => $project->id]);
  `);

  const miniPage = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
  await miniPage.goto(signedMiniAppListUrl, { waitUntil: 'networkidle' });
  await miniPage.screenshot({ path: shot('13-mini-app-list-page'), fullPage: true });

  await miniPage.getByRole('link', { name: new RegExp(visitorName, 'i') }).first().click();
  await expect(miniPage.locator('body')).toContainText(visitorMessage);
  await miniPage.screenshot({ path: shot('14-mini-app-detail-page'), fullPage: true });

  await miniPage.locator('textarea[name="body"]').fill(miniReply);
  await miniPage.getByRole('button', { name: /send/i }).click();
  await expect(miniPage.locator('body')).toContainText(miniReply);
  await miniPage.screenshot({ path: shot('15-mini-app-after-reply-page'), fullPage: true });

  await visitorPage.locator('#widget-close').click();
  await visitorPage.locator('#widget-bubble-badge.active').waitFor({ timeout: 15000 });
  await visitorPage.locator('#widget-bubble').click();
  await expect(visitorPage.locator('.widget-message').filter({ hasText: miniReply })).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('16-widget-after-mini-app-reply-page'), fullPage: true });
});

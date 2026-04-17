import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const appURL = process.env.APP_BASE_URL || 'http://localhost:7080';
const reportDir = path.resolve('playwright-report/manual-full-check');
const runStamp = new Date().toISOString().replace(/[:.]/g, '-');
const shot = (name) => path.join(reportDir, `${runStamp}-${name}.png`);

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

function ensureDir() {
  fs.mkdirSync(reportDir, { recursive: true });
}

async function registerTenant(page, email, password) {
  await page.goto(`${appURL}/auth/register`, { waitUntil: 'networkidle' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="password_confirmation"]').fill(password);
  await page.getByRole('button', { name: /create account/i }).click();
  await page.waitForURL(/\/dashboard$/, { timeout: 15000 });
}

async function createProject(page, domain, chatName, privacyUrl, adminsText) {
  await page.goto(`${appURL}/dashboard/projects/create`, { waitUntil: 'networkidle' });
  await page.locator('input[name="domain"]').fill(domain);
  await page.locator('input[name="chat_name"]').fill(chatName);
  await page.locator('textarea[name="greeting_message"]').fill('Hello from E2E');
  await page.locator('input[name="privacy_policy_url"]').fill(privacyUrl);
  await page.locator('textarea[name="telegram_admins_text"]').fill(adminsText);
  await page.getByRole('button', { name: /create project/i }).click();
  await page.waitForURL(/\/dashboard\/projects\/\d+\/edit$/, { timeout: 15000 });
}

async function openWidget(page, widgetBaseURL) {
  await page.addInitScript((url) => {
    window.WIDGET_API_BASE = url;
  }, appURL);
  await page.goto(`${widgetBaseURL}/test-widget`, { waitUntil: 'networkidle' });
  const openButton = page.locator('button[aria-label="Open chat"]').first();
  await expect(openButton).toBeVisible({ timeout: 15000 });
  await openButton.click();
}

test.describe.configure({ mode: 'serial' });

test('manual full check: register -> project -> widget -> dashboard -> mini app', async ({ browser }) => {
  test.setTimeout(180000);
  ensureDir();

  const email = `e2e-${Date.now()}@example.com`;
  const password = 'E2ePass123!';
  const domain = `e2e-${Date.now()}.lvh.me`;
  const widgetBaseURL = `http://${domain}:7080`;
  const chatName = 'E2E Support';
  const privacyUrl = 'https://example.com/privacy';
  const adminsText = '111111111|Ali Admin|111111111\n222222222|Vali Admin|222222222';
  const visitorName = 'Widget Visitor';
  const widgetMessage = `Visitor message ${Date.now()}`;
  const dashboardReply = `Dashboard reply ${Date.now()}`;
  const miniReply = `Mini app reply ${Date.now()}`;

  const tenantPage = await browser.newPage();
  await registerTenant(tenantPage, email, password);
  await tenantPage.screenshot({ path: shot('01-dashboard-after-register'), fullPage: true });

  await createProject(tenantPage, domain, chatName, privacyUrl, adminsText);
  await expect(tenantPage.locator('textarea[name="telegram_admins_text"]')).toContainText('Ali Admin');
  await tenantPage.screenshot({ path: shot('02-project-edit'), fullPage: true });

  const visitorPage = await browser.newPage();
  await openWidget(visitorPage, widgetBaseURL);
  await expect(visitorPage.locator('#widget-prechat-name')).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('03-widget-prechat'), fullPage: true });
  await visitorPage.locator('#widget-prechat-name').fill(visitorName);
  await visitorPage.locator('#widget-prechat-privacy').check();
  await visitorPage.locator('#widget-prechat-submit').click();
  await expect(visitorPage.locator('#widget-message-input')).toBeVisible({ timeout: 15000 });
  await visitorPage.locator('#widget-message-input').fill(widgetMessage);
  await visitorPage.locator('#widget-send-btn').click();
  await expect(visitorPage.locator('.widget-message').filter({ hasText: widgetMessage })).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('04-widget-after-visitor-message'), fullPage: true });

  await tenantPage.goto(`${appURL}/dashboard/conversations`, { waitUntil: 'networkidle' });
  const conversationRow = tenantPage.locator('tr').filter({ hasText: widgetMessage }).first();
  await expect(conversationRow).toBeVisible({ timeout: 15000 });
  await conversationRow.click();
  await tenantPage.waitForURL(/\/dashboard\/conversations\/\d+$/, { timeout: 15000 });
  await expect(tenantPage.locator('body')).toContainText(widgetMessage);
  await tenantPage.screenshot({ path: shot('05-conversation-opened'), fullPage: true });

  await tenantPage.locator('textarea[name="body"]').fill(dashboardReply);
  await tenantPage.getByRole('button', { name: /send reply/i }).click();
  await expect(tenantPage.locator('body')).toContainText(dashboardReply);
  await tenantPage.screenshot({ path: shot('06-dashboard-replied'), fullPage: true });

  await visitorPage.reload({ waitUntil: 'networkidle' });
  await openWidget(visitorPage, widgetBaseURL);
  await expect(visitorPage.locator('.widget-message').filter({ hasText: dashboardReply })).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('07-widget-after-dashboard-reply'), fullPage: true });

  const signedMiniAppUrl = phpEval(`
    $project = \\App\\Models\\Project::query()->where('domain', ${JSON.stringify(domain)})->latest('id')->firstOrFail();
    $message = \\App\\Models\\Message::query()
        ->where('body', ${JSON.stringify(widgetMessage)})
        ->latest('id')
        ->firstOrFail();
    $conversation = \\App\\Models\\Conversation::withoutGlobalScopes()->findOrFail($message->conversation_id);
    echo \\Illuminate\\Support\\Facades\\URL::signedRoute('telegram.mini-app', [
        'project' => $project->id,
        'conversation' => $conversation->public_id,
    ]);
  `);

  const miniPage = await browser.newPage();
  await miniPage.goto(signedMiniAppUrl, { waitUntil: 'networkidle' });
  await expect(miniPage.locator('body')).toContainText(widgetMessage);
  await miniPage.screenshot({ path: shot('08-mini-app-opened'), fullPage: true });
  await miniPage.locator('textarea[name="body"]').fill(miniReply);
  await miniPage.getByRole('button', { name: /send/i }).click();
  await expect(miniPage.locator('body')).toContainText(miniReply);
  await miniPage.screenshot({ path: shot('09-mini-app-replied'), fullPage: true });

  await tenantPage.reload({ waitUntil: 'networkidle' });
  await expect(tenantPage.locator('body')).toContainText(miniReply);
  await tenantPage.screenshot({ path: shot('10-conversation-after-mini-app-reply'), fullPage: true });

  await visitorPage.reload({ waitUntil: 'networkidle' });
  await openWidget(visitorPage, widgetBaseURL);
  await expect(visitorPage.locator('.widget-message').filter({ hasText: miniReply })).toBeVisible({ timeout: 15000 });
  await visitorPage.screenshot({ path: shot('11-widget-after-mini-app-reply'), fullPage: true });

  expect(fs.existsSync(reportDir)).toBeTruthy();
});

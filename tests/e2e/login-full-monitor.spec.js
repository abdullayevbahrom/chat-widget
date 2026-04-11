// @ts-check
import { test, expect } from '@playwright/test';

test('Login - full monitoring', async ({ page }) => {
  const requests = [];
  const responses = [];
  
  page.on('request', (request) => {
    if (request.url().includes('livewire')) {
      requests.push({
        url: request.url(),
        method: request.method(),
        postData: request.postData()?.substring(0, 200)
      });
    }
  });
  
  page.on('response', async (response) => {
    if (response.url().includes('livewire')) {
      responses.push({
        url: response.url(),
        status: response.status(),
      });
    }
  });
  
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  await page.locator('#form\\.email').click();
  await page.locator('#form\\.email').pressSequentially('testuser@example.com', { delay: 30 });
  await page.locator('#form\\.password').click();
  await page.locator('#form\\.password').pressSequentially('TestUser123!', { delay: 30 });
  
  await page.waitForTimeout(500);
  
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  await page.waitForTimeout(5000);
  
  console.log(`📊 Requests: ${requests.length}`);
  requests.forEach((req, i) => {
    console.log(`   ${i+1}. ${req.method} ${req.url.substring(0, 80)}`);
  });
  
  console.log(`📊 Responses: ${responses.length}`);
  responses.forEach((res, i) => {
    console.log(`   ${i+1}. ${res.status} ${res.url.substring(0, 80)}`);
  });
  
  console.log(`📍 Final URL: ${page.url()}`);
  console.log(`📄 Title: ${await page.title()}`);
  
  await page.screenshot({ path: 'test-results/screenshots/login-full.png', fullPage: true });
});

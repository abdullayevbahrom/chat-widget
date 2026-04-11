// @ts-check
import { test, expect } from '@playwright/test';

test('Login - Livewire request monitoring', async ({ page }) => {
  let livewireRequestFound = false;
  let livewireResponseFound = false;
  let requestUrl = '';
  let responseStatus = 0;
  let responseBody = '';
  
  // Request monitoring
  page.on('request', (request) => {
    if (request.url().includes('/livewire/') || request.url().includes('livewire/update')) {
      livewireRequestFound = true;
      requestUrl = request.url();
      console.log(`📤 Livewire request: ${requestUrl.substring(0, 100)}`);
    }
  });
  
  // Response monitoring
  page.on('response', async (response) => {
    if (response.url().includes('/livewire/') || response.url().includes('livewire/update')) {
      livewireResponseFound = true;
      responseStatus = response.status();
      try {
        const body = await response.json();
        responseBody = JSON.stringify(body).substring(0, 500);
        console.log(`📥 Livewire response: ${responseStatus}`);
        console.log(`📦 Body preview: ${responseBody.substring(0, 200)}`);
      } catch (e) {
        console.log(`📥 Livewire response: ${responseStatus} (JSON parse error)`);
      }
    }
  });
  
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  await page.locator('#form\\.email').click();
  await page.locator('#form\\.email').pressSequentially('testuser@example.com', { delay: 30 });
  await page.locator('#form\\.password').click();
  await page.locator('#form\\.password').pressSequentially('TestUser123!', { delay: 30 });
  
  await page.waitForTimeout(500);
  
  console.log('🔘 Submitting form...');
  await page.locator('button[type="submit"]:has-text("Sign in")').click();
  
  await page.waitForTimeout(5000);
  
  console.log(`\n📊 Results:`);
  console.log(`   Livewire request: ${livewireRequestFound ? '✅' : '❌'}`);
  console.log(`   Livewire response: ${livewireResponseFound ? '✅' : '❌'}`);
  console.log(`   Request URL: ${requestUrl}`);
  console.log(`   Response status: ${responseStatus}`);
  console.log(`   Final URL: ${page.url()}`);
  
  await page.screenshot({ path: 'test-results/screenshots/livewire-debug.png', fullPage: true });
});

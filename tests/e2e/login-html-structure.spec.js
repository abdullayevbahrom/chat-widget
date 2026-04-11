// @ts-check
import { test, expect } from '@playwright/test';

test('Login HTML structure', async ({ page }) => {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  
  // HTML structureni olish
  const htmlStructure = await page.evaluate(() => {
    const form = document.querySelector('form');
    if (!form) return { error: 'Form topilmadi' };
    
    return {
      action: form.action,
      method: form.method,
      hasWireSubmit: form.hasAttribute('wire:submit') || form.getAttribute('@submit')?.includes('prevent'),
      wireSubmit: form.getAttribute('wire:submit'),
      inputs: Array.from(form.querySelectorAll('input')).map(input => ({
        id: input.id,
        type: input.type,
        wireModel: input.getAttribute('wire:model'),
        value: input.value,
      })),
      buttons: Array.from(form.querySelectorAll('button')).map(btn => ({
        type: btn.type,
        text: btn.textContent?.trim(),
        wireClick: btn.getAttribute('wire:click'),
      })),
    };
  });
  
  console.log('📋 Form structure:');
  console.log(JSON.stringify(htmlStructure, null, 2));
  
  // Agar wire:submit bo'lsa, uni to'g'ri ishlatish kerak
  if (htmlStructure.wireSubmit) {
    console.log(`🔗 Wire submit: ${htmlStructure.wireSubmit}`);
    
    // Inputlarni to'ldirish
    await page.locator('#form\\.email').fill('testuser@example.com');
    await page.locator('#form\\.password').fill('TestUser123!');
    await page.waitForTimeout(500);
    
    // Form ni JavaScript orqali submit qilish
    await page.evaluate(() => {
      const form = document.querySelector('form');
      if (form) {
        const event = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(event);
      }
    });
    
    await page.waitForTimeout(3000);
    console.log(`📍 URL: ${page.url()}`);
  }
  
  await page.screenshot({ path: 'test-results/screenshots/login-html-structure.png', fullPage: true });
});

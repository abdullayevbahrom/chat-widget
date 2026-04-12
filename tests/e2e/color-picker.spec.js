import { test, expect } from '@playwright/test';

test.describe('Widget Chat Bubble', () => {
  test('widget bubble icon appears on page', async ({ page }) => {
    // Listen for console messages
    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    
    // Open test page
    await page.goto('/test-widget');
    await page.waitForLoadState('networkidle');
    
    // Check widget.js script is loaded
    const widgetScript = await page.locator('script[src*="widget.js"]');
    await expect(widgetScript).toHaveCount(1);
    
    // Wait for widget to initialize
    await page.waitForTimeout(3000);
    
    // Take screenshot for debugging
    await page.screenshot({ path: 'test-results/widget-debug-1.png', fullPage: true });
    
    // Check if body has the toggle button
    const bodyContent = await page.content();
    console.log('Page contains widget-toggle-btn:', bodyContent.includes('widget-toggle-btn'));
    console.log('Page contains widget-sdk-styles:', bodyContent.includes('widget-sdk-styles'));
    
    // Check toggle button exists
    const toggleBtn = await page.locator('#widget-toggle-btn');
    const count = await toggleBtn.count();
    console.log('Toggle button count:', count);
    
    await expect(toggleBtn).toBeVisible({ timeout: 10000 });
    
    // Check button has correct styles
    const styles = await toggleBtn.evaluate(el => {
      const computed = window.getComputedStyle(el);
      return {
        position: computed.position,
        width: computed.width,
        height: computed.height,
        borderRadius: computed.borderRadius,
        zIndex: computed.zIndex,
        display: computed.display,
      };
    });
    
    expect(styles.position).toBe('fixed');
    expect(parseInt(styles.width)).toBeGreaterThan(50);
    expect(parseInt(styles.height)).toBeGreaterThan(50);
    expect(styles.zIndex).toBe('999999');
    expect(styles.display).not.toBe('none');
    
    // Check chat container exists but is hidden
    const chatContainer = await page.locator('#widget-chat-container');
    await expect(chatContainer).toBeVisible();
    
    // Check container is initially hidden (opacity 0)
    const containerStyles = await chatContainer.evaluate(el => {
      const computed = window.getComputedStyle(el);
      return {
        opacity: computed.opacity,
        pointerEvents: computed.pointerEvents,
      };
    });
    
    expect(containerStyles.opacity).toBe('0');
    expect(containerStyles.pointerEvents).toBe('none');
  });

  test('clicking bubble opens chat', async ({ page }) => {
    // Open test page
    await page.goto('/test-widget');
    await page.waitForLoadState('networkidle');
    
    // Wait for widget to initialize
    await page.waitForTimeout(3000);
    
    // Take screenshot for debugging
    await page.screenshot({ path: 'test-results/widget-debug-2.png', fullPage: true });
    
    // Click the toggle button
    await page.click('#widget-toggle-btn');
    
    // Wait for animation
    await page.waitForTimeout(1000);
    
    // Take screenshot after click
    await page.screenshot({ path: 'test-results/widget-debug-3.png', fullPage: true });
    
    // Check chat container is now visible
    const chatContainer = await page.locator('#widget-chat-container');
    await expect(chatContainer).toBeVisible();
    
    // Check container is now visible (opacity 1)
    const containerStyles = await chatContainer.evaluate(el => {
      const computed = window.getComputedStyle(el);
      return {
        opacity: computed.opacity,
        pointerEvents: computed.pointerEvents,
      };
    });
    
    expect(containerStyles.opacity).toBe('1');
    expect(containerStyles.pointerEvents).toBe('auto');
  });

  test('widget sends message successfully', async ({ page }) => {
    // Open test page
    await page.goto('/test-widget');
    await page.waitForLoadState('networkidle');
    
    // Wait for widget to initialize
    await page.waitForTimeout(3000);
    
    // Click the toggle button to open chat
    await page.click('#widget-toggle-btn');
    await page.waitForTimeout(1000);
    
    // If pre-chat form is visible, fill it
    const preChatForm = await page.locator('#widget-pre-chat-form');
    if (await preChatForm.isVisible()) {
      await page.fill('#widget-visitor-name', 'Test User');
      await page.click('#widget-start-chat-btn');
      await page.waitForTimeout(1500);
    }
    
    // Type and send message
    await page.fill('#widget-input', 'Hello from Playwright test!');
    await page.click('#widget-send-btn');
    
    // Wait for message to appear
    await page.waitForTimeout(2000);
    
    // Check message appears in chat
    const messages = await page.locator('.widget-message');
    const count = await messages.count();
    expect(count).toBeGreaterThan(0);
    
    // Check the message content
    const lastMessage = await messages.last().textContent();
    expect(lastMessage).toContain('Hello from Playwright test!');
  });
});

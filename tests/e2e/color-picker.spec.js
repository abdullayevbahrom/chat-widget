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
    
    // Check bubble button exists
    const bubble = await page.locator('#widget-bubble');
    await expect(bubble).toBeVisible({ timeout: 10000 });
    
    // Check button has correct styles
    const styles = await bubble.evaluate(el => {
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
    expect(styles.zIndex).toBe('1000000');
    expect(styles.display).not.toBe('none');
    
    // Check chat window exists but is hidden
    const chatWindow = await page.locator('#widget-window');
    await expect(chatWindow).toHaveCount(1);
    
    // Check window is initially hidden (no widget-open class)
    const hasOpenClass = await chatWindow.evaluate(el => el.classList.contains('widget-open'));
    expect(hasOpenClass).toBe(false);
  });

  test('clicking bubble opens chat', async ({ page }) => {
    // Open test page
    await page.goto('/test-widget');
    await page.waitForLoadState('networkidle');
    
    // Wait for widget to initialize
    await page.waitForTimeout(3000);
    
    // Click the bubble button
    await page.click('#widget-bubble');
    
    // Wait for animation
    await page.waitForTimeout(1000);
    
    // Check chat window is now visible
    const chatWindow = await page.locator('#widget-window');
    await expect(chatWindow).toHaveClass(/widget-open/);
  });

  test('widget sends message successfully', async ({ page }) => {
    // Open test page
    await page.goto('/test-widget');
    await page.waitForLoadState('networkidle');
    
    // Wait for widget to initialize
    await page.waitForTimeout(3000);
    
    // Click the bubble to open chat
    await page.click('#widget-bubble');
    await page.waitForTimeout(1000);
    
    // Fill name and start chat
    await page.fill('#widget-name-input', 'Test User');
    await page.click('#widget-start-btn');
    await page.waitForTimeout(1500);
    
    // Type and send message
    await page.fill('#widget-message-input', 'Hello from Playwright test!');
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

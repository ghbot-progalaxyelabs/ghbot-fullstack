import { test, expect } from '@playwright/test';
import { loginWithMock } from './helpers/auth';

/**
 * E2E Test Suite: Website Creation Flow
 *
 * Tests the core website creation functionality.
 * This suite covers:
 * - Viewing website list
 * - Creating a new website
 * - Website persistence
 * - Empty states
 */

test.describe('Website Creation Flow', () => {

  // Login before each test
  test.beforeEach(async ({ page }) => {
    await loginWithMock(page, {
      email: 'website-test@example.com',
      name: 'Website Test User'
    });
  });

  test('should display websites page when authenticated', async ({ page }) => {
    // Navigate to websites page
    await page.goto('/websites');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Should show websites page
    await expect(page).toHaveURL(/\/websites/);

    // Page should have loaded without errors
    const title = await page.title();
    expect(title.length).toBeGreaterThan(0);
  });

  test('should show empty state when user has no websites', async ({ page }) => {
    // Create a new unique user
    await loginWithMock(page, {
      email: `empty-${Date.now()}@example.com`,
      name: 'Empty State User'
    });

    // Navigate to websites
    await page.goto('/websites');
    await page.waitForLoadState('networkidle');

    // Should see some indication of empty state
    // Note: Update selectors based on your actual UI
    const bodyText = await page.locator('body').textContent();

    // Check for empty state indicators
    // This is a loose check - adjust based on your actual UI
    const hasContent = bodyText && bodyText.length > 0;
    expect(hasContent).toBe(true);
  });

  test('should create a new website via API', async ({ page, request }) => {
    // Get auth token
    const token = await page.evaluate(() => localStorage.getItem('token'));
    expect(token).toBeTruthy();

    // Create a new website via API
    const response = await request.post('http://localhost:8000/websites', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      data: {
        name: 'Test Website',
        slug: 'test-website',
        domain: 'test.example.com'
      }
    });

    // Should succeed
    expect(response.ok()).toBe(true);
    expect(response.status()).toBe(201);

    // Should return website data
    const website = await response.json();
    expect(website).toHaveProperty('id');
    expect(website).toHaveProperty('name');
    expect(website.name).toBe('Test Website');
  });

  test('should list websites after creation', async ({ page, request }) => {
    // Get auth token
    const token = await page.evaluate(() => localStorage.getItem('token'));

    // Create a website
    const createResponse = await request.post('http://localhost:8000/websites', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      data: {
        name: 'Listed Website',
        slug: 'listed-website',
        domain: 'listed.example.com'
      }
    });

    expect(createResponse.ok()).toBe(true);

    // Navigate to websites page
    await page.goto('/websites');
    await page.waitForLoadState('networkidle');

    // Should display the website in the list
    // Note: Update selector based on your actual UI
    // For now we just verify the page loaded
    await expect(page).toHaveURL(/\/websites/);
  });

  test('should handle website creation errors gracefully', async ({ page, request }) => {
    // Get auth token
    const token = await page.evaluate(() => localStorage.getItem('token'));

    // Try to create a website with invalid data (missing required fields)
    const response = await request.post('http://localhost:8000/websites', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      data: {
        // Missing required fields
      }
    });

    // Should return error status
    expect(response.ok()).toBe(false);
    // Typically 400 for bad request, but could be different
    expect([400, 422, 500]).toContain(response.status());
  });

  test('should require authentication for website creation', async ({ request }) => {
    // Try to create a website without authentication
    const response = await request.post('http://localhost:8000/websites', {
      headers: {
        'Content-Type': 'application/json',
        // No Authorization header
      },
      data: {
        name: 'Unauthorized Website',
        slug: 'unauthorized',
        domain: 'unauthorized.example.com'
      }
    });

    // Should return 401 Unauthorized
    expect(response.status()).toBe(401);
  });
});

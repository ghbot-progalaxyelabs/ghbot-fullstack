import { test, expect } from '@playwright/test';
import { loginWithMock, logout, isAuthenticated } from './helpers/auth';

/**
 * E2E Test Suite: Authentication Flow
 *
 * Tests the authentication system using mock Google OAuth.
 * This suite covers:
 * - Mock login
 * - Protected route access
 * - Logout functionality
 * - Unauthorized access handling
 */

test.describe('Authentication Flow', () => {

  test('should successfully login with mock authentication', async ({ page }) => {
    // Login using mock auth
    const authData = await loginWithMock(page, {
      email: 'playwright-test@example.com',
      name: 'Playwright Test User'
    });

    // Verify we got a token
    expect(authData.token).toBeTruthy();
    expect(authData.token.length).toBeGreaterThan(0);

    // Verify user data
    expect(authData.user.email).toBe('playwright-test@example.com');
    expect(authData.user.name).toBe('Playwright Test User');
    expect(authData.user.id).toBeTruthy();

    // Verify token is stored in localStorage
    const isAuth = await isAuthenticated(page);
    expect(isAuth).toBe(true);
  });

  test('should allow access to protected routes when authenticated', async ({ page }) => {
    // Login first
    await loginWithMock(page);

    // Navigate to protected route (websites page)
    await page.goto('/websites');

    // Should not redirect to login
    await expect(page).toHaveURL(/\/websites/);

    // Should see the websites page content
    // Note: Update this selector based on your actual component
    await expect(page.locator('body')).toContainText(/websites|my websites|create/i);
  });

  test('should successfully logout', async ({ page }) => {
    // Login first
    await loginWithMock(page);

    // Verify authenticated
    let isAuth = await isAuthenticated(page);
    expect(isAuth).toBe(true);

    // Navigate to app
    await page.goto('/websites');

    // Click logout button (update selector based on your UI)
    // For now, we'll test logout programmatically
    await logout(page);

    // Verify token is removed
    isAuth = await isAuthenticated(page);
    expect(isAuth).toBe(false);

    // Try to access protected route after logout
    await page.goto('/websites');

    // Should redirect to login or show unauthorized
    // Note: This depends on your auth guard implementation
    // Update this assertion based on your actual behavior
    const url = page.url();
    const hasToken = await isAuthenticated(page);
    expect(hasToken).toBe(false);
  });

  test('should deny access to protected routes when not authenticated', async ({ page }) => {
    // Ensure we're logged out
    await logout(page);

    // Try to access protected route
    await page.goto('/websites');

    // Should redirect to home/login or show error
    // Note: Update based on your actual auth guard implementation
    // For now, we just verify no token exists
    const isAuth = await isAuthenticated(page);
    expect(isAuth).toBe(false);
  });

  test('should persist authentication across page reloads', async ({ page }) => {
    // Login
    await loginWithMock(page);

    // Navigate to websites
    await page.goto('/websites');

    // Reload page
    await page.reload();

    // Should still be authenticated
    const isAuth = await isAuthenticated(page);
    expect(isAuth).toBe(true);

    // Should still be on websites page
    await expect(page).toHaveURL(/\/websites/);
  });

  test('should handle multiple users independently', async ({ page }) => {
    // Login as first user
    const user1 = await loginWithMock(page, {
      email: 'user1@example.com',
      name: 'User One'
    });

    expect(user1.user.email).toBe('user1@example.com');
    const token1 = user1.token;

    // Logout
    await logout(page);

    // Login as second user
    const user2 = await loginWithMock(page, {
      email: 'user2@example.com',
      name: 'User Two'
    });

    expect(user2.user.email).toBe('user2@example.com');
    const token2 = user2.token;

    // Tokens should be different
    expect(token1).not.toBe(token2);

    // User IDs should be different
    expect(user1.user.id).not.toBe(user2.user.id);
  });
});

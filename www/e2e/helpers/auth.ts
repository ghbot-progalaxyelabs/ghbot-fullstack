import { Page, APIRequestContext } from '@playwright/test';

/**
 * Authentication helper for E2E tests
 *
 * Provides utilities for logging in with mock authentication
 * and managing authentication state across tests.
 */

export interface MockUser {
  email: string;
  name: string;
}

export interface AuthResponse {
  token: string;
  user: {
    id: string;
    email: string;
    name: string;
    avatarUrl: string;
  };
}

/**
 * Login using the mock authentication endpoint
 *
 * This bypasses Google OAuth and uses the /auth/mock-signin endpoint
 * which is only available when MOCK_AUTH_ENABLED=true
 *
 * @param page Playwright page object
 * @param user User credentials to login with
 * @returns Authentication response with token and user data
 */
export async function loginWithMock(
  page: Page,
  user: MockUser = { email: 'test@example.com', name: 'Test User' }
): Promise<AuthResponse> {
  // Call the mock auth API
  const response = await page.request.post('http://localhost:8000/auth/mock-signin', {
    data: {
      email: user.email,
      name: user.name,
    },
    headers: {
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok()) {
    throw new Error(`Mock login failed: ${response.status()} ${await response.text()}`);
  }

  const authData: AuthResponse = await response.json();

  // Store token in localStorage
  await page.goto('/');
  await page.evaluate((token) => {
    localStorage.setItem('token', token);
  }, authData.token);

  return authData;
}

/**
 * Logout by clearing localStorage
 *
 * @param page Playwright page object
 */
export async function logout(page: Page): Promise<void> {
  await page.evaluate(() => {
    localStorage.removeItem('token');
  });
}

/**
 * Check if user is currently authenticated
 *
 * @param page Playwright page object
 * @returns True if token exists in localStorage
 */
export async function isAuthenticated(page: Page): Promise<boolean> {
  return await page.evaluate(() => {
    return localStorage.getItem('token') !== null;
  });
}

/**
 * Get the current auth token
 *
 * @param page Playwright page object
 * @returns JWT token or null
 */
export async function getAuthToken(page: Page): Promise<string | null> {
  return await page.evaluate(() => {
    return localStorage.getItem('token');
  });
}

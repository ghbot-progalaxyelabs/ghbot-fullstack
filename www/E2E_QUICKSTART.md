# E2E Testing Quick Start

## Run Tests (Easiest Way)

```bash
# Interactive mode with UI
npm run e2e:ui

# Headless mode
npm run e2e

# With visible browser
npm run e2e:headed

# Debug mode
npm run e2e:debug

# View report
npm run e2e:report
```

The playwright.config.ts automatically starts both backend and frontend servers for you!

## What Gets Started Automatically

When you run `npm run e2e`, Playwright automatically:

1. ✅ Starts PHP backend on `http://localhost:8000` with `MOCK_AUTH_ENABLED=true`
2. ✅ Starts Angular frontend on `http://localhost:4200` in testing configuration
3. ✅ Runs all tests in `e2e/` directory
4. ✅ Generates test report

## Test Files

- `e2e/01-authentication.spec.ts` - Authentication flow tests
- `e2e/02-website-creation.spec.ts` - Website creation tests
- `e2e/helpers/auth.ts` - Authentication helper functions

## Mock Authentication

Tests use `/auth/mock-signin` endpoint instead of Google OAuth:

```typescript
import { loginWithMock } from './helpers/auth';

// Login with default user
await loginWithMock(page);

// Login with custom user
await loginWithMock(page, {
  email: 'test@example.com',
  name: 'Test User'
});
```

## Writing New Tests

Create a new file in `e2e/`:

```typescript
// e2e/03-my-feature.spec.ts
import { test, expect } from '@playwright/test';
import { loginWithMock } from './helpers/auth';

test.describe('My Feature', () => {
  test.beforeEach(async ({ page }) => {
    await loginWithMock(page);
  });

  test('should work', async ({ page }) => {
    await page.goto('/my-feature');
    await expect(page.locator('h1')).toBeVisible();
  });
});
```

## Using Docker

```bash
# Full stack with Docker
docker-compose -f ../docker-compose.testing.yaml up -d
docker-compose -f ../docker-compose.testing.yaml run --rm e2e

# Cleanup
docker-compose -f ../docker-compose.testing.yaml down
```

## Troubleshooting

**Tests fail immediately?**
- Make sure ports 8000 and 4200 are available
- Check that you have a `.env` file in the `api/` directory

**Can't see what's happening?**
- Use `npm run e2e:headed` to see the browser
- Use `npm run e2e:ui` for interactive mode

**Need to debug?**
- Use `npm run e2e:debug`
- Add `await page.pause()` in your test

## Full Documentation

See [E2E_TESTING_GUIDE.md](../E2E_TESTING_GUIDE.md) for complete documentation.

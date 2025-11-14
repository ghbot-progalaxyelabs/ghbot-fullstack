# E2E Testing Guide with Playwright

This guide covers the End-to-End (E2E) testing setup for the WebMeteor application using Playwright with mock authentication.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Setup](#setup)
- [Running Tests](#running-tests)
- [Writing Tests](#writing-tests)
- [Docker Testing](#docker-testing)
- [CI/CD Integration](#cicd-integration)
- [Troubleshooting](#troubleshooting)

---

## Overview

The E2E testing infrastructure uses:

- **Playwright** - Modern E2E testing framework
- **Mock Authentication** - Bypasses Google OAuth for deterministic tests
- **Testing Environment** - Separate Angular configuration and backend settings
- **Docker Support** - Isolated testing containers

### Key Features

✅ **No Real OAuth Required** - Tests use mock authentication endpoint
✅ **Full Stack Testing** - Tests both Angular frontend and PHP backend
✅ **Fast Execution** - Parallel test execution support
✅ **CI/CD Ready** - Configured for GitHub Actions and other CI systems
✅ **Isolated Test Database** - Separate PostgreSQL instance for tests

---

## Architecture

### Mock Authentication Flow

```
┌─────────────┐
│   E2E Test  │
└─────┬───────┘
      │
      │ 1. POST /auth/mock-signin
      │    { email, name }
      ↓
┌──────────────────────┐
│  Backend API         │
│  (MOCK_AUTH_ENABLED) │
└─────┬────────────────┘
      │
      │ 2. Create/Get User
      │ 3. Generate JWT
      ↓
┌──────────────────────┐
│   Test Database      │
│   (PostgreSQL)       │
└──────────────────────┘
      │
      │ 4. Return token
      ↓
┌──────────────────────┐
│  Frontend (Angular)  │
│  Testing Config      │
└──────────────────────┘
```

### Environment Configurations

| Configuration | Purpose | API URL | Mock Auth |
|---------------|---------|---------|-----------|
| `development` | Local development | `http://localhost:4402` | ❌ |
| `testing` | E2E tests | `http://localhost:8000` | ✅ |
| `production` | Production build | Production URL | ❌ |

---

## Setup

### Prerequisites

- Node.js 20+
- PHP 8.4+
- PostgreSQL 16
- Docker (optional, for containerized testing)

### Installation

1. **Install Playwright**

```bash
cd www
npm install --save-dev @playwright/test
npx playwright install chromium
```

2. **Configure Environment**

Copy the testing environment file:

```bash
cp .env.testing .env
```

Edit `.env` to match your local setup if needed.

3. **Start Test Database**

If using Docker:

```bash
docker-compose -f docker-compose.testing.yaml up db-test -d
```

Or use your local PostgreSQL instance on port 5433.

4. **Verify Setup**

```bash
cd www
npm run e2e -- --list
```

This should list all available E2E tests.

---

## Running Tests

### Local Development (Recommended)

Run tests with UI mode for debugging:

```bash
cd www
npm run e2e:ui
```

This opens Playwright's interactive test runner with:
- Watch mode
- Visual test execution
- Time-travel debugging
- Step-by-step replay

### Headless Mode

Run all tests in headless mode (CI-style):

```bash
npm run e2e
```

### Specific Test File

```bash
npm run e2e -- e2e/01-authentication.spec.ts
```

### Headed Mode (See Browser)

```bash
npm run e2e:headed
```

### Debug Mode

```bash
npm run e2e:debug
```

This pauses execution at the first test and opens Playwright Inspector.

### View Test Report

After running tests, view the HTML report:

```bash
npm run e2e:report
```

---

## Running Tests Manually (Without playwright.config.ts Auto-Start)

If you prefer to start servers manually:

### 1. Start Backend API

```bash
cd api

# Set environment variables
export MOCK_AUTH_ENABLED=true
export APP_ENV=testing
export JWT_SECRET=test-jwt-secret-key-for-testing-only

# Start PHP server
php -S localhost:8000 -t public
```

### 2. Start Frontend

```bash
cd www
npm run start -- --configuration=testing --port 4200
```

### 3. Run Tests

```bash
cd www
npm run e2e
```

---

## Writing Tests

### Test Structure

```typescript
import { test, expect } from '@playwright/test';
import { loginWithMock } from './helpers/auth';

test.describe('Feature Name', () => {

  // Run before each test
  test.beforeEach(async ({ page }) => {
    await loginWithMock(page);
  });

  test('should do something', async ({ page }) => {
    // Navigate
    await page.goto('/some-route');

    // Interact
    await page.click('[data-testid="button"]');

    // Assert
    await expect(page.locator('h1')).toContainText('Expected Text');
  });
});
```

### Using Mock Authentication

```typescript
import { loginWithMock, logout, isAuthenticated } from './helpers/auth';

// Login with default user
await loginWithMock(page);

// Login with specific user
await loginWithMock(page, {
  email: 'custom@example.com',
  name: 'Custom User'
});

// Check authentication status
const isAuth = await isAuthenticated(page);

// Logout
await logout(page);
```

### Making API Requests in Tests

```typescript
test('should create website via API', async ({ page, request }) => {
  // Get token
  const token = await page.evaluate(() => localStorage.getItem('token'));

  // Make authenticated API request
  const response = await request.post('http://localhost:8000/websites', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    data: {
      name: 'Test Website'
    }
  });

  expect(response.ok()).toBe(true);
  const website = await response.json();
  expect(website.name).toBe('Test Website');
});
```

### Best Practices

1. **Use Test IDs** - Add `data-testid` attributes to important elements

```html
<button data-testid="create-website">Create Website</button>
```

```typescript
await page.click('[data-testid="create-website"]');
```

2. **Wait for Network Idle** - Ensure API calls complete

```typescript
await page.goto('/websites');
await page.waitForLoadState('networkidle');
```

3. **Isolate Tests** - Each test should be independent

```typescript
test.beforeEach(async ({ page }) => {
  // Create fresh user for each test
  await loginWithMock(page, {
    email: `test-${Date.now()}@example.com`,
    name: 'Test User'
  });
});
```

4. **Clean Up** - Remove test data after tests

```typescript
test.afterEach(async ({ page, request }) => {
  // Delete test websites, etc.
});
```

---

## Docker Testing

### Full Docker Setup

Run all services (database, API, frontend, E2E tests) in Docker:

```bash
# Start test environment
docker-compose -f docker-compose.testing.yaml up -d db-test api-test www-test

# Run E2E tests in Docker
docker-compose -f docker-compose.testing.yaml run --rm e2e

# View test results
docker-compose -f docker-compose.testing.yaml run --rm e2e npm run e2e:report

# Stop all services
docker-compose -f docker-compose.testing.yaml down
```

### Individual Service Testing

Start only backend and database:

```bash
docker-compose -f docker-compose.testing.yaml up db-test api-test -d
```

Then run frontend and tests locally.

### Advantages of Docker Testing

✅ Consistent environment across all machines
✅ Isolated test database (auto-reset)
✅ No conflicts with development environment
✅ Easy CI/CD integration

---

## CI/CD Integration

### GitHub Actions Example

Create `.github/workflows/e2e-tests.yml`:

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: webmeteor_test
          POSTGRES_USER: webmeteor
          POSTGRES_PASSWORD: webmeteor_test_password
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install frontend dependencies
        run: |
          cd www
          npm ci

      - name: Install Playwright browsers
        run: |
          cd www
          npx playwright install chromium --with-deps

      - name: Start backend server
        run: |
          cd api
          cp ../.env.testing .env
          php -S localhost:8000 -t public &
        env:
          MOCK_AUTH_ENABLED: true
          APP_ENV: testing

      - name: Start frontend server
        run: |
          cd www
          npm run start -- --configuration=testing &

      - name: Wait for servers
        run: |
          npx wait-on http://localhost:8000 http://localhost:4200 -t 60000

      - name: Run E2E tests
        run: |
          cd www
          npm run e2e

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: www/playwright-report/
          retention-days: 7

      - name: Upload screenshots
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: screenshots
          path: www/test-results/
          retention-days: 7
```

### Using Docker in CI

Alternatively, use Docker Compose in CI:

```yaml
- name: Run E2E tests with Docker
  run: |
    docker-compose -f docker-compose.testing.yaml up --abort-on-container-exit --exit-code-from e2e
```

---

## Troubleshooting

### Tests Fail with "Connection Refused"

**Problem**: API or frontend server not ready

**Solution**: Ensure servers are running and healthy

```bash
# Check API
curl http://localhost:8000

# Check Frontend
curl http://localhost:4200
```

Add wait logic in tests:

```typescript
await page.waitForLoadState('networkidle');
```

### Mock Auth Endpoint Returns 403

**Problem**: `MOCK_AUTH_ENABLED` not set

**Solution**: Check environment variables

```bash
# In API directory
cat .env | grep MOCK_AUTH_ENABLED

# Should show:
# MOCK_AUTH_ENABLED=true
```

Or set it when starting PHP server:

```bash
MOCK_AUTH_ENABLED=true php -S localhost:8000 -t public
```

### Tests Pass Locally But Fail in CI

**Problem**: Timing issues or environment differences

**Solution**:

1. Add explicit waits:

```typescript
await page.waitForSelector('[data-testid="element"]', { timeout: 10000 });
```

2. Increase timeouts in `playwright.config.ts`:

```typescript
use: {
  actionTimeout: 30000, // Increase from 10s to 30s
}
```

3. Use `--workers=1` in CI to avoid parallelization issues:

```yaml
- name: Run E2E tests
  run: npm run e2e -- --workers=1
```

### Database Connection Errors

**Problem**: Cannot connect to test database

**Solution**:

1. Verify PostgreSQL is running on port 5433
2. Check credentials in `.env`
3. Reset test database:

```bash
docker-compose -f docker-compose.testing.yaml down -v
docker-compose -f docker-compose.testing.yaml up db-test -d
```

### Playwright Browsers Not Installed

**Problem**: `Error: browserType.launch: Executable doesn't exist`

**Solution**:

```bash
cd www
npx playwright install chromium
```

Or install all browsers:

```bash
npx playwright install
```

---

## Test Coverage

### Current E2E Tests

| Test Suite | File | Tests | Status |
|------------|------|-------|--------|
| Authentication | `01-authentication.spec.ts` | 6 | ✅ |
| Website Creation | `02-website-creation.spec.ts` | 6 | ✅ |

### Planned Tests

- [ ] Website Editing
- [ ] Multi-page Websites
- [ ] Website Deletion
- [ ] Error Handling
- [ ] Token Expiration
- [ ] Logout Flow
- [ ] GitHub Webhook Integration

---

## Additional Resources

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Angular Testing Guide](https://angular.io/guide/testing)
- [API Test Coverage Report](./TEST_COVERAGE_REPORT.md)

---

## Summary

✅ **Mock auth** bypasses Google OAuth for deterministic tests
✅ **Testing environment** uses separate Angular config and backend settings
✅ **Docker support** provides isolated, reproducible test environment
✅ **CI/CD ready** with GitHub Actions example
✅ **Easy to extend** - add new test files in `e2e/` directory

**Quick Start**:

```bash
# Install and setup
cd www
npm install
npx playwright install chromium

# Run tests (auto-starts servers)
npm run e2e:ui

# Or run headless
npm run e2e
```

Happy testing! 🎭

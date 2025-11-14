import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E testing configuration
 *
 * This configuration:
 * 1. Starts the backend API server (PHP on port 8000)
 * 2. Starts the frontend dev server (Angular in testing mode on port 4200)
 * 3. Runs tests against localhost:4200
 *
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './e2e',

  /* Run tests in files in parallel */
  fullyParallel: true,

  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,

  /* Reporter to use */
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['list'],
    ['json', { outputFile: 'test-results/results.json' }]
  ],

  /* Shared settings for all the projects below. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: 'http://localhost:4200',

    /* Collect trace when retrying the failed test. */
    trace: 'on-first-retry',

    /* Take screenshot only on failure */
    screenshot: 'only-on-failure',

    /* Record video only on failure */
    video: 'retain-on-failure',

    /* Maximum time each action such as `click()` can take */
    actionTimeout: 10000,
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    // Uncomment to test on Firefox and Safari
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },
  ],

  /* Web servers to start before running tests */
  webServer: [
    {
      command: 'cd ../api && php -S localhost:8000 -t public',
      url: 'http://localhost:8000',
      reuseExistingServer: !process.env.CI,
      timeout: 120000,
      env: {
        MOCK_AUTH_ENABLED: 'true',
        APP_ENV: 'testing'
      }
    },
    {
      command: 'ng serve --configuration=testing --port 4200',
      url: 'http://localhost:4200',
      reuseExistingServer: !process.env.CI,
      timeout: 120000,
    }
  ],
});

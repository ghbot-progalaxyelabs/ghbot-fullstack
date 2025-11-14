// Testing environment configuration
// Used for E2E tests with Playwright

export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000',
  googleClientId: 'mock-google-client-id',  // Mock client ID for testing
  mockAuth: true  // Enable mock authentication for testing
};

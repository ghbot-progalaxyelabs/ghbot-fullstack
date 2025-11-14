# Authentication Testing Guide

## Overview

This guide explains how to test authentication in the API without needing access to Google APIs or other external authentication providers. The testing framework uses mock JWT tokens with test RSA keys.

## Architecture

### Components

1. **MyTokens** (`src/App/Lib/MyTokens.php`)
   - Validates JWT tokens using RSA public key
   - Extracts claims from Authorization headers
   - Creates new JWT tokens using RSA private key

2. **MyTokenClaims** (`src/App/Models/MyTokenClaims.php`)
   - Model for JWT token claims
   - Properties: `iss`, `iat`, `exp`, `user_id`

3. **AuthTestHelper** (`tests/_fixtures/AuthTestHelper.php`)
   - Utility class for generating test JWT tokens
   - Creates mock authentication headers
   - Provides test key management

4. **Test RSA Keys** (`tests/_fixtures/keys/`)
   - `test_key.pem` - Private key (encrypted with passphrase)
   - `test_key.pub` - Public key
   - Used exclusively for testing, not production

## Testing Strategy

### 1. Unit Tests

#### MyTokenClaims Tests (`tests/Unit/MyTokenClaimsTest.php`)

Tests the token claims model:
- Creating claims from decoded tokens
- Handling missing fields with defaults
- Different user ID formats (string, numeric, UUID)
- Preserving timestamp claims

**Run:** `vendor/bin/phpunit tests/Unit/MyTokenClaimsTest.php`

#### MyTokens Tests (`tests/Unit/MyTokensTest.php`)

Tests JWT token validation:
- Token extraction from Authorization headers
- Valid token decoding
- Expired token handling
- Invalid/malformed token rejection
- Token creation with test keys
- Missing key file handling

**Run:** `vendor/bin/phpunit tests/Unit/MyTokensTest.php`

### 2. Integration Tests

#### AuthenticatedRoutes Tests (`tests/Feature/AuthenticatedRoutesTest.php`)

Tests authentication flow with routes:
- End-to-end token validation
- Rejecting invalid/expired tokens
- Multiple user distinction
- Bearer token format handling

**Run:** `vendor/bin/phpunit tests/Feature/AuthenticatedRoutesTest.php`

## Using AuthTestHelper

### Generating Test Tokens

```php
use Tests\Fixtures\AuthTestHelper;

// Generate a valid token for a user
$token = AuthTestHelper::generateToken('user-123');

// Generate a token with custom expiration
$token = AuthTestHelper::generateToken('user-123', 60); // 60 minutes

// Generate an expired token
$expiredToken = AuthTestHelper::generateExpiredToken('user-123');

// Generate token with custom payload
$customToken = AuthTestHelper::generateCustomToken([
    'iss' => 'custom-issuer',
    'iat' => time(),
    'exp' => time() + 3600,
    'user_id' => 'custom-user',
    'custom_claim' => 'value'
]);
```

### Creating Mock Requests

```php
use Tests\Fixtures\AuthTestHelper;

// Create authenticated request
$request = AuthTestHelper::createAuthenticatedRequest();

// Create authenticated request with specific token
$token = AuthTestHelper::generateToken('specific-user');
$request = AuthTestHelper::createAuthenticatedRequest($token);

// Create unauthenticated request
$request = AuthTestHelper::createUnauthenticatedRequest();
```

### Creating Authorization Headers

```php
use Tests\Fixtures\AuthTestHelper;

// Create Bearer header
$authHeader = AuthTestHelper::createAuthHeader();
// Returns: "Bearer eyJhbGc..."

// Create header with specific token
$token = AuthTestHelper::generateToken('user-123');
$authHeader = AuthTestHelper::createAuthHeader($token);

// Use in tests
$_SERVER['HTTP_AUTHORIZATION'] = $authHeader;
```

## Testing Authenticated Routes

### Pattern 1: Testing Token Validation

```php
public function test_validates_user_authentication(): void
{
    $userId = 'test-user-123';
    $token = AuthTestHelper::generateToken($userId);
    $request = AuthTestHelper::createAuthenticatedRequest($token);

    // Setup test environment
    $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
    file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

    $myTokens = new MyTokens();
    $claims = $myTokens->claimsFromAuthorizationHeader($request);

    $this->assertNotNull($claims);
    $this->assertEquals($userId, $claims->user_id);

    // Cleanup
    unlink($publicKeyPath);
}
```

### Pattern 2: Testing Route with Authentication

```php
public function test_authenticated_route_access(): void
{
    $userId = 'route-test-user';
    $token = AuthTestHelper::generateToken($userId);

    // Setup test keys
    $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
    file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

    // Set up request environment
    $_SERVER['HTTP_AUTHORIZATION'] = AuthTestHelper::createAuthHeader($token);

    // Extract userId from token (simulating middleware)
    $myTokens = new MyTokens();
    $claims = $myTokens->claimsFromAuthorizationHeader(
        new MockRequest(['HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION']])
    );

    // Test the route
    $route = new GetWebsiteRoute();
    $route->id = 'website-uuid';
    $route->userId = $claims->user_id; // Populated from authentication

    $response = $route->process();

    $this->assertInstanceOf(ApiResponse::class, $response);

    // Cleanup
    unlink($publicKeyPath);
}
```

### Pattern 3: Testing Authorization (Access Control)

```php
public function test_user_can_only_access_own_resources(): void
{
    $owner = 'resource-owner';
    $other = 'other-user';

    // Create resource as owner
    $ownerToken = AuthTestHelper::generateToken($owner);
    // ... create resource ...

    // Try to access as different user
    $otherToken = AuthTestHelper::generateToken($other);
    $_SERVER['HTTP_AUTHORIZATION'] = AuthTestHelper::createAuthHeader($otherToken);

    // Test access is denied
    // ... assert 403 or filtered results ...
}
```

## Test Key Management

### Test Keys Location

- **Private Key:** `tests/_fixtures/keys/test_key.pem`
- **Public Key:** `tests/_fixtures/keys/test_key.pub`
- **Passphrase:** `12345678`

### Regenerating Test Keys

If you need to regenerate the test keys:

```bash
cd api/tests/_fixtures/keys

# Generate new private key
openssl genrsa -aes256 -passout pass:12345678 -out test_key.pem 2048

# Extract public key
openssl rsa -in test_key.pem -passin pass:12345678 -pubout -out test_key.pub
```

**Important:** Never use test keys in production!

## Common Testing Scenarios

### Valid Authentication

```php
$token = AuthTestHelper::generateToken('valid-user');
$request = AuthTestHelper::createAuthenticatedRequest($token);
// Token should validate successfully
```

### Expired Token

```php
$token = AuthTestHelper::generateExpiredToken('expired-user');
$request = AuthTestHelper::createAuthenticatedRequest($token);
// Token validation should fail
```

### Missing Authentication

```php
$request = AuthTestHelper::createUnauthenticatedRequest();
// Should handle gracefully (401 or null userId)
```

### Invalid Token

```php
$request = AuthTestHelper::createAuthenticatedRequest('invalid.token.string');
// Token validation should fail
```

### Multiple Users

```php
$user1Token = AuthTestHelper::generateToken('user-1');
$user2Token = AuthTestHelper::generateToken('user-2');
// Each token should identify different users
```

## Running Tests

### Run All Authentication Tests

```bash
# All authentication-related tests
vendor/bin/phpunit --filter "MyToken|Authenticated"

# Just unit tests
vendor/bin/phpunit tests/Unit/MyTokensTest.php
vendor/bin/phpunit tests/Unit/MyTokenClaimsTest.php

# Just integration tests
vendor/bin/phpunit tests/Feature/AuthenticatedRoutesTest.php
```

### Run With Verbose Output

```bash
vendor/bin/phpunit --testdox tests/Unit/MyTokensTest.php
```

### Run Specific Test

```bash
vendor/bin/phpunit --filter test_validates_authentication_token
```

## Troubleshooting

### "Could not read test private key"

**Cause:** Test key files don't exist or aren't readable.

**Solution:**
```bash
cd api/tests/_fixtures/keys
openssl genrsa -aes256 -passout pass:testpass123 -out test_key.pem 2048
openssl rsa -in test_key.pem -passin pass:testpass123 -pubout -out test_key.pub
```

### "Token validation always fails"

**Cause:** Test public key not found by MyTokens class.

**Solution:** Ensure test sets up the public key file:
```php
$publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());
```

### Tests Pass But Production Fails

**Cause:** Test keys differ from production keys.

**Solution:** This is expected! Test keys are only for testing. Production uses different keys (specified in ROOT_PATH).

## Best Practices

### 1. Always Clean Up Test Keys

```php
protected function tearDown(): void
{
    $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
    if (file_exists($publicKeyPath)) {
        unlink($publicKeyPath);
    }
    parent::tearDown();
}
```

### 2. Use Specific User IDs

```php
// Good - Clear test purpose
$userId = 'integration-test-user';

// Bad - Unclear
$userId = 'test';
```

### 3. Test Both Success and Failure Cases

```php
public function test_valid_authentication(): void { /* ... */ }
public function test_invalid_authentication(): void { /* ... */ }
public function test_expired_authentication(): void { /* ... */ }
public function test_missing_authentication(): void { /* ... */ }
```

### 4. Don't Mix Test and Production Keys

- Test keys: `tests/_fixtures/keys/test_key.*`
- Production keys: Root level (`key.pem`, `instituteappapikey.pub`)
- Never commit production keys to repository

### 5. Use Descriptive Test Names

```php
// Good
public function test_rejects_expired_authentication_token(): void

// Bad
public function test_token(): void
```

## Security Considerations

### Test Keys vs Production Keys

- **Test keys** are in the repository for testing
- **Production keys** must never be committed
- Test keys use a known passphrase (`testpass123`)
- Production keys should use secure, secret passphrases

### Token Expiration

- Test tokens default to 15-minute expiration
- Production tokens should follow security best practices
- Always test expired token handling

### Issuer Validation

- Current implementation uses `instituteapp.in` as issuer
- Consider validating issuer claim in production
- Test both valid and invalid issuers

## Extending the Test Framework

### Adding Custom Claims

```php
// In AuthTestHelper
public static function generateTokenWithRole(
    string $userId,
    string $role
): string {
    $privateKey = self::getPrivateKey();
    $now = new \DateTimeImmutable();

    $payload = [
        'iss' => 'instituteapp.in',
        'iat' => $now->getTimestamp(),
        'exp' => $now->modify('+15 minutes')->getTimestamp(),
        'user_id' => $userId,
        'role' => $role // Custom claim
    ];

    return JWT::encode($payload, $privateKey, 'RS256');
}
```

### Testing Different Token Algorithms

```php
// Generate HS256 token for testing
public static function generateHS256Token(string $userId): string
{
    $secret = 'test-secret';
    $payload = [/* ... */];
    return JWT::encode($payload, $secret, 'HS256');
}
```

## Summary

The authentication testing framework provides:

✅ **No external dependencies** - Works without Google APIs
✅ **Fast tests** - All validation happens locally
✅ **Realistic scenarios** - Uses actual JWT validation
✅ **Easy to use** - Simple helper methods
✅ **Comprehensive** - Covers success and failure cases
✅ **Secure** - Separate test and production keys

Start testing authentication in your routes today using the patterns and examples in this guide!

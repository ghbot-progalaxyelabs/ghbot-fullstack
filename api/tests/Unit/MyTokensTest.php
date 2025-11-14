<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Lib\MyTokens;
use App\Models\MyTokenClaims;
use Tests\Fixtures\AuthTestHelper;
use Tests\Fixtures\MockRequest;
use Firebase\JWT\JWT;

/**
 * MyTokens Authentication Tests
 *
 * Tests JWT token validation and authentication
 * Uses test keys instead of production keys or Google APIs
 */
class MyTokensTest extends TestCase
{
    private MyTokens $tokens;
    private string $originalRootPath;
    private string $testPublicKeyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = new MyTokens();

        // Store original ROOT_PATH and set up test environment
        if (defined('ROOT_PATH')) {
            $this->originalRootPath = ROOT_PATH;
        }

        // Copy test public key to expected location for testing
        $this->testPublicKeyPath = __DIR__ . '/../_fixtures/keys/test_key.pub';
        $targetPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';

        if (file_exists($this->testPublicKeyPath)) {
            // Create a symlink or copy the test public key
            if (!file_exists($targetPath)) {
                copy($this->testPublicKeyPath, $targetPath);
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up test public key if it was created
        $targetPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        if (file_exists($targetPath) && is_file($targetPath)) {
            // Only delete if it's the test key (check file size or content)
            $testKeyContent = file_get_contents($this->testPublicKeyPath);
            $targetKeyContent = file_get_contents($targetPath);
            if ($testKeyContent === $targetKeyContent) {
                unlink($targetPath);
            }
        }

        parent::tearDown();
    }

    /**
     * Test extracting token from valid Authorization header
     */
    public function test_extracts_token_from_authorization_header(): void
    {
        $token = 'test.jwt.token';
        $request = new MockRequest(['HTTP_AUTHORIZATION' => "Bearer {$token}"]);

        $reflection = new \ReflectionClass($this->tokens);
        $method = $reflection->getMethod('tokenFromAuthorizationHeader');
        $method->setAccessible(true);

        $extractedToken = $method->invoke($this->tokens, $request);

        $this->assertEquals($token, $extractedToken);
    }

    /**
     * Test handling missing Authorization header
     */
    public function test_returns_empty_string_for_missing_authorization_header(): void
    {
        $request = new MockRequest([]);

        $reflection = new \ReflectionClass($this->tokens);
        $method = $reflection->getMethod('tokenFromAuthorizationHeader');
        $method->setAccessible(true);

        $extractedToken = $method->invoke($this->tokens, $request);

        $this->assertEquals('', $extractedToken);
    }

    /**
     * Test decoding valid JWT token
     */
    public function test_decodes_valid_jwt_token(): void
    {
        $userId = 'test-user-456';
        $token = AuthTestHelper::generateToken($userId);

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $claims = $this->tokens->decodeToken($token);

        $this->assertInstanceOf(MyTokenClaims::class, $claims);
        $this->assertEquals($userId, $claims->user_id);
        $this->assertEquals('instituteapp.in', $claims->iss);
        $this->assertGreaterThan(0, $claims->iat);
        $this->assertGreaterThan(0, $claims->exp);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test decoding expired token without allowing expiry
     */
    public function test_returns_null_for_expired_token(): void
    {
        $token = AuthTestHelper::generateExpiredToken('test-user-789');

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $claims = $this->tokens->decodeToken($token, false);

        $this->assertNull($claims);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test decoding expired token with allowing expiry
     */
    public function test_decodes_expired_token_when_allowed(): void
    {
        $userId = 'test-user-expired';
        $token = AuthTestHelper::generateExpiredToken($userId);

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $claims = $this->tokens->decodeToken($token, true);

        $this->assertInstanceOf(MyTokenClaims::class, $claims);
        $this->assertEquals($userId, $claims->user_id);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test decoding invalid token
     */
    public function test_returns_null_for_invalid_token(): void
    {
        $invalidToken = 'invalid.jwt.token';

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $claims = $this->tokens->decodeToken($invalidToken);

        $this->assertNull($claims);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test decoding token with malformed structure
     */
    public function test_returns_null_for_malformed_token(): void
    {
        $malformedToken = 'not-a-valid-jwt';

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $claims = $this->tokens->decodeToken($malformedToken);

        $this->assertNull($claims);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test extracting claims from Authorization header (full flow)
     */
    public function test_extracts_claims_from_authorization_header(): void
    {
        $userId = 'full-flow-user';
        $request = AuthTestHelper::createAuthenticatedRequest(
            AuthTestHelper::generateToken($userId)
        );

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $claims = $this->tokens->claimsFromAuthorizationHeader($request);

        $this->assertInstanceOf(MyTokenClaims::class, $claims);
        $this->assertEquals($userId, $claims->user_id);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test handling request without Authorization header
     */
    public function test_returns_null_for_missing_authorization_header_in_claims(): void
    {
        $request = AuthTestHelper::createUnauthenticatedRequest();

        $claims = $this->tokens->claimsFromAuthorizationHeader($request);

        $this->assertNull($claims);
    }

    /**
     * Test handling missing public key file
     */
    public function test_returns_null_when_public_key_file_missing(): void
    {
        $token = AuthTestHelper::generateToken();

        // Ensure public key doesn't exist
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        if (file_exists($publicKeyPath)) {
            $backup = $publicKeyPath . '.backup';
            rename($publicKeyPath, $backup);
        }

        $claims = $this->tokens->decodeToken($token);

        $this->assertNull($claims);

        // Restore if backed up
        if (isset($backup) && file_exists($backup)) {
            rename($backup, $publicKeyPath);
        }
    }

    /**
     * Test token creation with valid user ID
     */
    public function test_creates_access_and_refresh_tokens(): void
    {
        $userId = 'token-creation-test';

        // Create temporary test private key file
        $privateKeyPath = realpath(__DIR__ . '/../../') . '/key.pem';
        $testPrivateKeyPath = __DIR__ . '/../_fixtures/keys/test_key.pem';

        if (file_exists($testPrivateKeyPath)) {
            copy($testPrivateKeyPath, $privateKeyPath);

            $tokens = MyTokens::create_tokens($userId, true);

            $this->assertIsArray($tokens);
            $this->assertCount(2, $tokens);
            $this->assertNotEmpty($tokens[0]); // Access token
            $this->assertNotEmpty($tokens[1]); // Refresh token

            // Verify tokens are valid JWTs (have 3 parts)
            $this->assertCount(3, explode('.', $tokens[0]));
            $this->assertCount(3, explode('.', $tokens[1]));

            // Cleanup
            unlink($privateKeyPath);
        } else {
            $this->markTestSkipped('Test private key not available');
        }
    }

    /**
     * Test token creation without refresh token
     */
    public function test_creates_access_token_without_refresh(): void
    {
        $userId = 'no-refresh-test';

        // Create temporary test private key file
        $privateKeyPath = realpath(__DIR__ . '/../../') . '/key.pem';
        $testPrivateKeyPath = __DIR__ . '/../_fixtures/keys/test_key.pem';

        if (file_exists($testPrivateKeyPath)) {
            copy($testPrivateKeyPath, $privateKeyPath);

            $tokens = MyTokens::create_tokens($userId, false);

            $this->assertIsArray($tokens);
            $this->assertCount(2, $tokens);
            $this->assertNotEmpty($tokens[0]); // Access token
            $this->assertEmpty($tokens[1]); // No refresh token

            // Cleanup
            unlink($privateKeyPath);
        } else {
            $this->markTestSkipped('Test private key not available');
        }
    }

    /**
     * Test token creation returns null when private key missing
     */
    public function test_returns_null_when_private_key_missing(): void
    {
        $privateKeyPath = realpath(__DIR__ . '/../../') . '/key.pem';

        // Ensure private key doesn't exist
        if (file_exists($privateKeyPath)) {
            $backup = $privateKeyPath . '.backup';
            rename($privateKeyPath, $backup);
        }

        $tokens = MyTokens::create_tokens('test-user');

        $this->assertNull($tokens);

        // Restore if backed up
        if (isset($backup) && file_exists($backup)) {
            rename($backup, $privateKeyPath);
        }
    }
}

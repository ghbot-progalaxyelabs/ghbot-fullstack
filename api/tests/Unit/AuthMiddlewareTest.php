<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\Middleware\AuthMiddleware;
use Tests\Fixtures\WebmeteorAuthHelper;
use Framework\Env;

/**
 * AuthMiddleware Tests
 *
 * Tests JWT authentication middleware for HS256 tokens
 */
class AuthMiddlewareTest extends TestCase
{
    private $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock environment with test JWT secret
        $env = Env::get_instance();
        $this->originalEnv = $env->JWT_SECRET ?? null;
        $env->JWT_SECRET = WebmeteorAuthHelper::getTestJwtSecret();
    }

    protected function tearDown(): void
    {
        // Restore original environment
        $env = Env::get_instance();
        if ($this->originalEnv !== null) {
            $env->JWT_SECRET = $this->originalEnv;
        }

        parent::tearDown();
    }

    /**
     * Test successful authentication with valid token
     */
    public function test_authenticates_valid_token(): void
    {
        $userId = 'user-123';
        $token = WebmeteorAuthHelper::generateToken($userId, 'user@example.com');
        $headers = WebmeteorAuthHelper::createAuthHeaders($token);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertEquals($userId, $result);
    }

    /**
     * Test authentication with case-insensitive Authorization header
     */
    public function test_handles_case_insensitive_authorization_header(): void
    {
        $userId = 'user-456';
        $token = WebmeteorAuthHelper::generateToken($userId);

        // Test lowercase
        $headers = ['authorization' => "Bearer {$token}"];
        $result = AuthMiddleware::authenticate($headers);
        $this->assertEquals($userId, $result);

        // Test uppercase
        $headers = ['AUTHORIZATION' => "Bearer {$token}"];
        $result = AuthMiddleware::authenticate($headers);
        $this->assertEquals($userId, $result);

        // Test mixed case
        $headers = ['AuThOrIzAtIoN' => "Bearer {$token}"];
        $result = AuthMiddleware::authenticate($headers);
        $this->assertEquals($userId, $result);
    }

    /**
     * Test authentication returns null for missing Authorization header
     */
    public function test_returns_null_for_missing_authorization_header(): void
    {
        $headers = [];

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for malformed Authorization header
     */
    public function test_returns_null_for_malformed_authorization_header(): void
    {
        $headers = ['Authorization' => 'InvalidFormat token123'];

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for missing Bearer prefix
     */
    public function test_returns_null_for_missing_bearer_prefix(): void
    {
        $token = WebmeteorAuthHelper::generateToken();
        $headers = ['Authorization' => $token]; // No "Bearer " prefix

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for expired token
     */
    public function test_returns_null_for_expired_token(): void
    {
        $expiredToken = WebmeteorAuthHelper::generateExpiredToken('user-789');
        $headers = WebmeteorAuthHelper::createAuthHeaders($expiredToken);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for invalid issuer
     */
    public function test_returns_null_for_invalid_issuer(): void
    {
        $invalidToken = WebmeteorAuthHelper::generateTokenWithInvalidIssuer('user-bad');
        $headers = WebmeteorAuthHelper::createAuthHeaders($invalidToken);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for invalid audience
     */
    public function test_returns_null_for_invalid_audience(): void
    {
        $invalidToken = WebmeteorAuthHelper::generateTokenWithInvalidAudience('user-bad');
        $headers = WebmeteorAuthHelper::createAuthHeaders($invalidToken);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for token without sub claim
     */
    public function test_returns_null_for_token_without_sub_claim(): void
    {
        $invalidToken = WebmeteorAuthHelper::generateTokenWithoutSub();
        $headers = WebmeteorAuthHelper::createAuthHeaders($invalidToken);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication returns null for completely invalid token
     */
    public function test_returns_null_for_invalid_token(): void
    {
        $headers = ['Authorization' => 'Bearer invalid.jwt.token'];

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test authentication with malformed JWT (not 3 parts)
     */
    public function test_returns_null_for_malformed_jwt(): void
    {
        $headers = ['Authorization' => 'Bearer malformed-token'];

        $result = AuthMiddleware::authenticate($headers);

        $this->assertNull($result);
    }

    /**
     * Test getOptionalUserId returns userId for valid token
     */
    public function test_get_optional_user_id_returns_user_id_for_valid_token(): void
    {
        $userId = 'optional-user-123';
        $token = WebmeteorAuthHelper::generateToken($userId);
        $headers = WebmeteorAuthHelper::createAuthHeaders($token);

        $result = AuthMiddleware::getOptionalUserId($headers);

        $this->assertEquals($userId, $result);
    }

    /**
     * Test getOptionalUserId returns null for invalid token
     */
    public function test_get_optional_user_id_returns_null_for_invalid_token(): void
    {
        $headers = ['Authorization' => 'Bearer invalid.token'];

        $result = AuthMiddleware::getOptionalUserId($headers);

        $this->assertNull($result);
    }

    /**
     * Test getOptionalUserId returns null for missing header
     */
    public function test_get_optional_user_id_returns_null_for_missing_header(): void
    {
        $headers = [];

        $result = AuthMiddleware::getOptionalUserId($headers);

        $this->assertNull($result);
    }

    /**
     * Test requireAuth returns user ID for valid token
     *
     * Note: requireAuth exits on failure, so we can only test success case
     */
    public function test_require_auth_returns_user_id_for_valid_token(): void
    {
        $userId = 'required-user-456';
        $token = WebmeteorAuthHelper::generateToken($userId);
        $headers = WebmeteorAuthHelper::createAuthHeaders($token);

        $result = AuthMiddleware::requireAuth($headers);

        $this->assertEquals($userId, $result);
    }

    /**
     * Test authentication with different user IDs
     */
    public function test_distinguishes_between_different_users(): void
    {
        $user1Id = 'user-one';
        $user2Id = 'user-two';

        $token1 = WebmeteorAuthHelper::generateToken($user1Id, 'user1@example.com');
        $token2 = WebmeteorAuthHelper::generateToken($user2Id, 'user2@example.com');

        $headers1 = WebmeteorAuthHelper::createAuthHeaders($token1);
        $headers2 = WebmeteorAuthHelper::createAuthHeaders($token2);

        $result1 = AuthMiddleware::authenticate($headers1);
        $result2 = AuthMiddleware::authenticate($headers2);

        $this->assertEquals($user1Id, $result1);
        $this->assertEquals($user2Id, $result2);
        $this->assertNotEquals($result1, $result2);
    }

    /**
     * Test authentication with UUID user IDs
     */
    public function test_handles_uuid_user_ids(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $token = WebmeteorAuthHelper::generateToken($uuid);
        $headers = WebmeteorAuthHelper::createAuthHeaders($token);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertEquals($uuid, $result);
    }

    /**
     * Test authentication with numeric user IDs
     */
    public function test_handles_numeric_user_ids(): void
    {
        $numericId = '12345';
        $token = WebmeteorAuthHelper::generateToken($numericId);
        $headers = WebmeteorAuthHelper::createAuthHeaders($token);

        $result = AuthMiddleware::authenticate($headers);

        $this->assertEquals($numericId, $result);
    }

    /**
     * Test token with Bearer prefix variations (spaces, case)
     */
    public function test_handles_bearer_prefix_with_multiple_spaces(): void
    {
        $userId = 'space-test-user';
        $token = WebmeteorAuthHelper::generateToken($userId);

        // Multiple spaces after Bearer
        $headers = ['Authorization' => "Bearer    {$token}"];
        $result = AuthMiddleware::authenticate($headers);

        // Should still extract the token correctly
        $this->assertNotNull($result);
    }

    /**
     * Test bearer prefix case insensitivity
     */
    public function test_handles_bearer_case_variations(): void
    {
        $userId = 'bearer-case-user';
        $token = WebmeteorAuthHelper::generateToken($userId);

        // Lowercase bearer
        $headers = ['Authorization' => "bearer {$token}"];
        $result = AuthMiddleware::authenticate($headers);
        $this->assertEquals($userId, $result);

        // Uppercase BEARER
        $headers = ['Authorization' => "BEARER {$token}"];
        $result = AuthMiddleware::authenticate($headers);
        $this->assertEquals($userId, $result);

        // Mixed case BeArEr
        $headers = ['Authorization' => "BeArEr {$token}"];
        $result = AuthMiddleware::authenticate($headers);
        $this->assertEquals($userId, $result);
    }
}

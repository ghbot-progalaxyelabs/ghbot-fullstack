<?php

namespace Tests\Fixtures;

use Firebase\JWT\JWT;

/**
 * Auth Test Helper for HS256 JWT tokens
 *
 * Provides utilities for testing the webmeteor authentication system
 * which uses HS256 JWT tokens (different from MyTokens which uses RS256)
 *
 * Features:
 * - Generate valid HS256 JWT tokens
 * - Generate expired tokens
 * - Generate tokens with invalid issuer/audience
 * - Create mock headers for testing
 */
class WebmeteorAuthHelper
{
    private const TEST_JWT_SECRET = 'test-secret-key-for-testing-only';
    private const ISSUER = 'webmeteor';
    private const AUDIENCE = 'webmeteor-app';

    /**
     * Generate a valid JWT token
     *
     * @param string $userId User ID
     * @param string $email User email
     * @param int $expiresInDays Days until expiration (default: 7)
     * @return string JWT token
     */
    public static function generateToken(
        string $userId = 'test-user-123',
        string $email = 'test@example.com',
        int $expiresInDays = 7
    ): string {
        $issuedAt = time();
        $expiresAt = $issuedAt + ($expiresInDays * 24 * 60 * 60);

        $payload = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'sub' => $userId,
            'email' => $email,
        ];

        return JWT::encode($payload, self::TEST_JWT_SECRET, 'HS256');
    }

    /**
     * Generate an expired JWT token
     *
     * @param string $userId User ID
     * @param string $email User email
     * @param int $expiredDaysAgo How many days ago it expired
     * @return string JWT token
     */
    public static function generateExpiredToken(
        string $userId = 'test-user-123',
        string $email = 'test@example.com',
        int $expiredDaysAgo = 1
    ): string {
        $now = time();
        $issuedAt = $now - (30 * 24 * 60 * 60); // 30 days ago
        $expiresAt = $now - ($expiredDaysAgo * 24 * 60 * 60);

        $payload = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'sub' => $userId,
            'email' => $email,
        ];

        return JWT::encode($payload, self::TEST_JWT_SECRET, 'HS256');
    }

    /**
     * Generate token with invalid issuer
     *
     * @param string $userId User ID
     * @return string JWT token
     */
    public static function generateTokenWithInvalidIssuer(string $userId = 'test-user'): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + (7 * 24 * 60 * 60);

        $payload = [
            'iss' => 'invalid-issuer',
            'aud' => self::AUDIENCE,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'sub' => $userId,
            'email' => 'test@example.com',
        ];

        return JWT::encode($payload, self::TEST_JWT_SECRET, 'HS256');
    }

    /**
     * Generate token with invalid audience
     *
     * @param string $userId User ID
     * @return string JWT token
     */
    public static function generateTokenWithInvalidAudience(string $userId = 'test-user'): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + (7 * 24 * 60 * 60);

        $payload = [
            'iss' => self::ISSUER,
            'aud' => 'invalid-audience',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'sub' => $userId,
            'email' => 'test@example.com',
        ];

        return JWT::encode($payload, self::TEST_JWT_SECRET, 'HS256');
    }

    /**
     * Generate token without sub claim (missing user ID)
     *
     * @return string JWT token
     */
    public static function generateTokenWithoutSub(): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + (7 * 24 * 60 * 60);

        $payload = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'email' => 'test@example.com',
        ];

        return JWT::encode($payload, self::TEST_JWT_SECRET, 'HS256');
    }

    /**
     * Generate token with custom payload
     *
     * @param array $payload Custom payload
     * @return string JWT token
     */
    public static function generateCustomToken(array $payload): string
    {
        return JWT::encode($payload, self::TEST_JWT_SECRET, 'HS256');
    }

    /**
     * Create Authorization header
     *
     * @param string|null $token JWT token (null to generate new one)
     * @return string Authorization header value
     */
    public static function createAuthHeader(?string $token = null): string
    {
        $token = $token ?? self::generateToken();
        return "Bearer {$token}";
    }

    /**
     * Create headers array with authorization
     *
     * @param string|null $token JWT token (null to generate new one)
     * @return array Headers array
     */
    public static function createAuthHeaders(?string $token = null): array
    {
        return [
            'Authorization' => self::createAuthHeader($token)
        ];
    }

    /**
     * Create headers array without authorization
     *
     * @return array Empty headers array
     */
    public static function createUnauthenticatedHeaders(): array
    {
        return [];
    }

    /**
     * Get the test JWT secret
     *
     * @return string Test JWT secret
     */
    public static function getTestJwtSecret(): string
    {
        return self::TEST_JWT_SECRET;
    }

    /**
     * Mock Google JWT token payload
     *
     * @param string $googleId Google user ID
     * @param string $email User email
     * @param string $name User name
     * @param string $picture Avatar URL
     * @return object Mock Google token payload
     */
    public static function createMockGoogleTokenPayload(
        string $googleId = '123456789',
        string $email = 'test@gmail.com',
        string $name = 'Test User',
        string $picture = 'https://example.com/avatar.jpg'
    ): object {
        return (object)[
            'iss' => 'https://accounts.google.com',
            'sub' => $googleId,
            'email' => $email,
            'name' => $name,
            'picture' => $picture,
            'aud' => 'test-google-client-id.apps.googleusercontent.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];
    }
}

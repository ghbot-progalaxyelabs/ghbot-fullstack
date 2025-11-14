<?php

namespace Tests\Fixtures;

use Firebase\JWT\JWT;

/**
 * Authentication Test Helper
 *
 * Provides utilities for testing authentication without needing access
 * to Google APIs or production authentication systems.
 *
 * Features:
 * - Generate mock JWT tokens
 * - Create test authentication headers
 * - Mock request objects with authentication
 * - Generate expired tokens for testing
 */
class AuthTestHelper
{
    private const TEST_PRIVATE_KEY_PATH = __DIR__ . '/keys/test_key.pem';
    private const TEST_PUBLIC_KEY_PATH = __DIR__ . '/keys/test_key.pub';
    private const TEST_PASSPHRASE = '12345678';

    /**
     * Generate a valid test JWT token
     *
     * @param string $userId User ID to include in token
     * @param int $expiresInMinutes Minutes until token expires (default: 15)
     * @return string JWT token
     */
    public static function generateToken(
        string $userId = 'test-user-123',
        int $expiresInMinutes = 15
    ): string {
        $privateKey = self::getPrivateKey();

        $now = new \DateTimeImmutable();
        $issuedAt = $now;
        $expiresAt = $issuedAt->modify("+{$expiresInMinutes} minutes");

        $payload = [
            'iss' => 'instituteapp.in',
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'user_id' => $userId
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /**
     * Generate an expired test JWT token
     *
     * @param string $userId User ID to include in token
     * @param int $expiredMinutesAgo How many minutes ago the token expired
     * @return string JWT token
     */
    public static function generateExpiredToken(
        string $userId = 'test-user-123',
        int $expiredMinutesAgo = 5
    ): string {
        $privateKey = self::getPrivateKey();

        $now = new \DateTimeImmutable();
        $issuedAt = $now->modify('-1 hour');
        $expiresAt = $now->modify("-{$expiredMinutesAgo} minutes");

        $payload = [
            'iss' => 'instituteapp.in',
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'user_id' => $userId
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /**
     * Generate a token with custom payload
     *
     * @param array $customPayload Custom payload data
     * @return string JWT token
     */
    public static function generateCustomToken(array $customPayload): string
    {
        $privateKey = self::getPrivateKey();
        return JWT::encode($customPayload, $privateKey, 'RS256');
    }

    /**
     * Create an Authorization header value
     *
     * @param string|null $token JWT token (null to generate a new one)
     * @return string Authorization header value (e.g., "Bearer eyJ...")
     */
    public static function createAuthHeader(?string $token = null): string
    {
        $token = $token ?? self::generateToken();
        return "Bearer {$token}";
    }

    /**
     * Create a mock request object with authentication
     *
     * @param string|null $token JWT token (null to generate a new one)
     * @return MockRequest
     */
    public static function createAuthenticatedRequest(?string $token = null): MockRequest
    {
        $authHeader = self::createAuthHeader($token);
        return new MockRequest(['HTTP_AUTHORIZATION' => $authHeader]);
    }

    /**
     * Create a mock request object without authentication
     *
     * @return MockRequest
     */
    public static function createUnauthenticatedRequest(): MockRequest
    {
        return new MockRequest([]);
    }

    /**
     * Get the test private key
     *
     * @return resource OpenSSL key resource
     */
    private static function getPrivateKey()
    {
        $pemContent = file_get_contents(self::TEST_PRIVATE_KEY_PATH);
        if (!$pemContent) {
            throw new \RuntimeException('Could not read test private key');
        }

        $privateKey = openssl_pkey_get_private($pemContent, self::TEST_PASSPHRASE);
        if (!$privateKey) {
            throw new \RuntimeException('Could not load test private key');
        }

        return $privateKey;
    }

    /**
     * Get the test public key content
     *
     * @return string Public key content
     */
    public static function getPublicKey(): string
    {
        $publicKey = file_get_contents(self::TEST_PUBLIC_KEY_PATH);
        if (!$publicKey) {
            throw new \RuntimeException('Could not read test public key');
        }

        return $publicKey;
    }

    /**
     * Get the test public key path
     *
     * @return string Path to test public key file
     */
    public static function getPublicKeyPath(): string
    {
        return self::TEST_PUBLIC_KEY_PATH;
    }
}

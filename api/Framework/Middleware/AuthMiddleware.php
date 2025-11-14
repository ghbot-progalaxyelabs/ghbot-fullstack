<?php

namespace Framework\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Framework\Env;
use Exception;

/**
 * JWT Authentication Middleware
 *
 * Issue #3: Create JWT authentication middleware
 *
 * Validates JWT tokens and extracts user information for protected routes.
 * Injects userId into route handlers when authentication is successful.
 */
class AuthMiddleware
{
    /**
     * Validate JWT token and extract user ID
     *
     * @param array $headers Request headers
     * @return string|null User ID if valid, null if invalid/missing
     */
    public static function authenticate(array $headers): ?string
    {
        $env = Env::get_instance();

        // Get Authorization header
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if (!$authHeader) {
            return null;
        }

        // Extract Bearer token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];

        try {
            // Decode and verify JWT
            $decoded = JWT::decode($token, new Key($env->JWT_SECRET, 'HS256'));

            // Verify issuer and audience
            if ($decoded->iss !== 'webmeteor' || $decoded->aud !== 'webmeteor-app') {
                log_error('Invalid JWT issuer or audience');
                return null;
            }

            // Extract user ID from 'sub' claim
            $userId = $decoded->sub ?? null;

            if (!$userId) {
                log_error('JWT missing user ID (sub claim)');
                return null;
            }

            return $userId;

        } catch (Exception $e) {
            log_error('JWT validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Require authentication or return 401
     *
     * @param array $headers Request headers
     * @return string User ID
     * @throws Exception If authentication fails
     */
    public static function requireAuth(array $headers): string
    {
        $userId = self::authenticate($headers);

        if (!$userId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Unauthorized',
                'message' => 'Valid authentication token required'
            ]);
            exit;
        }

        return $userId;
    }

    /**
     * Get user ID from request headers (optional authentication)
     *
     * Returns user ID if authenticated, null otherwise (doesn't throw/exit)
     *
     * @param array $headers Request headers
     * @return string|null User ID or null
     */
    public static function getOptionalUserId(array $headers): ?string
    {
        return self::authenticate($headers);
    }
}

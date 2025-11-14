<?php

namespace App\Routes;

use App\DTO\GoogleSigninResponse;
use App\DTO\UserData;
use Firebase\JWT\JWT;
use Framework\Database;
use Framework\Env;
use Exception;

/**
 * Mock authentication route for E2E testing
 *
 * This route bypasses Google OAuth and creates test users directly.
 * Only enabled when MOCK_AUTH_ENABLED=true in .env
 */
class MockAuthRoute
{
    /**
     * Handle mock authentication for testing
     *
     * Creates or retrieves a test user without Google OAuth.
     * This endpoint should ONLY be used in testing environments.
     *
     * Expected POST body:
     * {
     *   "email": "test@example.com",
     *   "name": "Test User" (optional)
     * }
     *
     * @return GoogleSigninResponse
     * @throws Exception
     */
    public static function handle(): GoogleSigninResponse
    {
        $env = Env::get_instance();

        // Security check: only allow in testing environment
        if (!$env->MOCK_AUTH_ENABLED) {
            http_response_code(403);
            throw new Exception('Mock authentication is disabled. Set MOCK_AUTH_ENABLED=true in .env for testing.');
        }

        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);

        $email = $input['email'] ?? null;
        $name = $input['name'] ?? 'Test User';

        if (!$email) {
            http_response_code(400);
            throw new Exception('Email is required');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            throw new Exception('Invalid email format');
        }

        $db = Database::get_instance();

        // Generate a test google_id based on email
        $googleId = 'mock-' . md5($email);
        $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($name);

        // Check if user exists
        $existingUser = $db->query_single(
            'SELECT id, email, google_id, name, avatar_url FROM users WHERE email = $1',
            [$email]
        );

        if ($existingUser) {
            // User exists - update if needed
            if ($existingUser->name !== $name) {
                $db->query(
                    'UPDATE users SET name = $1, avatar_url = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3',
                    [$name, $avatarUrl, $existingUser->id]
                );
            }
            $userId = $existingUser->id;
        } else {
            // Create new test user
            $result = $db->query_single(
                'INSERT INTO users (email, google_id, name, avatar_url) VALUES ($1, $2, $3, $4) RETURNING id',
                [$email, $googleId, $name, $avatarUrl]
            );
            $userId = $result->id;
        }

        // Generate JWT token
        $jwtToken = self::generateJWT($userId, $email, $env->JWT_SECRET);

        // Prepare response
        $response = new GoogleSigninResponse();
        $response->token = $jwtToken;

        $userData = new UserData();
        $userData->id = $userId;
        $userData->email = $email;
        $userData->name = $name;
        $userData->avatarUrl = $avatarUrl;

        $response->user = $userData;

        return $response;
    }

    /**
     * Generate application JWT token
     * (Same as AuthRoute::generateJWT)
     *
     * @param string $userId User ID
     * @param string $email User email
     * @param string $secret JWT secret key
     * @return string JWT token
     */
    private static function generateJWT(string $userId, string $email, string $secret): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + (7 * 24 * 60 * 60); // 7 days

        $payload = [
            'iss' => 'webmeteor',           // Issuer
            'aud' => 'webmeteor-app',       // Audience
            'iat' => $issuedAt,             // Issued at
            'exp' => $expiresAt,            // Expires at
            'sub' => $userId,               // Subject (user ID)
            'email' => $email,              // User email
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}

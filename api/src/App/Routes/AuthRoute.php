<?php

namespace App\Routes;

use App\Contracts\IAuthRoute;
use App\DTO\GoogleSigninRequest;
use App\DTO\GoogleSigninResponse;
use App\DTO\UserData;
use Firebase\JWT\JWT;
use Framework\Database;
use Framework\Env;
use Exception;

class AuthRoute implements IAuthRoute
{
    /**
     * Handle Google Sign-in authentication
     *
     * Issues #2: Implement Google Sign-in backend route
     *
     * Process:
     * 1. Verify Google JWT token
     * 2. Extract user info (email, google_id, name, avatar_url)
     * 3. Create user if doesn't exist, or retrieve existing user
     * 4. Generate app JWT token
     * 5. Return JWT and user info
     *
     * @param GoogleSigninRequest $request
     * @return GoogleSigninResponse
     * @throws Exception
     */
    public static function handle(GoogleSigninRequest $request): GoogleSigninResponse
    {
        $env = Env::get_instance();
        $db = Database::get_instance();

        // Verify Google JWT token
        $googleUser = self::verifyGoogleToken($request->googleToken, $env->GOOGLE_CLIENT_ID);

        if (!$googleUser) {
            throw new Exception('Invalid Google token');
        }

        // Extract user info from Google token
        $email = $googleUser->email ?? null;
        $googleId = $googleUser->sub ?? null; // 'sub' is Google's user ID
        $name = $googleUser->name ?? '';
        $avatarUrl = $googleUser->picture ?? '';

        if (!$email || !$googleId) {
            throw new Exception('Missing required user information from Google token');
        }

        // Check if user exists
        $existingUser = $db->query_single(
            'SELECT id, email, google_id, name, avatar_url FROM users WHERE google_id = $1',
            [$googleId]
        );

        if ($existingUser) {
            // User exists - update info if changed
            if ($existingUser->name !== $name || $existingUser->avatar_url !== $avatarUrl) {
                $db->query(
                    'UPDATE users SET name = $1, avatar_url = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3',
                    [$name, $avatarUrl, $existingUser->id]
                );
            }
            $userId = $existingUser->id;
        } else {
            // Create new user
            $result = $db->query_single(
                'INSERT INTO users (email, google_id, name, avatar_url) VALUES ($1, $2, $3, $4) RETURNING id',
                [$email, $googleId, $name, $avatarUrl]
            );
            $userId = $result->id;
        }

        // Generate app JWT token
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
     * Verify Google JWT token
     *
     * @param string $token Google JWT token
     * @param string $clientId Google OAuth Client ID
     * @return object|null Decoded token payload or null if invalid
     */
    private static function verifyGoogleToken(string $token, ?string $clientId): ?object
    {
        try {
            // Google's public keys URL
            $publicKeysUrl = 'https://www.googleapis.com/oauth2/v3/certs';

            // Fetch Google's public keys
            $publicKeys = json_decode(file_get_contents($publicKeysUrl), true);

            if (!$publicKeys || !isset($publicKeys['keys'])) {
                log_error('Failed to fetch Google public keys');
                return null;
            }

            // Decode token header to get key ID
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return null;
            }

            $header = json_decode(base64_decode(strtr($tokenParts[0], '-_', '+/')), true);
            $kid = $header['kid'] ?? null;

            if (!$kid) {
                return null;
            }

            // Find matching public key
            $publicKey = null;
            foreach ($publicKeys['keys'] as $key) {
                if ($key['kid'] === $kid) {
                    // Convert JWK to PEM format
                    $publicKey = self::jwkToPem($key);
                    break;
                }
            }

            if (!$publicKey) {
                log_error('Public key not found for kid: ' . $kid);
                return null;
            }

            // Decode and verify JWT
            $decoded = JWT::decode($token, new \Firebase\JWT\Key($publicKey, 'RS256'));

            // Verify audience (client ID)
            if ($clientId && $decoded->aud !== $clientId) {
                log_error('Token audience mismatch');
                return null;
            }

            // Verify issuer
            if (!in_array($decoded->iss, ['accounts.google.com', 'https://accounts.google.com'])) {
                log_error('Invalid token issuer');
                return null;
            }

            return $decoded;

        } catch (Exception $e) {
            log_error('Google token verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert JWK (JSON Web Key) to PEM format
     *
     * @param array $jwk JSON Web Key
     * @return string PEM formatted public key
     */
    private static function jwkToPem(array $jwk): string
    {
        $n = base64_decode(strtr($jwk['n'], '-_', '+/'));
        $e = base64_decode(strtr($jwk['e'], '-_', '+/'));

        // Build RSA public key
        $modulus = self::encodeLength(strlen($n)) . $n;
        $exponent = self::encodeLength(strlen($e)) . $e;

        $rsaPublicKey = chr(0x30) . self::encodeLength(strlen($modulus . $exponent)) . $modulus . $exponent;

        // Build full public key structure
        $rsaOID = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $rsaPublicKey = chr(0x03) . self::encodeLength(strlen($rsaPublicKey) + 1) . chr(0x00) . $rsaPublicKey;
        $rsaPublicKey = chr(0x30) . self::encodeLength(strlen($rsaOID . $rsaPublicKey)) . $rsaOID . $rsaPublicKey;

        return "-----BEGIN PUBLIC KEY-----\n" .
               chunk_split(base64_encode($rsaPublicKey), 64, "\n") .
               "-----END PUBLIC KEY-----\n";
    }

    /**
     * Encode length for ASN.1 format
     *
     * @param int $length
     * @return string
     */
    private static function encodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $lengthBytes = '';
        while ($length > 0) {
            $lengthBytes = chr($length & 0xFF) . $lengthBytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($lengthBytes)) . $lengthBytes;
    }

    /**
     * Generate application JWT token
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

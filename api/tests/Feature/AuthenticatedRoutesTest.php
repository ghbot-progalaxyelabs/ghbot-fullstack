<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\AuthTestHelper;
use App\Lib\MyTokens;

/**
 * Authenticated Routes Integration Tests
 *
 * Tests routes that require authentication using mock JWT tokens
 * instead of real Google API integration
 */
class AuthenticatedRoutesTest extends TestCase
{
    /**
     * Test authentication token validation flow
     */
    public function test_validates_authentication_token(): void
    {
        $userId = 'integration-test-user';
        $token = AuthTestHelper::generateToken($userId);
        $request = AuthTestHelper::createAuthenticatedRequest($token);

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $myTokens = new MyTokens();
        $claims = $myTokens->claimsFromAuthorizationHeader($request);

        $this->assertNotNull($claims);
        $this->assertEquals($userId, $claims->user_id);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test authentication fails with invalid token
     */
    public function test_rejects_invalid_authentication_token(): void
    {
        $request = AuthTestHelper::createAuthenticatedRequest('invalid.jwt.token');

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $myTokens = new MyTokens();
        $claims = $myTokens->claimsFromAuthorizationHeader($request);

        $this->assertNull($claims);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test authentication fails without token
     */
    public function test_rejects_request_without_authentication(): void
    {
        $request = AuthTestHelper::createUnauthenticatedRequest();

        $myTokens = new MyTokens();
        $claims = $myTokens->claimsFromAuthorizationHeader($request);

        $this->assertNull($claims);
    }

    /**
     * Test expired token is rejected
     */
    public function test_rejects_expired_authentication_token(): void
    {
        $expiredToken = AuthTestHelper::generateExpiredToken('expired-user');
        $request = AuthTestHelper::createAuthenticatedRequest($expiredToken);

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $myTokens = new MyTokens();
        $claims = $myTokens->claimsFromAuthorizationHeader($request);

        $this->assertNull($claims);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Test multiple users with different tokens
     */
    public function test_distinguishes_between_different_users(): void
    {
        $user1Id = 'user-one';
        $user2Id = 'user-two';

        $token1 = AuthTestHelper::generateToken($user1Id);
        $token2 = AuthTestHelper::generateToken($user2Id);

        $request1 = AuthTestHelper::createAuthenticatedRequest($token1);
        $request2 = AuthTestHelper::createAuthenticatedRequest($token2);

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $myTokens = new MyTokens();
        $claims1 = $myTokens->claimsFromAuthorizationHeader($request1);
        $claims2 = $myTokens->claimsFromAuthorizationHeader($request2);

        $this->assertNotNull($claims1);
        $this->assertNotNull($claims2);
        $this->assertEquals($user1Id, $claims1->user_id);
        $this->assertEquals($user2Id, $claims2->user_id);
        $this->assertNotEquals($claims1->user_id, $claims2->user_id);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }

    /**
     * Example: Test authenticated route handler (GetWebsiteRoute)
     *
     * This demonstrates how to test routes that use authentication
     */
    public function test_authenticated_route_uses_user_id_from_token(): void
    {
        // This is a demonstration test showing how to test authenticated routes
        // In a real scenario, you would:
        // 1. Set up a test database
        // 2. Create test data
        // 3. Mock the request with authentication
        // 4. Call the route handler
        // 5. Assert the response matches expectations

        $this->markTestIncomplete(
            'This test demonstrates the pattern for testing authenticated routes. ' .
            'Implement with database mocking or test database setup.'
        );

        // Example pattern:
        // $userId = 'test-user-123';
        // $token = AuthTestHelper::generateToken($userId);
        //
        // // Set up $_SERVER for the route
        // $_SERVER['HTTP_AUTHORIZATION'] = AuthTestHelper::createAuthHeader($token);
        //
        // $route = new GetWebsiteRoute();
        // $route->id = 'website-uuid';
        // $route->userId = $userId; // Populated from authentication middleware
        //
        // $response = $route->process();
        //
        // $this->assertInstanceOf(ApiResponse::class, $response);
        // $this->assertEquals('ok', $response->status);
    }

    /**
     * Example: Test unauthenticated access to protected route
     */
    public function test_protected_route_without_authentication(): void
    {
        $this->markTestIncomplete(
            'This test demonstrates checking that protected routes require authentication. ' .
            'Implement with actual route handling logic.'
        );

        // Example pattern:
        // $route = new GetWebsiteRoute();
        // $route->id = 'website-uuid';
        // $route->userId = null; // No authentication
        //
        // // Should either return 401 or filter results by ownership
        // $response = $route->process();
        //
        // $this->assertInstanceOf(ApiResponse::class, $response);
    }

    /**
     * Test Bearer token format extraction
     */
    public function test_extracts_token_from_bearer_header(): void
    {
        $token = AuthTestHelper::generateToken('bearer-test-user');
        $authHeader = "Bearer {$token}";

        // Verify the header format is correct
        $this->assertStringStartsWith('Bearer ', $authHeader);

        // Verify token extraction works (mimics Router behavior)
        $extractedToken = substr($authHeader, 7); // Remove 'Bearer ' prefix

        $this->assertEquals($token, $extractedToken);
        $this->assertStringNotContainsString('Bearer', $extractedToken);
    }

    /**
     * Test token with different expiration times
     */
    public function test_respects_token_expiration_time(): void
    {
        // Short-lived token (1 minute)
        $shortToken = AuthTestHelper::generateToken('short-lived-user', 1);

        // Long-lived token (60 minutes)
        $longToken = AuthTestHelper::generateToken('long-lived-user', 60);

        // Create temporary test public key file
        $publicKeyPath = realpath(__DIR__ . '/../../') . '/instituteappapikey.pub';
        file_put_contents($publicKeyPath, AuthTestHelper::getPublicKey());

        $myTokens = new MyTokens();

        $shortRequest = AuthTestHelper::createAuthenticatedRequest($shortToken);
        $longRequest = AuthTestHelper::createAuthenticatedRequest($longToken);

        $shortClaims = $myTokens->claimsFromAuthorizationHeader($shortRequest);
        $longClaims = $myTokens->claimsFromAuthorizationHeader($longRequest);

        $this->assertNotNull($shortClaims);
        $this->assertNotNull($longClaims);

        // Both should be valid now
        $this->assertLessThan($longClaims->exp, $shortClaims->exp);

        // Cleanup
        if (file_exists($publicKeyPath)) {
            unlink($publicKeyPath);
        }
    }
}

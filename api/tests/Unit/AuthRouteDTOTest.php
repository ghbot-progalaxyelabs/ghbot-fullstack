<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\DTO\GoogleSigninRequest;
use App\DTO\GoogleSigninResponse;
use App\DTO\UserData;

/**
 * Auth DTOs Tests
 *
 * Tests for authentication Data Transfer Objects
 */
class AuthRouteDTOTest extends TestCase
{
    /**
     * Test GoogleSigninRequest structure
     */
    public function test_google_signin_request_has_google_token_property(): void
    {
        $request = new GoogleSigninRequest();
        $request->googleToken = 'test-google-token';

        $this->assertObjectHasProperty('googleToken', $request);
        $this->assertEquals('test-google-token', $request->googleToken);
    }

    /**
     * Test GoogleSigninResponse structure
     */
    public function test_google_signin_response_has_required_properties(): void
    {
        $response = new GoogleSigninResponse();
        $response->token = 'jwt-token';

        $user = new UserData();
        $user->id = 'user-123';
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        $user->avatarUrl = 'https://example.com/avatar.jpg';

        $response->user = $user;

        $this->assertObjectHasProperty('token', $response);
        $this->assertObjectHasProperty('user', $response);
        $this->assertInstanceOf(UserData::class, $response->user);
        $this->assertEquals('jwt-token', $response->token);
    }

    /**
     * Test UserData structure
     */
    public function test_user_data_has_all_required_properties(): void
    {
        $user = new UserData();
        $user->id = '550e8400-e29b-41d4-a716-446655440000';
        $user->email = 'user@example.com';
        $user->name = 'John Doe';
        $user->avatarUrl = 'https://example.com/photo.jpg';

        $this->assertObjectHasProperty('id', $user);
        $this->assertObjectHasProperty('email', $user);
        $this->assertObjectHasProperty('name', $user);
        $this->assertObjectHasProperty('avatarUrl', $user);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $user->id);
        $this->assertEquals('user@example.com', $user->email);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('https://example.com/photo.jpg', $user->avatarUrl);
    }

    /**
     * Test UserData with empty values
     */
    public function test_user_data_handles_empty_name_and_avatar(): void
    {
        $user = new UserData();
        $user->id = 'user-456';
        $user->email = 'user@test.com';
        $user->name = '';
        $user->avatarUrl = '';

        $this->assertEquals('', $user->name);
        $this->assertEquals('', $user->avatarUrl);
    }

    /**
     * Test GoogleSigninResponse can be serialized
     */
    public function test_google_signin_response_can_be_json_encoded(): void
    {
        $response = new GoogleSigninResponse();
        $response->token = 'test-jwt-token';

        $user = new UserData();
        $user->id = 'user-789';
        $user->email = 'json@example.com';
        $user->name = 'JSON User';
        $user->avatarUrl = 'https://example.com/json.jpg';

        $response->user = $user;

        $json = json_encode($response);
        $this->assertIsString($json);

        $decoded = json_decode($json);
        $this->assertEquals('test-jwt-token', $decoded->token);
        $this->assertEquals('user-789', $decoded->user->id);
        $this->assertEquals('json@example.com', $decoded->user->email);
    }

    /**
     * Test GoogleSigninRequest with real Google token format
     */
    public function test_google_signin_request_with_realistic_token(): void
    {
        $request = new GoogleSigninRequest();
        // Simulated Google JWT token format (3 parts separated by dots)
        $request->googleToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIn0.signature';

        $this->assertStringContainsString('.', $request->googleToken);
        $this->assertCount(3, explode('.', $request->googleToken));
    }

    /**
     * Test UserData with Unicode characters in name
     */
    public function test_user_data_handles_unicode_characters(): void
    {
        $user = new UserData();
        $user->id = 'unicode-user';
        $user->email = 'unicode@example.com';
        $user->name = '李明 🚀';
        $user->avatarUrl = 'https://example.com/李明.jpg';

        $this->assertEquals('李明 🚀', $user->name);
        $this->assertStringContainsString('李明', $user->avatarUrl);
    }

    /**
     * Test UserData with long email
     */
    public function test_user_data_handles_long_email(): void
    {
        $user = new UserData();
        $longEmail = str_repeat('a', 50) . '@' . str_repeat('b', 50) . '.com';
        $user->id = 'long-email-user';
        $user->email = $longEmail;
        $user->name = 'Long Email User';
        $user->avatarUrl = '';

        $this->assertEquals($longEmail, $user->email);
        $this->assertGreaterThan(100, strlen($user->email));
    }
}

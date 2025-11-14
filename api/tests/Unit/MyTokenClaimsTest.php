<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\MyTokenClaims;

/**
 * MyTokenClaims Tests
 *
 * Tests for JWT token claims model
 */
class MyTokenClaimsTest extends TestCase
{
    /**
     * Test creating claims from decoded token with all fields
     */
    public function test_creates_claims_from_decoded_token_with_all_fields(): void
    {
        $payload = (object)[
            'iss' => 'instituteapp.in',
            'iat' => 1700000000,
            'exp' => 1700000900,
            'user_id' => 'test-user-123'
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertInstanceOf(MyTokenClaims::class, $claims);
        $this->assertEquals('instituteapp.in', $claims->iss);
        $this->assertEquals(1700000000, $claims->iat);
        $this->assertEquals(1700000900, $claims->exp);
        $this->assertEquals('test-user-123', $claims->user_id);
    }

    /**
     * Test creating claims from decoded token with missing fields
     */
    public function test_creates_claims_from_decoded_token_with_missing_fields(): void
    {
        $payload = (object)[
            'iss' => 'instituteapp.in',
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertInstanceOf(MyTokenClaims::class, $claims);
        $this->assertEquals('instituteapp.in', $claims->iss);
        $this->assertEquals(0, $claims->iat);
        $this->assertEquals(0, $claims->exp);
        $this->assertEquals('', $claims->user_id);
    }

    /**
     * Test creating claims from empty object
     */
    public function test_creates_claims_from_empty_object(): void
    {
        $payload = (object)[];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertInstanceOf(MyTokenClaims::class, $claims);
        $this->assertEquals('', $claims->iss);
        $this->assertEquals(0, $claims->iat);
        $this->assertEquals(0, $claims->exp);
        $this->assertEquals('', $claims->user_id);
    }

    /**
     * Test claims with numeric user_id
     */
    public function test_handles_numeric_user_id(): void
    {
        $payload = (object)[
            'iss' => 'instituteapp.in',
            'iat' => 1700000000,
            'exp' => 1700000900,
            'user_id' => 12345
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertEquals(12345, $claims->user_id);
    }

    /**
     * Test claims with UUID user_id
     */
    public function test_handles_uuid_user_id(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $payload = (object)[
            'iss' => 'instituteapp.in',
            'iat' => 1700000000,
            'exp' => 1700000900,
            'user_id' => $uuid
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertEquals($uuid, $claims->user_id);
    }

    /**
     * Test issuer claim validation
     */
    public function test_preserves_issuer_claim(): void
    {
        $payload = (object)[
            'iss' => 'custom-issuer.com',
            'iat' => 1700000000,
            'exp' => 1700000900,
            'user_id' => 'user-123'
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertEquals('custom-issuer.com', $claims->iss);
    }

    /**
     * Test issued at timestamp claim
     */
    public function test_preserves_issued_at_timestamp(): void
    {
        $issuedAt = time();
        $payload = (object)[
            'iss' => 'instituteapp.in',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 900,
            'user_id' => 'user-123'
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertEquals($issuedAt, $claims->iat);
    }

    /**
     * Test expiration timestamp claim
     */
    public function test_preserves_expiration_timestamp(): void
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + 900;
        $payload = (object)[
            'iss' => 'instituteapp.in',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'user_id' => 'user-123'
        ];

        $claims = MyTokenClaims::fromDecodedToken($payload);

        $this->assertEquals($expiresAt, $claims->exp);
    }
}

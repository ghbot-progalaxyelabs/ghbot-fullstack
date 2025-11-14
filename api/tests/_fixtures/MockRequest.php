<?php

namespace Tests\Fixtures;

/**
 * Mock Request class for testing
 *
 * Simulates request server variables for authentication testing
 */
class MockRequest
{
    private array $serverVars;

    public function __construct(array $serverVars = [])
    {
        $this->serverVars = $serverVars;
    }

    public function getServer(string $key): ?string
    {
        return $this->serverVars[$key] ?? null;
    }

    public function setServer(string $key, string $value): void
    {
        $this->serverVars[$key] = $value;
    }
}

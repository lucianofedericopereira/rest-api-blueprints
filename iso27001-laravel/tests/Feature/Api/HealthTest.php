<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_liveness_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure(['status', 'timestamp']);
    }

    public function test_liveness_returns_correlation_id_header(): void
    {
        $response = $this->withHeaders(['X-Request-ID' => 'trace-abc-123'])
            ->getJson('/api/health');

        $response->assertHeader('X-Request-ID', 'trace-abc-123');
    }

    public function test_liveness_generates_correlation_id_when_not_provided(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_readiness_returns_status_structure(): void
    {
        $response = $this->getJson('/api/health/ready');

        // May be 200 or 503 depending on test DB
        $this->assertContains($response->getStatusCode(), [200, 503]);
        $response->assertJsonStructure(['status', 'checks', 'timestamp']);
    }

    public function test_security_headers_present(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Strict-Transport-Security');
    }

    public function test_detailed_requires_authentication(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(401);
    }
}

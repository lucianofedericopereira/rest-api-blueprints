<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testLiveness(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testReadinessReturnsJsonResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/ready');

        // May be 200 or 503 depending on test DB connectivity
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 503]);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
    }

    public function testDetailedRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/detailed');

        // Without auth, should redirect or return 401/403
        $this->assertContains($client->getResponse()->getStatusCode(), [302, 401, 403]);
    }

    public function testCorrelationIdInResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health', [], [], ['HTTP_X-Request-ID' => 'test-trace-123']);

        $this->assertResponseHeaderSame('x-request-id', 'test-trace-123');
    }
}

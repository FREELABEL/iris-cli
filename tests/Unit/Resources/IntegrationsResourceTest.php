<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Integrations\Integration;
use IRIS\SDK\Resources\Integrations\IntegrationCollection;
use IRIS\SDK\Resources\Integrations\TestResult;

class IntegrationsResourceTest extends TestCase
{
    public function test_list_integrations(): void
    {
        $this->mockResponse('GET', '/api/v1/integrations', [
            'data' => [
                ['id' => 1, 'type' => 'gmail', 'name' => 'Gmail', 'status' => 'connected'],
                ['id' => 2, 'type' => 'slack', 'name' => 'Slack', 'status' => 'disconnected'],
            ],
        ]);

        $integrations = $this->iris->integrations->list();

        $this->assertInstanceOf(IntegrationCollection::class, $integrations);
        $this->assertCount(2, $integrations);
        $this->assertTrue($integrations->first()->isConnected());
    }

    public function test_create_integration(): void
    {
        $this->mockResponse('POST', '/api/v1/integrations', [
            'id' => 789,
            'type' => 'gmail',
            'name' => 'Gmail Integration',
            'status' => 'connected',
        ]);

        $integration = $this->iris->integrations->create([
            'type' => 'gmail',
            'name' => 'Gmail Integration',
        ]);

        $this->assertInstanceOf(Integration::class, $integration);
        $this->assertEquals('gmail', $integration->type);
    }

    public function test_get_oauth_url(): void
    {
        $this->mockResponse('GET', '/api/v1/integrations/oauth-url/google-drive', [
            'url' => 'https://accounts.google.com/oauth...',
        ]);

        $url = $this->iris->integrations->getOAuthUrl('google-drive');

        $this->assertStringContainsString('oauth', $url);
    }

    public function test_execute_integration(): void
    {
        $this->mockResponse('POST', '/api/v1/integrations/execute', [
            'result' => 'success',
            'data' => ['email_sent' => true],
        ]);

        $result = $this->iris->integrations->execute('gmail', 'send_email', [
            'to' => 'test@example.com',
            'subject' => 'Test',
        ]);

        $this->assertEquals('success', $result['result']);
    }

    public function test_test_integration(): void
    {
        $this->mockResponse('POST', '/api/v1/integrations/789/test', [
            'success' => true,
            'message' => 'Connection successful',
            'latency_ms' => 150,
        ]);

        $result = $this->iris->integrations->test(789);

        $this->assertInstanceOf(TestResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(150, $result->latencyMs);
    }

    public function test_enabled_integrations(): void
    {
        $this->mockResponse('GET', '/api/v1/integrations/enabled', [
            'data' => [
                ['id' => 1, 'type' => 'gmail', 'status' => 'connected'],
            ],
        ]);

        $enabled = $this->iris->integrations->enabled();

        $this->assertCount(1, $enabled);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit;

use IRIS\SDK\IRIS;
use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;
use IRIS\SDK\Auth\AuthManager;
use IRIS\SDK\Resources\Agents\AgentsResource;
use IRIS\SDK\Resources\Bloqs\BloqsResource;
use IRIS\SDK\Resources\Leads\LeadsResource;
use IRIS\SDK\Resources\Workflows\WorkflowsResource;
use IRIS\SDK\Resources\Integrations\IntegrationsResource;
use IRIS\SDK\Resources\RAG\RAGResource;
use IRIS\SDK\Resources\CloudFiles\CloudFilesResource;
use IRIS\SDK\Resources\Usage\UsageResource;
use IRIS\SDK\Resources\Vapi\VapiResource;
use IRIS\SDK\Resources\Models\ModelsResource;
use IRIS\SDK\Resources\Chat\ChatResource;
use IRIS\SDK\Events\WebhookHandler;
use PHPUnit\Framework\TestCase;

/**
 * IRIS Client Tests
 *
 * Tests for the main IRIS SDK client class.
 */
class IRISTest extends TestCase
{
    // ========================================
    // Constructor & Initialization
    // ========================================

    public function test_creates_client_with_required_options(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_api_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(IRIS::class, $iris);
    }

    public function test_creates_client_with_all_options(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_api_key',
            'user_id' => 123,
            'base_url' => 'https://api.custom.iris.ai',
            'iris_url' => 'https://iris.custom.iris.ai',
            'timeout' => 60,
            'retries' => 5,
            'webhook_secret' => 'whsec_test',
            'client_id' => 'client_123',
            'client_secret' => 'secret_456',
            'debug' => true,
        ]);

        $this->assertInstanceOf(IRIS::class, $iris);
    }

    public function test_throws_exception_without_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IRIS([
            'user_id' => 123,
        ]);
    }

    // ========================================
    // Resource Access
    // ========================================

    public function test_exposes_agents_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(AgentsResource::class, $iris->agents);
    }

    public function test_exposes_bloqs_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(BloqsResource::class, $iris->bloqs);
    }

    public function test_exposes_leads_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(LeadsResource::class, $iris->leads);
    }

    public function test_exposes_workflows_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(WorkflowsResource::class, $iris->workflows);
    }

    public function test_exposes_integrations_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(IntegrationsResource::class, $iris->integrations);
    }

    public function test_exposes_rag_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(RAGResource::class, $iris->rag);
    }

    public function test_exposes_cloud_files_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(CloudFilesResource::class, $iris->cloudFiles);
    }

    public function test_exposes_usage_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(UsageResource::class, $iris->usage);
    }

    public function test_exposes_vapi_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(VapiResource::class, $iris->vapi);
    }

    public function test_exposes_models_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(ModelsResource::class, $iris->models);
    }

    public function test_exposes_chat_resource(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $this->assertInstanceOf(ChatResource::class, $iris->chat);
    }

    // ========================================
    // Configuration
    // ========================================

    public function test_get_config(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $config = $iris->getConfig();

        $this->assertInstanceOf(Config::class, $config);
        $this->assertEquals(123, $config->userId);
    }

    public function test_get_http_client(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $client = $iris->getHttpClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_get_auth_manager(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $auth = $iris->auth();

        $this->assertInstanceOf(AuthManager::class, $auth);
    }

    // ========================================
    // User Context
    // ========================================

    public function test_as_user_changes_context(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        $result = $iris->asUser(456);

        // Returns self for chaining
        $this->assertSame($iris, $result);

        // Config should be updated
        $this->assertEquals(456, $iris->getConfig()->userId);
    }

    public function test_as_user_is_chainable(): void
    {
        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
        ]);

        // Should be able to chain
        $this->assertInstanceOf(IRIS::class, $iris->asUser(456));
    }

    // ========================================
    // Webhooks
    // ========================================

    public function test_creates_webhook_handler(): void
    {
        // Skip if WebhookHandler class doesn't exist yet
        if (!class_exists(WebhookHandler::class)) {
            $this->markTestSkipped('WebhookHandler class not yet implemented');
        }

        $iris = new IRIS([
            'api_key' => 'test_key',
            'user_id' => 123,
            'webhook_secret' => 'whsec_test123',
        ]);

        $handler = $iris->webhooks();

        $this->assertInstanceOf(WebhookHandler::class, $handler);
    }

    // ========================================
    // Version
    // ========================================

    public function test_version_constant_exists(): void
    {
        $this->assertNotEmpty(IRIS::VERSION);
        $this->assertIsString(IRIS::VERSION);
    }

    public function test_version_follows_semver(): void
    {
        // Version should be like 1.0.0
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', IRIS::VERSION);
    }
}

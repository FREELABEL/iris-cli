<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Agents\Agent;
use IRIS\SDK\Resources\Agents\AgentCollection;
use IRIS\SDK\Resources\Agents\AgentConfig;
use IRIS\SDK\Resources\Agents\ChatResponse;
use IRIS\SDK\Resources\Workflows\WorkflowRun;

/**
 * AgentsResource Tests
 *
 * Tests for AI agent management, chat, and memory operations.
 */
class AgentsResourceTest extends TestCase
{
    // ========================================
    // List Operations
    // ========================================

    public function test_list_agents(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents', [
            'data' => [
                ['id' => 1, 'name' => 'Marketing Agent', 'model' => 'gpt-4.1-nano'],
                ['id' => 2, 'name' => 'Support Agent', 'model' => 'gpt-4o-mini'],
            ],
            'meta' => ['total' => 2, 'per_page' => 20],
        ]);

        $agents = $this->iris->agents->list();

        $this->assertInstanceOf(AgentCollection::class, $agents);
        $this->assertCount(2, $agents);
        $this->assertEquals('Marketing Agent', $agents->first()->name);
    }

    public function test_list_agents_with_pagination(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents', [
            'data' => [
                ['id' => 3, 'name' => 'Page 2 Agent'],
            ],
            'meta' => ['total' => 3, 'per_page' => 2, 'current_page' => 2],
        ]);

        $agents = $this->iris->agents->list(['page' => 2, 'per_page' => 2]);

        $this->assertInstanceOf(AgentCollection::class, $agents);
        $this->assertCount(1, $agents);
    }

    public function test_list_agents_with_search(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents', [
            'data' => [
                ['id' => 1, 'name' => 'Marketing Assistant'],
            ],
            'meta' => ['total' => 1],
        ]);

        $agents = $this->iris->agents->list(['search' => 'marketing']);

        $this->assertCount(1, $agents);
    }

    // ========================================
    // CRUD Operations
    // ========================================

    public function test_get_agent(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'initial_prompt' => 'You are a helpful assistant.',
            'model' => 'gpt-4.1-nano',
            'bloq_id' => 32,
            'is_public' => false,
            'fileAttachments' => [],
        ]);

        $agent = $this->iris->agents->get(456);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertEquals(456, $agent->id);
        $this->assertEquals('Test Agent', $agent->name);
        $this->assertEquals('gpt-4.1-nano', $agent->model);
    }

    public function test_create_agent(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/agents', [
            'id' => 789,
            'name' => 'New Agent',
            'initial_prompt' => 'Custom prompt',
            'model' => 'gpt-5-nano',
            'bloq_id' => 40,
        ]);

        $config = new AgentConfig(
            name: 'New Agent',
            prompt: 'Custom prompt',
            model: 'gpt-5-nano',
            bloqId: 40
        );

        $agent = $this->iris->agents->create($config);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertEquals(789, $agent->id);
        $this->assertEquals('New Agent', $agent->name);
    }

    public function test_update_agent(): void
    {
        $this->mockResponse('PUT', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Updated Agent',
            'initial_prompt' => 'Updated prompt',
            'model' => 'gpt-4.1-nano',
        ]);

        $agent = $this->iris->agents->update(456, [
            'name' => 'Updated Agent',
            'initial_prompt' => 'Updated prompt',
        ]);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertEquals('Updated Agent', $agent->name);
    }

    public function test_patch_agent(): void
    {
        // First call to get current agent
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Original Name',
            'initial_prompt' => 'Original prompt',
            'model' => 'gpt-4.1-nano',
        ]);

        // Second call to update
        $this->mockResponse('PUT', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Original Name',
            'initial_prompt' => 'New prompt only',
            'model' => 'gpt-4.1-nano',
        ]);

        $agent = $this->iris->agents->patch(456, [
            'initial_prompt' => 'New prompt only',
        ]);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertEquals('New prompt only', $agent->initialPrompt);
    }

    public function test_delete_agent(): void
    {
        $this->mockResponse('DELETE', '/api/v1/users/123/bloqs/agents/456', [
            'success' => true,
        ]);

        $result = $this->iris->agents->delete(456);

        $this->assertTrue($result);
    }

    // ========================================
    // Chat Operations
    // ========================================

    public function test_chat_with_agent(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/agents/generate-response', [
            'content' => 'Hello! How can I help you today?',
            'role' => 'assistant',
            'agent_id' => 456,
            'model' => 'gpt-4.1-nano',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 15,
                'total_tokens' => 25,
            ],
        ]);

        $response = $this->iris->agents->chat(456, [
            ['role' => 'user', 'content' => 'Hello!']
        ]);

        $this->assertInstanceOf(ChatResponse::class, $response);
        $this->assertEquals('Hello! How can I help you today?', $response->content);
        $this->assertEquals('assistant', $response->role);
    }

    public function test_chat_with_options(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/agents/generate-response', [
            'content' => 'Based on the documents...',
            'role' => 'assistant',
        ]);

        $response = $this->iris->agents->chat(456, [
            ['role' => 'user', 'content' => 'What do my documents say?']
        ], [
            'bloq_id' => 32,
            'use_rag' => true,
            'thread_id' => 'thread_abc123',
        ]);

        $this->assertInstanceOf(ChatResponse::class, $response);
    }

    public function test_multi_step_workflow(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/agents/multi-step-response', [
            'workflow_id' => 'wf_123',
            'status' => 'running',
            'steps' => [
                ['name' => 'Research', 'status' => 'pending'],
                ['name' => 'Analysis', 'status' => 'pending'],
            ],
        ]);

        $workflow = $this->iris->agents->multiStep(456, 'Research competitors and create a report', [
            'bloq_id' => 32,
            'require_approval' => true,
        ]);

        $this->assertInstanceOf(WorkflowRun::class, $workflow);
    }

    // ========================================
    // File Attachment Operations
    // ========================================

    public function test_get_file_attachments(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'data.csv', 'size' => 1000],
                ['cloud_file_id' => 101, 'name' => 'docs.pdf', 'size' => 2000],
            ],
        ]);

        $attachments = $this->iris->agents->getFileAttachments(456);

        $this->assertCount(2, $attachments);
        $this->assertEquals('data.csv', $attachments[0]['name']);
    }

    public function test_add_file_attachments(): void
    {
        // Get current agent
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'existing.csv'],
            ],
        ]);

        // Update with merged attachments
        $this->mockResponse('PUT', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'existing.csv'],
                ['cloud_file_id' => 200, 'name' => 'new.pdf'],
            ],
        ]);

        $agent = $this->iris->agents->addFileAttachments(456, [
            ['cloud_file_id' => 200, 'name' => 'new.pdf'],
        ]);

        $this->assertCount(2, $agent->fileAttachments);
    }

    public function test_set_file_attachments_replaces_all(): void
    {
        // Get current agent
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'old.csv'],
            ],
        ]);

        // Update with only new attachments
        $this->mockResponse('PUT', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'fileAttachments' => [
                ['cloud_file_id' => 300, 'name' => 'replacement.pdf'],
            ],
        ]);

        $agent = $this->iris->agents->setFileAttachments(456, [
            ['cloud_file_id' => 300, 'name' => 'replacement.pdf'],
        ]);

        $this->assertCount(1, $agent->fileAttachments);
        $this->assertEquals(300, $agent->fileAttachments[0]['cloud_file_id']);
    }

    public function test_remove_file_attachment(): void
    {
        // Get current agent with 2 attachments
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'keep.csv'],
                ['cloud_file_id' => 200, 'name' => 'remove.pdf'],
            ],
        ]);

        // Update with filtered attachments
        $this->mockResponse('PUT', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'keep.csv'],
            ],
        ]);

        $agent = $this->iris->agents->removeFileAttachment(456, 200);

        $this->assertCount(1, $agent->fileAttachments);
        $this->assertEquals(100, $agent->fileAttachments[0]['cloud_file_id']);
    }

    public function test_clear_file_attachments(): void
    {
        // Get current agent
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'fileAttachments' => [
                ['cloud_file_id' => 100, 'name' => 'file1.csv'],
                ['cloud_file_id' => 200, 'name' => 'file2.pdf'],
            ],
        ]);

        // Update with empty attachments
        $this->mockResponse('PUT', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'fileAttachments' => [],
        ]);

        $agent = $this->iris->agents->clearFileAttachments(456);

        $this->assertEmpty($agent->fileAttachments);
    }

    // ========================================
    // Public Agent Operations
    // ========================================

    public function test_toggle_public(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/agents/456/public/toggle', [
            'id' => 456,
            'is_public' => true,
            'public_slug' => 'my-public-agent',
        ]);

        $agent = $this->iris->agents->togglePublic(456, true);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertTrue($agent->isPublic);
    }

    public function test_get_analytics(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456/public/analytics', [
            'views' => 1500,
            'conversations' => 250,
            'unique_users' => 100,
            'avg_messages_per_conversation' => 4.5,
        ]);

        $analytics = $this->iris->agents->getAnalytics(456);

        $this->assertEquals(1500, $analytics['views']);
        $this->assertEquals(250, $analytics['conversations']);
    }

    public function test_list_public_agents(): void
    {
        $this->mockResponse('GET', '/api/v1/public/agents', [
            'data' => [
                ['id' => 1, 'name' => 'Public Agent 1', 'public_slug' => 'agent-1'],
                ['id' => 2, 'name' => 'Public Agent 2', 'public_slug' => 'agent-2'],
            ],
            'meta' => ['total' => 2],
        ]);

        $agents = $this->iris->agents->listPublic();

        $this->assertInstanceOf(AgentCollection::class, $agents);
        $this->assertCount(2, $agents);
    }

    public function test_chat_with_public_agent_by_slug(): void
    {
        $this->mockResponse('POST', '/api/v1/public/agents/marketing-assistant/chat', [
            'content' => 'Public agent response',
            'role' => 'assistant',
        ]);

        $response = $this->iris->agents->chatPublic('marketing-assistant', [
            ['role' => 'user', 'content' => 'Hello!']
        ]);

        $this->assertInstanceOf(ChatResponse::class, $response);
        $this->assertEquals('Public agent response', $response->content);
    }

    // ========================================
    // Webhook Operations
    // ========================================

    public function test_generate_webhook(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/agents/456/webhook/generate', [
            'url' => 'https://api.iris.ai/webhooks/agent/abc123',
            'secret' => 'whsec_xyz789',
        ]);

        $result = $this->iris->agents->generateWebhook(456);

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('secret', $result);
        $this->assertStringContainsString('webhooks/agent', $result['url']);
    }

    public function test_get_webhook(): void
    {
        $this->mockResponse('GET', '/api/v1/bloqs/agents/456/webhook', [
            'url' => 'https://api.iris.ai/webhooks/agent/abc123',
            'enabled' => true,
            'events' => ['message.received', 'workflow.completed'],
        ]);

        $settings = $this->iris->agents->getWebhook(456);

        $this->assertTrue($settings['enabled']);
        $this->assertContains('message.received', $settings['events']);
    }

    // ========================================
    // MCP Tools
    // ========================================

    public function test_discover_mcp_tools(): void
    {
        $this->mockResponse('POST', '/api/v1/agents/456/mcp/discover-tools', [
            'tools' => [
                ['name' => 'google_search', 'description' => 'Search Google'],
                ['name' => 'web_browse', 'description' => 'Browse websites'],
            ],
        ]);

        $result = $this->iris->agents->discoverTools(456);

        $this->assertArrayHasKey('tools', $result);
        $this->assertCount(2, $result['tools']);
    }

    // ========================================
    // URL Generation
    // ========================================

    public function test_get_agent_url(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'bloq_id' => 40,
        ]);

        $url = $this->iris->agents->getUrl(456);

        $this->assertStringContainsString('/agent/simple/456', $url);
        $this->assertStringContainsString('bloq=40', $url);
    }

    public function test_get_agent_urls(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/agents/456', [
            'id' => 456,
            'name' => 'Test Agent',
            'bloq_id' => 40,
            'is_public' => true,
            'public_slug' => 'my-agent',
        ]);

        $urls = $this->iris->agents->getUrls(456);

        $this->assertArrayHasKey('simple', $urls);
        $this->assertArrayHasKey('embed', $urls);
        $this->assertArrayHasKey('public', $urls);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Exceptions\IRISException;

/**
 * ChatResource Tests
 *
 * Tests for real-time chat with AI agents using the IRIS V5 workflow system.
 */
class ChatResourceTest extends TestCase
{
    // ========================================
    // Async Chat (Start/Poll Pattern)
    // ========================================

    public function test_start_chat(): void
    {
        $this->mockResponse('POST', '/api/chat/start', [
            'workflow_id' => 'wf_chat_123',
            'message' => 'Workflow started',
        ]);

        $response = $this->iris->chat->start([
            'query' => 'Hello, how are you?',
            'agentId' => 11,
            'bloqId' => 32,
        ]);

        $this->assertArrayHasKey('workflow_id', $response);
        $this->assertEquals('wf_chat_123', $response['workflow_id']);
    }

    public function test_start_chat_with_conversation_history(): void
    {
        $this->mockResponse('POST', '/api/chat/start', [
            'workflow_id' => 'wf_chat_456',
            'message' => 'Workflow started',
        ]);

        $response = $this->iris->chat->start([
            'query' => 'What about the second point?',
            'agentId' => 11,
            'conversationHistory' => [
                ['role' => 'user', 'content' => 'Tell me about AI'],
                ['role' => 'assistant', 'content' => 'AI has several key aspects...'],
                ['role' => 'user', 'content' => 'What about the second point?'],
            ],
        ]);

        $this->assertArrayHasKey('workflow_id', $response);
    }

    public function test_start_chat_with_uploaded_files(): void
    {
        $this->mockResponse('POST', '/api/chat/start', [
            'workflow_id' => 'wf_file_chat',
            'message' => 'Workflow started with files',
        ]);

        $response = $this->iris->chat->start([
            'query' => 'What does this document say?',
            'agentId' => 11,
            'uploadedFiles' => [
                ['cloud_file_id' => 100, 'name' => 'report.pdf'],
            ],
        ]);

        $this->assertArrayHasKey('workflow_id', $response);
    }

    // ========================================
    // Status Polling
    // ========================================

    public function test_get_chat_status_running(): void
    {
        $this->mockResponse('GET', '/api/workflows/wf_chat_123', [
            'workflow_id' => 'wf_chat_123',
            'status' => 'running',
            'user_input' => 'Hello, how are you?',
            'summary' => null,
            'metrics' => [
                'tokens_used' => 0,
                'steps_completed' => 0,
            ],
        ]);

        $status = $this->iris->chat->getStatus('wf_chat_123');

        $this->assertEquals('running', $status['status']);
        $this->assertNull($status['summary']);
    }

    public function test_get_chat_status_completed(): void
    {
        $this->mockResponse('GET', '/api/workflows/wf_chat_123', [
            'workflow_id' => 'wf_chat_123',
            'status' => 'completed',
            'user_input' => 'Hello!',
            'summary' => 'Hello! I am doing well. How can I help you today?',
            'agent_name' => 'Support Agent',
            'metrics' => [
                'tokens_used' => 150,
                'duration_ms' => 2500,
            ],
            'execution_results' => [
                ['step' => 'response_generation', 'status' => 'success'],
            ],
        ]);

        $status = $this->iris->chat->getStatus('wf_chat_123');

        $this->assertEquals('completed', $status['status']);
        $this->assertNotNull($status['summary']);
        $this->assertEquals('Hello! I am doing well. How can I help you today?', $status['summary']);
    }

    public function test_get_chat_status_paused(): void
    {
        $this->mockResponse('GET', '/api/workflows/wf_paused', [
            'workflow_id' => 'wf_paused',
            'status' => 'paused',
            'requires_approval' => true,
            'pending_task' => [
                'type' => 'confirmation',
                'message' => 'Do you want to proceed with sending this email?',
            ],
        ]);

        $status = $this->iris->chat->getStatus('wf_paused');

        $this->assertEquals('paused', $status['status']);
        $this->assertTrue($status['requires_approval']);
    }

    public function test_get_chat_status_failed(): void
    {
        $this->mockResponse('GET', '/api/workflows/wf_failed', [
            'workflow_id' => 'wf_failed',
            'status' => 'failed',
            'error' => 'Agent timed out',
            'summary' => 'I was unable to complete your request due to a timeout.',
        ]);

        $status = $this->iris->chat->getStatus('wf_failed');

        $this->assertEquals('failed', $status['status']);
        $this->assertArrayHasKey('error', $status);
    }

    // ========================================
    // Resume (Human-in-the-Loop)
    // ========================================

    public function test_resume_paused_workflow(): void
    {
        $this->mockResponse('POST', '/api/chat/resume', [
            'workflow_id' => 'wf_paused',
            'status' => 'running',
            'message' => 'Workflow resumed',
        ]);

        $response = $this->iris->chat->resume('wf_paused', [
            'approved' => true,
            'message' => 'Yes, proceed with sending the email',
        ]);

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('running', $response['status']);
    }

    public function test_resume_with_rejection(): void
    {
        $this->mockResponse('POST', '/api/chat/resume', [
            'workflow_id' => 'wf_paused',
            'status' => 'cancelled',
            'message' => 'User cancelled the action',
        ]);

        $response = $this->iris->chat->resume('wf_paused', [
            'approved' => false,
            'message' => 'No, do not send the email',
        ]);

        $this->assertEquals('cancelled', $response['status']);
    }

    // ========================================
    // Conversation Management
    // ========================================

    public function test_summarize_conversation(): void
    {
        $this->mockResponse('POST', '/api/chat/summarize', [
            'consolidated' => [
                ['role' => 'system', 'content' => 'Summary of previous conversation: User discussed AI topics and asked about machine learning basics.'],
                ['role' => 'user', 'content' => 'Recent message 1'],
                ['role' => 'assistant', 'content' => 'Recent response 1'],
                ['role' => 'user', 'content' => 'Recent message 2'],
                ['role' => 'assistant', 'content' => 'Recent response 2'],
            ],
            'originalCount' => 25,
            'consolidatedCount' => 5,
        ]);

        $result = $this->iris->chat->summarize([
            // ... 25 messages
        ], 4, 20);

        $this->assertArrayHasKey('consolidated', $result);
        $this->assertArrayHasKey('originalCount', $result);
        $this->assertArrayHasKey('consolidatedCount', $result);
        $this->assertEquals(25, $result['originalCount']);
        $this->assertEquals(5, $result['consolidatedCount']);
    }

    // ========================================
    // History & Stats
    // ========================================

    public function test_get_chat_history(): void
    {
        $this->mockResponse('GET', '/api/users/123/workflows', [
            'data' => [
                ['workflow_id' => 'wf_1', 'status' => 'completed', 'created_at' => '2025-12-23T10:00:00Z'],
                ['workflow_id' => 'wf_2', 'status' => 'completed', 'created_at' => '2025-12-23T09:00:00Z'],
            ],
            'meta' => ['total' => 2, 'per_page' => 20],
        ]);

        $history = $this->iris->chat->history();

        $this->assertArrayHasKey('data', $history);
        $this->assertCount(2, $history['data']);
    }

    public function test_get_chat_history_with_filters(): void
    {
        $this->mockResponse('GET', '/api/users/123/workflows', [
            'data' => [
                ['workflow_id' => 'wf_agent11', 'agent_id' => 11],
            ],
            'meta' => ['total' => 1],
        ]);

        $history = $this->iris->chat->history([
            'agent_id' => 11,
            'status' => 'completed',
            'per_page' => 10,
        ]);

        $this->assertCount(1, $history['data']);
    }

    public function test_get_chat_stats(): void
    {
        $this->mockResponse('GET', '/api/users/123/workflows/stats', [
            'total' => 150,
            'completed' => 140,
            'failed' => 5,
            'running' => 3,
            'paused' => 2,
            'avg_duration_ms' => 3500,
            'total_tokens' => 50000,
        ]);

        $stats = $this->iris->chat->stats();

        $this->assertEquals(150, $stats['total']);
        $this->assertEquals(140, $stats['completed']);
        $this->assertEquals(5, $stats['failed']);
    }
}

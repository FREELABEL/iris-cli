<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Chat;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;
use IRIS\SDK\Exceptions\IRISException;

/**
 * Chat Resource
 *
 * Real-time chat with AI agents using IRIS V5 workflow system.
 * Supports async (start/poll) and sync (execute with automatic polling) modes.
 *
 * @example Async usage (start + poll):
 * ```php
 * $response = $iris->chat->start([
 *     'query' => 'Hello!',
 *     'agentId' => 11,
 *     'bloqId' => 32,
 * ]);
 *
 * $workflowId = $response['workflow_id'];
 *
 * while (true) {
 *     $status = $iris->chat->getStatus($workflowId);
 *     if ($status['status'] === 'completed') {
 *         echo $status['summary'];
 *         break;
 *     }
 *     usleep(500000);
 * }
 * ```
 *
 * @example Sync usage (blocking with automatic polling):
 * ```php
 * $result = $iris->chat->execute([
 *     'query' => 'Hello!',
 *     'agentId' => 11,
 *     'bloqId' => 32,
 * ]);
 *
 * echo $result['summary'];
 * ```
 */
class ChatResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Start a new chat workflow (non-blocking).
     *
     * Returns immediately with a workflow_id. Use getStatus() to poll for results.
     *
     * @param array{
     *     query: string,
     *     agentId: int|string,
     *     bloqId?: int|string,
     *     conversationHistory?: array,
     *     uploadedFiles?: array,
     *     contextPayload?: array
     * } $options Chat options
     * @return array{workflow_id: string, message: string}
     */
    public function start(array $options): array
    {
        $userId = $this->config->requireUserId();

        $payload = [
            'query' => $options['query'],
            'agentId' => $options['agentId'],
            'userId' => $userId,
            'conversationHistory' => $options['conversationHistory'] ?? [
                ['role' => 'user', 'content' => $options['query']]
            ],
            'bloqId' => isset($options['bloqId']) ? (string) $options['bloqId'] : null,
            'uploadedFiles' => $options['uploadedFiles'] ?? [],
            'enableRAG' => $options['enableRAG'] ?? true, // Enable RAG by default for file attachments
            'contextPayload' => $options['contextPayload'] ?? [
                'source' => 'sdk',
            ],
        ];

        // Filter null values
        $payload = array_filter($payload, fn($v) => $v !== null);

        return $this->http->post('/api/chat/start', $payload);
    }

    /**
     * Get the status of a chat workflow.
     *
     * @param string $workflowId The workflow ID returned from start()
     * @return array Workflow status including:
     *   - workflow_id: string
     *   - status: 'running'|'paused'|'completed'|'failed'
     *   - user_input: string
     *   - summary: ?string (final response when completed)
     *   - agent_name: ?string
     *   - metrics: array
     *   - execution_results: array (when completed)
     */
    public function getStatus(string $workflowId): array
    {
        return $this->http->get("/api/workflows/{$workflowId}");
    }

    /**
     * Execute a chat and wait for completion (blocking).
     *
     * This method starts a chat workflow and polls until completion,
     * calling the optional progress callback on each poll.
     *
     * @param array{
     *     query: string,
     *     agentId: int|string,
     *     bloqId?: int|string,
     *     conversationHistory?: array,
     *     uploadedFiles?: array
     * } $options Chat options
     * @param callable|null $onProgress Optional callback called on each status update
     *                                   Signature: function(array $status): void
     * @return array Complete workflow result
     * @throws IRISException On timeout or failure
     */
    public function execute(array $options, ?callable $onProgress = null): array
    {
        // Start the workflow
        $response = $this->start($options);
        $workflowId = $response['workflow_id'];

        // Poll until completion
        $startTime = time();
        $maxDuration = $this->config->maxPollingDuration;
        $pollInterval = $this->config->pollingInterval * 1000; // Convert to microseconds

        while (true) {
            // Check timeout
            if ((time() - $startTime) > $maxDuration) {
                throw new IRISException(
                    "Chat execution timed out after {$maxDuration} seconds. " .
                    "Workflow ID: {$workflowId}"
                );
            }

            // Get status
            $status = $this->getStatus($workflowId);

            // Call progress callback
            if ($onProgress !== null) {
                $onProgress($status);
            }

            // Check if complete
            if (in_array($status['status'], ['completed', 'failed'])) {
                if ($status['status'] === 'failed') {
                    $error = $status['error'] ?? $status['summary'] ?? 'Unknown error';
                    throw new IRISException("Chat workflow failed: {$error}");
                }
                return $status;
            }

            // Check if requires human input
            if ($status['status'] === 'paused' && ($status['requires_approval'] ?? false)) {
                // Return paused status for HITL handling
                return $status;
            }

            // Wait before next poll
            usleep($pollInterval);
        }
    }

    /**
     * Resume a paused workflow (Human-in-the-Loop).
     *
     * @param string $workflowId The workflow ID
     * @param array $feedback User feedback/approval data
     * @return array Resume response
     */
    public function resume(string $workflowId, array $feedback): array
    {
        return $this->http->post('/api/chat/resume', [
            'workflow_id' => $workflowId,
            'feedback' => $feedback,
        ]);
    }

    /**
     * Summarize a conversation history.
     *
     * Useful for compressing long conversations to save tokens.
     *
     * @param array $messages Conversation messages
     * @param int $keepRecent Number of recent messages to keep verbatim
     * @param int $threshold Minimum messages before summarization
     * @return array{consolidated: array, originalCount: int, consolidatedCount: int}
     */
    public function summarize(array $messages, int $keepRecent = 4, int $threshold = 20): array
    {
        return $this->http->post('/api/chat/summarize', [
            'messages' => $messages,
            'keepRecent' => $keepRecent,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Get user's recent workflows.
     *
     * @param array{
     *     status?: string,
     *     agent_id?: int,
     *     per_page?: int,
     *     page?: int
     * } $options Filter options
     * @return array Paginated workflow list
     */
    public function history(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/users/{$userId}/workflows", $options);
    }

    /**
     * Get workflow statistics for the current user.
     *
     * @return array Statistics including total, completed, failed, running counts
     */
    public function stats(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/users/{$userId}/workflows/stats");
    }
}

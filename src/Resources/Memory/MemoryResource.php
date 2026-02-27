<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Memory;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Memory Resource
 *
 * Manage persistent working memory for AI agents. Store facts, insights,
 * context, and preferences that agents accumulate during conversations
 * and workflows.
 *
 * Memory Types:
 * - fact: Learned information (e.g., "Client's budget is $50k/quarter")
 * - insight: Discovered patterns (e.g., "Email open rates peak Tuesdays")
 * - context: Project/workflow status (e.g., "Phase 3 of 5 complete")
 * - preference: User preferences (e.g., "Prefers formal tone")
 * - relationship: Information about other agents
 * - document: Contracts, agreements, and reference documents
 *
 * @example
 * ```php
 * // Store a memory
 * $iris->memory->store(11, 'fact', 'Client prefers morning meetings', [
 *     'topic' => 'scheduling',
 *     'importance' => 7,
 * ]);
 *
 * // Search memories
 * $results = $iris->memory->search(11, 'meeting preferences');
 *
 * // List all memories for an agent
 * $memories = $iris->memory->list(11, ['topic' => 'scheduling']);
 * ```
 */
class MemoryResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List memories for an agent.
     *
     * @param int $agentId The agent ID
     * @param array{
     *     topic?: string,
     *     memory_type?: string,
     *     limit?: int,
     *     min_importance?: int
     * } $options Filter options
     * @return array List of memories
     *
     * @example
     * ```php
     * // List all memories
     * $memories = $iris->memory->list(11);
     *
     * // Filter by topic
     * $memories = $iris->memory->list(11, ['topic' => 'scheduling']);
     *
     * // Filter by type and importance
     * $memories = $iris->memory->list(11, [
     *     'memory_type' => 'fact',
     *     'min_importance' => 7,
     * ]);
     * ```
     */
    public function list(int $agentId, array $options = []): array
    {
        $userId = $this->config->requireUserId();

        $params = array_merge([
            'user_id' => $userId,
            'agent_id' => $agentId,
        ], $options);

        return $this->http->get('/api/v6/memory', $params);
    }

    /**
     * Store a new memory for an agent.
     *
     * Includes automatic deduplication — if a substantially similar memory
     * already exists (same topic + type, >80% content similarity), the
     * existing memory is updated instead of creating a duplicate.
     *
     * @param int $agentId The agent ID
     * @param string $type Memory type: fact, insight, context, preference, relationship, document
     * @param string $content The memory content (max 5000 chars)
     * @param array{
     *     topic?: string,
     *     importance?: int
     * } $options Additional options
     * @return array Result with memory_id, action (created/updated), etc.
     *
     * @example
     * ```php
     * // Store a fact
     * $iris->memory->store(11, 'fact', 'Client budget is $50k/quarter', [
     *     'topic' => 'client_profile',
     *     'importance' => 8,
     * ]);
     *
     * // Store project context
     * $iris->memory->store(11, 'context', 'Sprint 3: 8/10 tickets complete', [
     *     'topic' => 'project_status',
     * ]);
     *
     * // Store a task assignment
     * $iris->memory->store(11, 'fact', 'TODO: Draft API spec by Friday', [
     *     'topic' => 'tasks',
     *     'importance' => 9,
     * ]);
     * ```
     */
    public function store(int $agentId, string $type, string $content, array $options = []): array
    {
        $userId = $this->config->requireUserId();

        $data = array_merge([
            'user_id' => $userId,
            'agent_id' => $agentId,
            'memory_type' => $type,
            'content' => $content,
        ], $options);

        return $this->http->post('/api/v6/memory', $data);
    }

    /**
     * Search memories by content or topic.
     *
     * Uses keyword search with optional ChromaDB semantic search fallback.
     *
     * @param int $agentId The agent ID
     * @param string $query Search query
     * @param array{
     *     limit?: int
     * } $options Search options
     * @return array Search results with matching memories
     *
     * @example
     * ```php
     * // Search for meeting-related memories
     * $results = $iris->memory->search(11, 'meeting preferences');
     *
     * foreach ($results['memories'] as $memory) {
     *     echo "[{$memory['type']}] {$memory['content']}\n";
     * }
     * ```
     */
    public function search(int $agentId, string $query, array $options = []): array
    {
        $userId = $this->config->requireUserId();

        $params = array_merge([
            'user_id' => $userId,
            'agent_id' => $agentId,
            'query' => $query,
        ], $options);

        return $this->http->get('/api/v6/memory/search', $params);
    }

    /**
     * Delete a specific memory.
     *
     * @param string $memoryId The memory UUID to delete
     * @return array Result confirmation
     *
     * @example
     * ```php
     * $iris->memory->delete('550e8400-e29b-41d4-a716-446655440000');
     * ```
     */
    public function delete(string $memoryId): array
    {
        $userId = $this->config->requireUserId();

        return $this->http->delete("/v6/memory/{$memoryId}", [
            'user_id' => $userId,
        ]);
    }

    /**
     * List structured CRM entities from the agent's workspace.
     *
     * Proxies leads, tasks, invoices, and outreach steps through the
     * memory namespace. Resolves the agent → bloq → leads chain
     * automatically.
     *
     * @param int $agentId The agent ID
     * @param array{
     *     type?: string,
     *     lead_id?: int,
     *     limit?: int
     * } $options Filter options
     *   - type: Entity type filter (leads, tasks, invoices, outreach)
     *   - lead_id: Get entities for a specific lead
     *   - limit: Max results (default 20)
     * @return array Structured entity data
     *
     * @example
     * ```php
     * // List all leads for the agent's workspace
     * $leads = $iris->memory->entities(11);
     *
     * // List tasks across all leads
     * $tasks = $iris->memory->entities(11, ['type' => 'tasks']);
     *
     * // Get invoices for a specific lead
     * $invoices = $iris->memory->entities(11, [
     *     'type' => 'invoices',
     *     'lead_id' => 412,
     * ]);
     *
     * // Get all sub-entities for a lead
     * $everything = $iris->memory->entities(11, ['lead_id' => 412]);
     * ```
     */
    public function entities(int $agentId, array $options = []): array
    {
        $params = array_merge([
            'agent_id' => $agentId,
        ], $options);

        return $this->http->get('/api/v6/memory/entities', $params);
    }

    /**
     * Get the entity relationship graph for an agent's workspace.
     *
     * Returns the full structured map: Agent → Bloq → Leads → {Tasks,
     * Invoices, Outreach Steps}. Shows how all business entities connect.
     *
     * @param int $agentId The agent ID
     * @return array Entity relationship graph with totals
     *
     * @example
     * ```php
     * $graph = $iris->memory->graph(11);
     *
     * echo "Leads: {$graph['graph']['totals']['leads']}\n";
     * echo "Tasks: {$graph['graph']['totals']['tasks']}\n";
     *
     * foreach ($graph['graph']['leads'] as $lead) {
     *     echo "{$lead['name']} — {$lead['status']}\n";
     *     foreach ($lead['tasks'] as $task) {
     *         $status = $task['is_completed'] ? 'done' : 'pending';
     *         echo "  [{$status}] {$task['title']}\n";
     *     }
     * }
     * ```
     */
    public function graph(int $agentId): array
    {
        return $this->http->get('/api/v6/memory/graph', [
            'agent_id' => $agentId,
        ]);
    }
}

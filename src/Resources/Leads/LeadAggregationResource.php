<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Lead Aggregation Resource
 *
 * Access aggregated lead data for autonomous AI agent pipeline.
 *
 * @example
 * ```php
 * // Get statistics
 * $stats = $iris->leads->aggregation()->statistics();
 * echo "Total leads: {$stats['total_leads']}\n";
 * echo "Incomplete tasks: {$stats['incomplete_tasks']}\n";
 *
 * // List aggregated leads
 * $leads = $iris->leads->aggregation()->list([
 *     'has_incomplete_tasks' => 1,
 *     'per_page' => 25,
 * ]);
 *
 * // Get specific lead with aggregated data
 * $lead = $iris->leads->aggregation()->get(123);
 * echo "Priority: {$lead['priority_score']}\n";
 * echo "Tasks: {$lead['tasks_summary']['incomplete']}\n";
 * ```
 */
class LeadAggregationResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Get aggregated lead statistics.
     *
     * Returns comprehensive statistics including:
     * - Total leads and breakdown by status
     * - Task statistics (total, incomplete, completed)
     * - Leads with incomplete tasks
     * - Recent activity (notes, tasks completed)
     * - Top priority leads with tasks, notes, deliverables, invoices
     *
     * @return array
     *
     * @example
     * ```php
     * $stats = $iris->leads->aggregation()->statistics();
     * 
     * echo "Total leads: {$stats['total_leads']}\n";
     * echo "Incomplete tasks: {$stats['incomplete_tasks']}\n";
     * 
     * // Top priority leads with full details
     * foreach ($stats['top_priority_leads'] as $lead) {
     *     echo "{$lead['nickname']} - Priority: {$lead['priority_score']}\n";
     *     echo "  Tasks: {$lead['tasks_summary']['incomplete']} incomplete\n";
     *     echo "  Notes: {$lead['counts']['notes']}\n";
     * }
     * ```
     */
    public function statistics(): array
    {
        $response = $this->http->get('/api/v1/leads/aggregation/statistics');

        return $response['data'] ?? $response;
    }

    /**
     * List aggregated leads with optional filters.
     *
     * Returns paginated leads with aggregated data including tasks,
     * notes, deliverables, and invoice counts.
     *
     * @param array $filters Optional filters
     *   - per_page: Number of results per page (default: 15)
     *   - page: Page number (default: 1)
     *   - status: Filter by lead status (comma-separated: "won,negotiation,proposal")
     *   - has_incomplete_tasks: Only leads with incomplete tasks (1 or 0)
     *   - min_priority: Minimum priority score
     *   - sort: Sort field (updated_at, created_at, priority, status) - default: updated_at
     *   - order: Sort direction (asc, desc) - default: desc
     *
     * @return array Paginated response with leads
     *
     * @example
     * ```php
     * // Get recently updated leads
     * $leads = $iris->leads->aggregation()->list([
     *     'sort' => 'updated_at',
     *     'order' => 'desc',
     *     'per_page' => 10,
     * ]);
     *
     * // Get high-priority leads with incomplete tasks
     * $leads = $iris->leads->aggregation()->list([
     *     'has_incomplete_tasks' => 1,
     *     'sort' => 'priority',
     *     'per_page' => 25,
     * ]);
     *
     * // Get leads by specific statuses
     * $leads = $iris->leads->aggregation()->list([
     *     'status' => 'won,negotiation,proposal',
     *     'sort' => 'updated_at',
     *     'per_page' => 10,
     * ]);
     *
     * foreach ($leads['data'] as $lead) {
     *     echo "{$lead['nickname']} - {$lead['tasks_summary']['incomplete']} tasks\n";
     *     
     *     foreach ($lead['tasks'] as $task) {
     *         $status = $task['is_completed'] ? '✓' : '○';
     *         echo "  {$status} {$task['title']}\n";
     *     }
     * }
     * ```
     */
    public function list(array $filters = []): array
    {
        $response = $this->http->get('/api/v1/leads/aggregation', $filters);

        return $response;
    }

    /**
     * Get recently updated leads.
     *
     * Convenience method that returns the most recently updated leads,
     * sorted by updated_at DESC. Optionally filter by status.
     *
     * @param int $limit Number of leads to return (default: 10)
     * @param array $filters Optional additional filters
     *   - status: Filter by status (comma-separated: "won,negotiation,proposal")
     *   - has_incomplete_tasks: Only leads with incomplete tasks
     *
     * @return array Array of leads
     *
     * @example
     * ```php
     * // Get 10 most recently updated leads
     * $leads = $iris->leads->aggregation()->getRecentLeads();
     *
     * // Get recent Won/Negotiation/Proposal leads
     * $leads = $iris->leads->aggregation()->getRecentLeads(10, [
     *     'status' => 'won,negotiation,proposal'
     * ]);
     *
     * // Get recent leads with incomplete tasks
     * $leads = $iris->leads->aggregation()->getRecentLeads(15, [
     *     'has_incomplete_tasks' => 1
     * ]);
     * ```
     */
    public function getRecentLeads(int $limit = 10, array $filters = []): array
    {
        $params = array_merge($filters, [
            'sort' => 'updated_at',
            'order' => 'desc',
            'per_page' => $limit,
        ]);

        $response = $this->http->get('/api/v1/leads/aggregation', $params);

        return $response['data'] ?? $response;
    }

    /**
     * Get a specific lead with aggregated data.
     *
     * Returns detailed lead information including:
     * - Basic lead details (nickname, status, priority_score)
     * - All tasks with completion status
     * - Task summary (total, incomplete, completed)
     * - Counts (notes, deliverables, invoices)
     * - Recent notes with timestamps
     *
     * @param int $leadId Lead ID
     * @return array Lead with aggregated data
     *
     * @example
     * ```php
     * $lead = $iris->leads->aggregation()->get(123);
     *
     * echo "Lead: {$lead['nickname']}\n";
     * echo "Priority: {$lead['priority_score']}\n";
     * echo "Status: {$lead['status']}\n\n";
     *
     * echo "Tasks ({$lead['tasks_summary']['incomplete']} incomplete):\n";
     * foreach ($lead['tasks'] as $task) {
     *     $status = $task['is_completed'] ? '✓' : '○';
     *     echo "  {$status} {$task['title']}\n";
     * }
     *
     * echo "\nActivity:\n";
     * echo "  Notes: {$lead['counts']['notes']}\n";
     * echo "  Deliverables: {$lead['counts']['deliverables']}\n";
     * echo "  Invoices: {$lead['counts']['invoices']}\n";
     * ```
     */
    public function get(int $leadId): array
    {
        $response = $this->http->get("/api/v1/leads/aggregation/{$leadId}");

        return $response['data'] ?? $response;
    }

    /**
     * Get requirements and context for a specific lead.
     *
     * Returns AI-optimized context for autonomous agents including:
     * - Lead details and history
     * - Open tasks and deliverables
     * - Recent notes and activity
     * - Related bloqs and knowledge base
     *
     * @param int $leadId Lead ID
     * @return array Requirements context
     *
     * @example
     * ```php
     * $requirements = $iris->leads->aggregation()->requirements(123);
     *
     * // Use with AI agent
     * $response = $iris->agents->chat($agentId, [
     *     ['role' => 'system', 'content' => $requirements['context']],
     *     ['role' => 'user', 'content' => 'What should I work on for this lead?']
     * ]);
     * ```
     */
    public function requirements(int $leadId): array
    {
        $response = $this->http->get("/api/v1/leads/aggregation/{$leadId}/requirements");

        return $response['data'] ?? $response;
    }
}

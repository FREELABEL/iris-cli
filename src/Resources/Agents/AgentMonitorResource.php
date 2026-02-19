<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Agent Monitor Resource
 *
 * Monitor agent performance, view metrics, and manage evaluation results.
 *
 * @example
 * ```php
 * // Get performance metrics
 * $metrics = $iris->agents->monitor(387)->getMetrics('7d');
 *
 * // Log an interaction
 * $iris->agents->monitor(387)->logInteraction([
 *     'response_time_ms' => 1200,
 *     'status' => 'success',
 *     'tools_used' => ['web_search'],
 * ]);
 *
 * // Get latest evaluation
 * $evaluation = $iris->agents->monitor(387)->getLatestEvaluation();
 * ```
 */
class AgentMonitorResource
{
    protected Client $http;
    protected Config $config;
    protected int $agentId;

    public function __construct(Client $http, Config $config, int $agentId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->agentId = $agentId;
    }

    /**
     * Log an agent interaction for performance tracking.
     *
     * @param array{
     *     user_message?: string,
     *     agent_response?: string,
     *     response_time_ms: int,
     *     input_tokens?: int,
     *     output_tokens?: int,
     *     token_cost?: float,
     *     tools_used?: array,
     *     integrations_called?: array,
     *     tool_execution_success?: bool,
     *     tool_error?: string,
     *     status?: string,
     *     model_used?: string,
     *     error_message?: string,
     *     workflow_id?: string,
     *     workflow_steps_count?: int,
     *     workflow_completed?: bool,
     *     session_id?: string
     * } $data Interaction data
     * @return array{success: bool, log_id: int}
     */
    public function logInteraction(array $data): array
    {
        return $this->http->post("/api/v1/agents/{$this->agentId}/logs", $data);
    }

    /**
     * Get performance metrics for the agent.
     *
     * @param string $period Time period (24h, 7d, 30d, 90d)
     * @return array{
     *     avg_response_time_ms: int,
     *     min_response_time_ms: int,
     *     max_response_time_ms: int,
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     success_rate: float,
     *     total_tokens: int,
     *     total_token_cost: float,
     *     uptime_percentage: float,
     *     tool_usage_counts: array,
     *     period_days: int
     * }
     */
    public function getMetrics(string $period = '7d'): array
    {
        return $this->http->get("/api/v1/agents/{$this->agentId}/metrics", [
            'period' => $period,
        ]);
    }

    /**
     * Get metrics summary for UI display.
     *
     * @param string $period Time period (24h, 7d, 30d, 90d)
     * @return array
     */
    public function getSummary(string $period = '7d'): array
    {
        return $this->http->get("/api/v1/agents/{$this->agentId}/metrics/summary", [
            'period' => $period,
        ]);
    }

    /**
     * Get performance logs for the agent.
     *
     * @param array{
     *     status?: string,
     *     from?: string,
     *     to?: string,
     *     limit?: int,
     *     page?: int
     * } $filters Optional filters
     * @return array{
     *     data: array,
     *     meta: array{total: int, page: int, per_page: int, total_pages: int}
     * }
     */
    public function getLogs(array $filters = []): array
    {
        return $this->http->get("/api/v1/agents/{$this->agentId}/logs", $filters);
    }

    /**
     * Get the latest evaluation for the agent.
     *
     * @return array|null Evaluation data or null if none exists
     */
    public function getLatestEvaluation(): ?array
    {
        try {
            return $this->http->get("/api/v1/agents/{$this->agentId}/evaluation/latest");
        } catch (\Exception $e) {
            // 404 means no evaluations yet
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get evaluation history for the agent.
     *
     * @param array{
     *     test_suite?: string,
     *     limit?: int,
     *     page?: int
     * } $filters Optional filters
     * @return array{
     *     data: array,
     *     meta: array{total: int, page: int, per_page: int, total_pages: int}
     * }
     */
    public function getEvaluations(array $filters = []): array
    {
        return $this->http->get("/api/v1/agents/{$this->agentId}/evaluations", $filters);
    }

    /**
     * Store evaluation results from the SDK evaluator.
     *
     * @param array{
     *     test_type?: string,
     *     average_score?: int,
     *     tests_passed: int,
     *     tests_total: int,
     *     pass_rate?: float,
     *     certification_badge?: string,
     *     evaluation_status?: string,
     *     test_results: array,
     *     test_names?: array,
     *     model_used?: string,
     *     sdk_version?: string,
     *     total_duration_ms?: int,
     *     metadata?: array
     * } $data Evaluation data
     * @return array{success: bool, evaluation_id: int, certification_badge: string, average_score: int}
     */
    public function storeEvaluation(array $data): array
    {
        return $this->http->post("/api/v1/agents/{$this->agentId}/evaluation", $data);
    }

    /**
     * Update agent settings with evaluation data.
     *
     * @param array{
     *     average_score: int,
     *     tests_passed: int,
     *     tests_total: int,
     *     pass_rate?: float,
     *     certification_badge?: string,
     *     evaluation_status?: string,
     *     test_type?: string
     * } $data Evaluation data
     * @return array{success: bool, agent_id: int, evaluation: array}
     */
    public function updateAgentSettings(array $data): array
    {
        return $this->http->patch("/api/v1/agents/{$this->agentId}/evaluation", $data);
    }

    /**
     * Get pass rate trend for the agent.
     *
     * @param int $days Number of days to include
     * @return array{agent_id: int, period_days: int, trend: array}
     */
    public function getPassRateTrend(int $days = 30): array
    {
        return $this->http->get("/api/v1/agents/{$this->agentId}/evaluation/trend", [
            'days' => $days,
        ]);
    }

    /**
     * Get agent health status.
     *
     * @return array{
     *     status: string,
     *     uptime: float,
     *     error_rate: float,
     *     avg_response_time: int,
     *     last_error: string|null
     * }
     */
    public function health(): array
    {
        $metrics = $this->getMetrics('24h');

        $errorRate = $metrics['total_requests'] > 0
            ? (($metrics['total_requests'] - $metrics['successful_requests']) / $metrics['total_requests']) * 100
            : 0;

        return [
            'status' => $errorRate < 5 ? 'healthy' : ($errorRate < 15 ? 'degraded' : 'unhealthy'),
            'uptime' => $metrics['uptime_percentage'] ?? 100.0,
            'error_rate' => round($errorRate, 2),
            'avg_response_time' => $metrics['avg_response_time_ms'] ?? 0,
            'last_error' => null, // Would need separate query for this
        ];
    }
}

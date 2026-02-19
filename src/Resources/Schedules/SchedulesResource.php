<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Schedules;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Schedules Resource
 *
 * Manage scheduled agent tasks - create, list, run, and manage recurring jobs.
 *
 * @example
 * ```php
 * // List all scheduled jobs
 * $jobs = $iris->schedules->list();
 *
 * // List jobs for a specific agent
 * $jobs = $iris->schedules->list(['agent_id' => 11]);
 *
 * // Create a scheduled job
 * $job = $iris->schedules->create([
 *     'agent_id' => 11,
 *     'task_name' => 'Daily report',
 *     'prompt' => 'Generate a daily sales report',
 *     'time' => '09:00',
 *     'frequency' => 'daily',
 * ]);
 *
 * // Run a job immediately
 * $result = $iris->schedules->run($jobId);
 *
 * // Sync agent recurring tasks
 * $result = $iris->schedules->syncAgent($agentId);
 * ```
 */
class SchedulesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all scheduled jobs for the current user.
     *
     * @param array{
     *     agent_id?: int,
     *     status?: string,
     *     agent_jobs_only?: bool
     * } $options Filter options
     * @return array
     */
    public function list(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/scheduled-jobs", $options);

        return $response['data'] ?? $response;
    }

    /**
     * List scheduled jobs for a specific agent.
     *
     * @param int|string $agentId Agent ID
     * @return array
     */
    public function forAgent(int|string $agentId): array
    {
        return $this->list(['agent_id' => $agentId]);
    }

    /**
     * Get a specific scheduled job.
     *
     * @param int|string $jobId Job ID
     * @return array
     */
    public function get(int|string $jobId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/scheduled-jobs/{$jobId}");

        return $response;
    }

    /**
     * Create a new scheduled job for an agent.
     *
     * @param array{
     *     agent_id: int,
     *     task_name: string,
     *     prompt?: string,
     *     time: string,
     *     frequency: string,
     *     timezone?: string
     * } $data Job configuration
     * @return array
     */
    public function create(array $data): array
    {
        $userId = $this->config->requireUserId();

        // Validate required fields
        if (!isset($data['agent_id'])) {
            throw new \InvalidArgumentException('agent_id is required');
        }
        if (!isset($data['task_name'])) {
            throw new \InvalidArgumentException('task_name is required');
        }
        if (!isset($data['time'])) {
            throw new \InvalidArgumentException('time is required (HH:MM format)');
        }
        if (!isset($data['frequency'])) {
            throw new \InvalidArgumentException('frequency is required (daily, weekly, monthly, once)');
        }

        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/scheduled-jobs", $data);

        return $response['data'] ?? $response;
    }

    /**
     * Update a scheduled job.
     *
     * @param int|string $jobId Job ID
     * @param array $data Update data
     * @return array
     */
    public function update(int|string $jobId, array $data): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->put("/api/v1/users/{$userId}/bloqs/scheduled-jobs/{$jobId}", $data);

        return $response;
    }

    /**
     * Delete a scheduled job.
     *
     * @param int|string $jobId Job ID
     * @return array
     */
    public function delete(int|string $jobId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->delete("/api/v1/users/{$userId}/bloqs/scheduled-jobs/{$jobId}");

        return $response;
    }

    /**
     * Run a scheduled job immediately.
     *
     * @param int|string $jobId Job ID
     * @return array
     */
    public function run(int|string $jobId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/scheduled-jobs/{$jobId}/run", []);

        return $response;
    }

    /**
     * Reset a stuck job back to 'scheduled' status.
     * Useful for jobs stuck in 'running' status.
     *
     * @param int|string $jobId Job ID
     * @return array
     */
    public function reset(int|string $jobId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/scheduled-jobs/{$jobId}/reset", []);

        return $response;
    }

    /**
     * Reset all stuck jobs (status=running with next_run_at in the past).
     *
     * @return array
     */
    public function resetAll(): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/scheduled-jobs/reset-all", []);

        return $response;
    }

    /**
     * Sync recurring tasks from an agent's schedule configuration.
     *
     * @param int|string $agentId Agent ID
     * @return array
     */
    public function syncAgent(int|string $agentId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/agents/{$agentId}/sync-schedule", []);

        return $response;
    }

    /**
     * Get pending jobs (status = scheduled).
     *
     * @return array
     */
    public function pending(): array
    {
        return $this->list(['status' => 'scheduled']);
    }

    /**
     * Get completed jobs.
     *
     * @return array
     */
    public function completed(): array
    {
        return $this->list(['status' => 'completed']);
    }

    /**
     * Get failed jobs.
     *
     * @return array
     */
    public function failed(): array
    {
        return $this->list(['status' => 'failed']);
    }

    /**
     * Get execution history for a specific scheduled job.
     *
     * @param int|string $jobId Job ID
     * @param array{
     *     status?: string,
     *     limit?: int
     * } $options Filter options
     * @return array
     */
    public function executions(int|string $jobId, array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/scheduled-jobs/{$jobId}/executions", $options);

        return $response;
    }

    /**
     * Get all execution history for a specific agent.
     *
     * @param int|string $agentId Agent ID
     * @param array{
     *     status?: string,
     *     limit?: int,
     *     from?: string,
     *     to?: string
     * } $options Filter options
     * @return array
     */
    public function agentExecutions(int|string $agentId, array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/agents/{$agentId}/executions", $options);

        return $response;
    }

    /**
     * Get all executions across all agents for the user.
     *
     * @param array{
     *     status?: string,
     *     limit?: int,
     *     from?: string,
     *     to?: string
     * } $options Filter options
     * @return array
     */
    public function allExecutions(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/agents/executions", $options);

        return $response;
    }

    /**
     * Get a single execution with full details.
     *
     * @param int|string $executionId Execution ID
     * @return array
     */
    public function execution(int|string $executionId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/job-executions/{$executionId}");

        return $response['data'] ?? $response;
    }

    /**
     * Rate an execution (good/bad).
     *
     * @param int|string $executionId Execution ID
     * @param string|null $rating 'good', 'bad', or null to clear
     * @param string|null $notes Optional notes
     * @return array
     */
    public function rateExecution(int|string $executionId, ?string $rating, ?string $notes = null): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/agents/executions/{$executionId}/rate", [
            'rating' => $rating,
            'notes' => $notes,
        ]);

        return $response;
    }
}

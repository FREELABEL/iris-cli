<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Automations;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Automations Resource - V6 Goal-Driven Automation System
 *
 * Execute V6 automations where you define WHAT you want (goals + outcomes)
 * rather than HOW to achieve it. The V6 ReAct engine autonomously executes
 * the automation by selecting tools and delivering specified outcomes.
 *
 * This is different from the V6 workflow engine - this is the higher-level
 * automation system that uses the V6 engine under the hood.
 *
 * @example Create and execute a V6 automation
 * ```php
 * // Create automation
 * $automation = $fl->automations->create([
 *     'name' => 'Daily Client Update',
 *     'agent_id' => 55,
 *     'goal' => 'Send daily email update to client about project status',
 *     'outcomes' => [
 *         [
 *             'type' => 'email',
 *             'description' => 'Email sent to client',
 *             'destination' => [
 *                 'to' => 'client@example.com',
 *                 'subject' => 'Daily Update'
 *             ]
 *         ]
 *     ],
 *     'success_criteria' => ['Email delivered successfully']
 * ]);
 *
 * // Execute automation
 * $run = $fl->automations->execute($automation['id']);
 *
 * // Monitor status
 * $status = $fl->automations->status($run['run_id']);
 * echo "Status: {$status['status']} ({$status['progress']}%)\n";
 * ```
 */
class AutomationsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Create a new V6 automation.
     *
     * @param array{
     *     name: string,
     *     agent_id: int,
     *     goal: string,
     *     outcomes: array,
     *     success_criteria?: array,
     *     max_iterations?: int,
     *     description?: string
     * } $params Automation parameters
     * @return array Created automation data
     *
     * @example
     * ```php
     * $automation = $iris->automations->create([
     *     'name' => 'Email Campaign',
     *     'agent_id' => 55,
     *     'goal' => 'Send personalized emails to leads',
     *     'outcomes' => [
     *         ['type' => 'email', 'description' => 'Emails sent']
     *     ]
     * ]);
     * ```
     */
    public function create(array $params): array
    {
        $userId = $this->config->requireUserId();

        // Build workflow template for V6 agentic execution
        $data = [
            'user_id' => $userId,
            'name' => $params['name'],
            'description' => $params['description'] ?? "V6 Automation: {$params['name']}",
            'execution_mode' => 'agentic_v6',
            'agent_id' => $params['agent_id'],
            'agent_config' => [
                'goal' => $params['goal'],
                'outcomes' => $params['outcomes'],
                'successCriteria' => $params['success_criteria'] ?? [],
                'maxIterations' => $params['max_iterations'] ?? 10,
            ],
        ];

        $response = $this->http->post('/api/v1/workflows/templates', $data);

        return $response['data'] ?? $response;
    }

    /**
     * Execute a V6 automation.
     *
     * @param int $automationId Automation (workflow template) ID
     * @param array $inputs Optional input data for the automation
     * @return array Execution result with run_id and status
     *
     * @example
     * ```php
     * $run = $iris->automations->execute(16, [
     *     'recipient' => 'john@example.com'
     * ]);
     * echo "Run ID: {$run['run_id']}\n";
     * ```
     */
    public function execute(int $automationId, array $inputs = []): array
    {
        $response = $this->http->post("/api/v1/workflows/{$automationId}/execute/v6", [
            'inputs' => $inputs,
        ]);

        return $response['data'] ?? $response;
    }

    /**
     * Get automation run status with real-time progress.
     *
     * @param string $runId Automation run ID (UUID)
     * @return array Run status including progress, results, and outcomes
     *
     * @example
     * ```php
     * $status = $iris->automations->status('c3337ce1-81f0-4785-adb8-a82347c563ae');
     * 
     * echo "Status: {$status['status']}\n";
     * echo "Progress: {$status['progress']}%\n";
     * 
     * if ($status['status'] === 'completed') {
     *     echo "Results: {$status['results']['content']}\n";
     *     echo "Iterations: {$status['results']['iterations']}\n";
     *     print_r($status['results']['outcomes_delivered']);
     * }
     * ```
     */
    public function status(string $runId): array
    {
        $response = $this->http->get("/api/v1/workflows/runs/{$runId}");

        return $response['data'] ?? $response;
    }

    /**
     * List automation runs for the authenticated user.
     *
     * @param array{
     *     automation_id?: int,
     *     status?: string,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return array List of automation runs with pagination
     *
     * @example
     * ```php
     * // Get all runs
     * $runs = $iris->automations->runs();
     * 
     * // Filter by automation
     * $runs = $iris->automations->runs(['automation_id' => 16]);
     * 
     * // Filter by status
     * $runs = $iris->automations->runs(['status' => 'completed']);
     * ```
     */
    public function runs(array $filters = []): array
    {
        $params = [];

        if (isset($filters['automation_id'])) {
            $params['workflow_id'] = $filters['automation_id'];
        }

        if (isset($filters['status'])) {
            $params['status'] = $filters['status'];
        }

        if (isset($filters['page'])) {
            $params['page'] = $filters['page'];
        }

        if (isset($filters['per_page'])) {
            $params['per_page'] = $filters['per_page'];
        }

        $response = $this->http->get('/api/v1/workflows/runs', $params);

        return $response;
    }

    /**
     * Get a specific automation by ID.
     *
     * @param int $automationId Automation ID
     * @return array Automation details
     */
    public function get(int $automationId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/workflows/{$automationId}");

        return $response['data'] ?? $response;
    }

    /**
     * List all automations for the authenticated user.
     *
     * @param array{
     *     agent_id?: int,
     *     status?: string,
     *     page?: int
     * } $filters Filter options
     * @return array List of automations
     */
    public function list(array $filters = []): array
    {
        $userId = $this->config->requireUserId();
        
        $params = [
            'execution_mode' => 'agentic_v6', // Only get V6 automations
        ];

        if (isset($filters['agent_id'])) {
            $params['agent_id'] = $filters['agent_id'];
        }

        if (isset($filters['page'])) {
            $params['page'] = $filters['page'];
        }

        $response = $this->http->get("/api/v1/users/{$userId}/workflows", $params);

        return $response;
    }

    /**
     * Update an automation.
     *
     * @param int $automationId Automation ID
     * @param array $updates Fields to update
     * @return array Updated automation data
     */
    public function update(int $automationId, array $updates): array
    {
        $response = $this->http->put("/api/v1/workflows/{$automationId}", $updates);

        return $response['data'] ?? $response;
    }

    /**
     * Delete an automation.
     *
     * @param int $automationId Automation ID
     * @return bool Success status
     */
    public function delete(int $automationId): bool
    {
        $this->http->delete("/api/v1/workflows/{$automationId}");
        return true;
    }

    /**
     * Cancel a running automation.
     *
     * @param string $runId Automation run ID
     * @return bool Success status
     */
    public function cancel(string $runId): bool
    {
        $this->http->post("/api/v1/workflows/runs/{$runId}/cancel");
        return true;
    }

    /**
     * Poll automation status until completion (with timeout).
     *
     * @param string $runId Automation run ID
     * @param int $timeoutSeconds Maximum time to wait (default: 300 = 5 minutes)
     * @param int $intervalSeconds Polling interval (default: 2 seconds)
     * @param callable|null $onProgress Callback for progress updates: function($status) {}
     * @return array Final status
     * @throws \RuntimeException If timeout is reached
     *
     * @example
     * ```php
     * $status = $iris->automations->waitForCompletion(
     *     $runId,
     *     timeoutSeconds: 60,
     *     onProgress: function($status) {
     *         echo "Progress: {$status['progress']}% - {$status['status']}\n";
     *     }
     * );
     * ```
     */
    public function waitForCompletion(
        string $runId,
        int $timeoutSeconds = 300,
        int $intervalSeconds = 2,
        ?callable $onProgress = null
    ): array {
        $startTime = time();

        while (true) {
            $status = $this->status($runId);

            // Call progress callback if provided
            if ($onProgress) {
                $onProgress($status);
            }

            // Check if completed
            if (in_array($status['status'], ['completed', 'failed', 'cancelled'])) {
                return $status;
            }

            // Check timeout
            if ((time() - $startTime) >= $timeoutSeconds) {
                throw new \RuntimeException(
                    "Automation run timeout after {$timeoutSeconds} seconds. " .
                    "Current status: {$status['status']}"
                );
            }

            // Wait before next poll
            sleep($intervalSeconds);
        }
    }

    /**
     * Get outcomes delivered by an automation run.
     *
     * @param string $runId Automation run ID
     * @return array List of delivered outcomes
     */
    public function getOutcomes(string $runId): array
    {
        $status = $this->status($runId);

        return $status['results']['outcomes_delivered'] ?? [];
    }

    /**
     * Validate automation configuration before creation.
     *
     * @param array $config Automation configuration
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Required fields
        if (empty($config['name'])) {
            $errors[] = 'name is required';
        }

        if (empty($config['agent_id'])) {
            $errors[] = 'agent_id is required';
        }

        if (empty($config['goal'])) {
            $errors[] = 'goal is required';
        }

        if (empty($config['outcomes']) || !is_array($config['outcomes'])) {
            $errors[] = 'outcomes array is required';
        }

        // Validate outcomes structure
        if (!empty($config['outcomes'])) {
            foreach ($config['outcomes'] as $i => $outcome) {
                if (empty($outcome['type'])) {
                    $errors[] = "outcomes[{$i}].type is required";
                }

                if (empty($outcome['description'])) {
                    $errors[] = "outcomes[{$i}].description is required";
                }

                // Type-specific validation
                if (($outcome['type'] ?? '') === 'email') {
                    if (empty($outcome['destination']['to'])) {
                        $errors[] = "outcomes[{$i}].destination.to is required for email type";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}

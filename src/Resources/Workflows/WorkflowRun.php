<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;
use IRIS\SDK\Exceptions\WorkflowException;
use Generator;

/**
 * Workflow Run
 *
 * Represents an executing workflow with progress tracking
 * and human-in-the-loop support.
 *
 * @example
 * ```php
 * $workflow = $fl->workflows->execute([...]);
 *
 * // Stream progress
 * foreach ($workflow->steps() as $step) {
 *     echo "[{$step->progress}%] {$step->description}\n";
 * }
 *
 * // Handle human input if needed
 * if ($workflow->needsHumanInput()) {
 *     $workflow->provideInput(['approved' => true]);
 * }
 *
 * // Get result
 * echo $workflow->result()->content;
 * ```
 */
class WorkflowRun
{
    /**
     * Run ID (UUID).
     */
    public string $id;

    /**
     * Current status: 'pending', 'running', 'completed', 'awaiting_human', 'failed'
     */
    public string $status;

    /**
     * Progress percentage (0-100).
     */
    public int $progress;

    /**
     * Current step being executed.
     */
    public ?string $currentStep;

    /**
     * Completed step records.
     */
    public array $stepRecords = [];

    /**
     * Final result (when completed).
     */
    public ?array $result = null;

    /**
     * Pending human task (if any).
     */
    public ?HumanTask $pendingTask = null;

    /**
     * Workflow ID (if workflow-based).
     */
    public ?int $workflowId;

    /**
     * Agent ID (if agent-based).
     */
    public ?int $agentId;

    /**
     * Context/variables.
     */
    public array $context = [];

    /**
     * HTTP client for polling.
     */
    protected Client $http;

    /**
     * SDK configuration.
     */
    protected Config $config;

    /**
     * Yielded step IDs for deduplication.
     */
    protected array $yieldedSteps = [];

    /**
     * Raw attributes.
     */
    protected array $attributes;

    public function __construct(array $data, Client $http, Config $config)
    {
        $this->attributes = $data;
        $this->http = $http;
        $this->config = $config;

        $this->id = $data['id'] ?? $data['run_id'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->progress = (int) ($data['progress'] ?? 0);
        $this->currentStep = $data['current_step'] ?? null;
        $this->stepRecords = $data['step_records'] ?? [];
        $this->result = $data['result'] ?? $data['results'] ?? null;
        $this->workflowId = $data['workflow_id'] ?? null;
        $this->agentId = $data['agent_id'] ?? null;
        $this->context = $data['context'] ?? [];

        if (isset($data['human_task']) || isset($data['pending_task'])) {
            $this->pendingTask = new HumanTask($data['human_task'] ?? $data['pending_task']);
        }
    }

    /**
     * Poll for step updates (generator).
     *
     * Yields WorkflowStep objects as they complete.
     * Automatically handles polling and deduplication.
     *
     * @return Generator<WorkflowStep>
     * @throws WorkflowException If workflow fails
     */
    public function steps(): Generator
    {
        $startTime = time();
        $maxDuration = $this->config->maxPollingDuration;
        $interval = $this->config->pollingInterval * 1000; // Convert to microseconds

        while ($this->status === 'running' || $this->status === 'pending') {
            // Check timeout
            if ((time() - $startTime) > $maxDuration) {
                throw new WorkflowException("Workflow polling timeout after {$maxDuration} seconds")
                    ->withWorkflowRunId($this->id);
            }

            // Fetch latest status
            $status = $this->fetchStatus();

            // Yield new steps
            foreach ($status->stepRecords as $step) {
                $stepKey = $step['id'] ?? "{$step['step_number']}_{$step['status']}";

                if (!isset($this->yieldedSteps[$stepKey])) {
                    $this->yieldedSteps[$stepKey] = true;
                    yield new WorkflowStep($step);
                }
            }

            // Update internal state
            $this->updateFromStatus($status);

            // Check terminal states
            if ($this->status === 'awaiting_human') {
                return;
            }

            if ($this->status === 'completed') {
                return;
            }

            if ($this->status === 'failed') {
                throw new WorkflowException($status->error ?? 'Workflow execution failed')
                    ->withWorkflowRunId($this->id);
            }

            // Wait before next poll
            usleep($interval);
        }
    }

    /**
     * Get the final result (blocks until complete).
     *
     * @return WorkflowResult
     * @throws WorkflowException If workflow fails
     */
    public function result(): WorkflowResult
    {
        // Exhaust the steps generator
        iterator_to_array($this->steps());

        // Handle human input requirement
        if ($this->status === 'awaiting_human') {
            throw new WorkflowException('Workflow requires human input before completing')
                ->withWorkflowRunId($this->id);
        }

        return new WorkflowResult($this->result ?? []);
    }

    /**
     * Check if human input is required.
     */
    public function needsHumanInput(): bool
    {
        return $this->status === 'awaiting_human' && $this->pendingTask !== null;
    }

    /**
     * Provide human input and continue the workflow.
     *
     * @param array{
     *     approved?: bool,
     *     feedback?: string,
     *     data?: array
     * } $input Human input
     * @return self
     */
    public function provideInput(array $input): self
    {
        if (!$this->needsHumanInput()) {
            throw new WorkflowException('No human input required at this time')
                ->withWorkflowRunId($this->id);
        }

        // Complete the task
        if ($this->pendingTask) {
            $this->http->post(
                "/api/v1/bloqs/workflow-human-tasks/{$this->pendingTask->id}/complete",
                $input
            );
        }

        // Continue the workflow
        $response = $this->http->post(
            "/api/v1/bloqs/workflow-runs/{$this->id}/continue",
            ['input' => $input]
        );

        // Update state
        $this->updateFromResponse($response);
        $this->pendingTask = null;

        return $this;
    }

    /**
     * Approve and continue (shorthand).
     *
     * @param string|null $feedback Optional feedback
     * @return self
     */
    public function approve(?string $feedback = null): self
    {
        return $this->provideInput([
            'approved' => true,
            'feedback' => $feedback,
        ]);
    }

    /**
     * Reject and stop workflow.
     *
     * @param string|null $feedback Rejection reason
     * @return self
     */
    public function reject(?string $feedback = null): self
    {
        return $this->provideInput([
            'approved' => false,
            'feedback' => $feedback,
        ]);
    }

    /**
     * Get current workflow status.
     */
    public function getStatus(): WorkflowStatus
    {
        return $this->fetchStatus();
    }

    /**
     * Refresh the workflow run state.
     */
    public function refresh(): self
    {
        $status = $this->fetchStatus();
        $this->updateFromStatus($status);
        return $this;
    }

    /**
     * Check if workflow is still running.
     */
    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }

    /**
     * Check if workflow completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if workflow failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get execution logs.
     */
    public function getLogs(): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/bloqs/workflow-runs/{$this->id}/logs");
    }

    /**
     * Fetch current status from API.
     */
    protected function fetchStatus(): WorkflowStatus
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/workflow-runs/{$this->id}");
        return new WorkflowStatus($response);
    }

    /**
     * Update internal state from status response.
     */
    protected function updateFromStatus(WorkflowStatus $status): void
    {
        $this->status = $status->status;
        $this->progress = $status->progress;
        $this->currentStep = $status->currentStep;
        $this->stepRecords = $status->stepRecords;
        $this->result = $status->result;
        $this->context = $status->context;

        if ($status->humanTask) {
            $this->pendingTask = $status->humanTask;
        }
    }

    /**
     * Update internal state from raw response.
     */
    protected function updateFromResponse(array $response): void
    {
        $this->status = $response['status'] ?? $this->status;
        $this->progress = (int) ($response['progress'] ?? $this->progress);
        $this->currentStep = $response['current_step'] ?? $this->currentStep;
        $this->stepRecords = $response['step_records'] ?? $this->stepRecords;
        $this->result = $response['result'] ?? $this->result;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'progress' => $this->progress,
            'current_step' => $this->currentStep,
            'step_records' => $this->stepRecords,
            'result' => $this->result,
            'workflow_id' => $this->workflowId,
            'agent_id' => $this->agentId,
        ];
    }
}

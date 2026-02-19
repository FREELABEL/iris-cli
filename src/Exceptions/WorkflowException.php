<?php

declare(strict_types=1);

namespace IRIS\SDK\Exceptions;

/**
 * Exception for workflow execution failures.
 */
class WorkflowException extends IRISException
{
    /**
     * The step that failed.
     */
    public ?string $stepName = null;

    /**
     * The step parameters when failure occurred.
     */
    public array $stepParams = [];

    /**
     * The workflow run ID.
     */
    public ?string $workflowRunId = null;

    /**
     * The step index that failed.
     */
    public ?int $stepIndex = null;

    /**
     * Create a new workflow exception.
     */
    public function __construct(
        string $message = 'Workflow execution failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set step context for the failure.
     */
    public function withStep(string $stepName, array $stepParams = [], ?int $stepIndex = null): self
    {
        $this->stepName = $stepName;
        $this->stepParams = $stepParams;
        $this->stepIndex = $stepIndex;
        return $this;
    }

    /**
     * Set the workflow run ID.
     */
    public function withWorkflowRunId(string $runId): self
    {
        $this->workflowRunId = $runId;
        return $this;
    }

    /**
     * Get detailed context for debugging.
     */
    public function getContext(): array
    {
        return [
            'workflow_run_id' => $this->workflowRunId,
            'step_name' => $this->stepName,
            'step_index' => $this->stepIndex,
            'step_params' => $this->stepParams,
            'message' => $this->getMessage(),
        ];
    }
}

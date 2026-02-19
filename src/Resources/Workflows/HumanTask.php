<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

/**
 * Human Task
 *
 * Represents a pending human-in-the-loop task that requires user approval.
 */
class HumanTask
{
    /**
     * Task ID.
     */
    public string $id;

    /**
     * Task description.
     */
    public string $description;

    /**
     * Step name/index that requires approval.
     */
    public string $stepName;

    /**
     * Step index.
     */
    public int $stepIndex;

    /**
     * Step parameters for context.
     */
    public array $stepParams;

    /**
     * Task status.
     */
    public string $status;

    /**
     * Workflow run ID.
     */
    public ?string $workflowRunId;

    /**
     * Expected input schema.
     */
    public array $inputSchema;

    /**
     * Created timestamp.
     */
    public ?string $createdAt;

    /**
     * Raw attributes.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = $data['id'] ?? '';
        $this->description = $data['description'] ?? $data['message'] ?? '';
        $this->stepName = $data['step_name'] ?? '';
        $this->stepIndex = (int) ($data['step_index'] ?? 0);
        $this->stepParams = $data['step_params'] ?? $data['params'] ?? [];
        $this->status = $data['status'] ?? 'pending';
        $this->workflowRunId = $data['workflow_run_id'] ?? null;
        $this->inputSchema = $data['input_schema'] ?? [];
        $this->createdAt = $data['created_at'] ?? null;
    }

    /**
     * Check if task is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if task was approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if task was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Get context for display.
     */
    public function getContext(): array
    {
        return [
            'step_name' => $this->stepName,
            'step_index' => $this->stepIndex,
            'params' => $this->stepParams,
            'description' => $this->description,
        ];
    }

    /**
     * Get raw attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'step_name' => $this->stepName,
            'step_index' => $this->stepIndex,
            'step_params' => $this->stepParams,
            'status' => $this->status,
            'workflow_run_id' => $this->workflowRunId,
        ];
    }
}

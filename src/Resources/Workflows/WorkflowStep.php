<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

/**
 * Workflow Step
 *
 * Represents a single step in a workflow execution.
 */
class WorkflowStep
{
    /**
     * Step ID.
     */
    public ?string $id;

    /**
     * Step number (1-indexed).
     */
    public int $number;

    /**
     * Step name/tool.
     */
    public string $name;

    /**
     * Human-readable description.
     */
    public string $description;

    /**
     * Step status: 'pending', 'executing', 'completed', 'failed', 'skipped'
     */
    public string $status;

    /**
     * Progress percentage (0-100).
     */
    public int $progress;

    /**
     * Step parameters/inputs.
     */
    public array $params;

    /**
     * Step result (when completed).
     */
    public mixed $result;

    /**
     * Error message (if failed).
     */
    public ?string $error;

    /**
     * Whether this step requires approval.
     */
    public bool $requiresApproval;

    /**
     * Execution duration in milliseconds.
     */
    public ?int $duration;

    /**
     * Timestamp when step started.
     */
    public ?string $startedAt;

    /**
     * Timestamp when step completed.
     */
    public ?string $completedAt;

    /**
     * Raw attributes.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = $data['id'] ?? null;
        $this->number = (int) ($data['step_number'] ?? $data['number'] ?? 0);
        $this->name = $data['name'] ?? $data['tool'] ?? $data['step_name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->progress = (int) ($data['progress'] ?? 0);
        $this->params = $data['params'] ?? $data['parameters'] ?? [];
        $this->result = $data['result'] ?? null;
        $this->error = $data['error'] ?? null;
        $this->requiresApproval = (bool) ($data['requires_approval'] ?? false);
        $this->duration = $data['duration'] ?? null;
        $this->startedAt = $data['started_at'] ?? null;
        $this->completedAt = $data['completed_at'] ?? null;
    }

    /**
     * Check if step is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if step failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if step is currently executing.
     */
    public function isExecuting(): bool
    {
        return $this->status === 'executing';
    }

    /**
     * Check if step is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if step has a result.
     */
    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    /**
     * Get result as string.
     */
    public function getResultString(): string
    {
        if ($this->result === null) {
            return '';
        }

        if (is_string($this->result)) {
            return $this->result;
        }

        return json_encode($this->result, JSON_PRETTY_PRINT);
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
            'number' => $this->number,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'progress' => $this->progress,
            'params' => $this->params,
            'result' => $this->result,
            'error' => $this->error,
            'requires_approval' => $this->requiresApproval,
            'duration' => $this->duration,
        ];
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return "[Step {$this->number}] {$this->name}: {$this->description} ({$this->status})";
    }
}

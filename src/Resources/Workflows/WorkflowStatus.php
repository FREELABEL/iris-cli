<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

/**
 * Workflow Status
 *
 * Current status of a workflow run.
 */
class WorkflowStatus
{
    /**
     * Run ID.
     */
    public string $id;

    /**
     * Current status.
     */
    public string $status;

    /**
     * Progress percentage.
     */
    public int $progress;

    /**
     * Current step name.
     */
    public ?string $currentStep;

    /**
     * Total number of steps.
     */
    public int $totalSteps;

    /**
     * Current step index.
     */
    public int $currentStepIndex;

    /**
     * Step records.
     */
    public array $stepRecords;

    /**
     * Final result.
     */
    public ?array $result;

    /**
     * Pending human task.
     */
    public ?HumanTask $humanTask;

    /**
     * Context/variables.
     */
    public array $context;

    /**
     * Error message if failed.
     */
    public ?string $error;

    /**
     * Created timestamp.
     */
    public ?string $createdAt;

    /**
     * Updated timestamp.
     */
    public ?string $updatedAt;

    /**
     * Raw attributes.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = $data['id'] ?? $data['run_id'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->progress = (int) ($data['progress'] ?? 0);
        $this->currentStep = $data['current_step'] ?? null;
        $this->totalSteps = (int) ($data['total_steps'] ?? count($data['step_records'] ?? []));
        $this->currentStepIndex = (int) ($data['current_step_index'] ?? 0);
        $this->stepRecords = $data['step_records'] ?? [];
        $this->result = $data['result'] ?? $data['results'] ?? null;
        $this->context = $data['context'] ?? [];
        $this->error = $data['error'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;

        if (isset($data['human_task']) || isset($data['pending_task'])) {
            $this->humanTask = new HumanTask($data['human_task'] ?? $data['pending_task']);
        } else {
            $this->humanTask = null;
        }
    }

    /**
     * Check if workflow is in terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    /**
     * Check if workflow requires human input.
     */
    public function needsHumanInput(): bool
    {
        return $this->status === 'awaiting_human' && $this->humanTask !== null;
    }

    /**
     * Get completed steps count.
     */
    public function getCompletedStepsCount(): int
    {
        return count(array_filter(
            $this->stepRecords,
            fn($step) => ($step['status'] ?? '') === 'completed'
        ));
    }

    /**
     * Get current step as WorkflowStep.
     */
    public function getCurrentStepObject(): ?WorkflowStep
    {
        foreach ($this->stepRecords as $step) {
            if (($step['status'] ?? '') === 'executing') {
                return new WorkflowStep($step);
            }
        }
        return null;
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
            'total_steps' => $this->totalSteps,
            'current_step_index' => $this->currentStepIndex,
            'step_records' => $this->stepRecords,
            'result' => $this->result,
            'error' => $this->error,
        ];
    }
}

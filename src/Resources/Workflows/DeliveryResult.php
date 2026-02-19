<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

/**
 * Delivery Result
 *
 * Represents the result of a workflow delivery to a lead.
 * Contains all information about the executed workflow, created deliverable,
 * and email notification.
 *
 * @example
 * ```php
 * $result = $iris->workflows->deliverToLead(522, 'newsletter-generator', [...]);
 *
 * if ($result->success) {
 *     echo "Delivered to lead #{$result->leadId}\n";
 *     echo "Workflow: {$result->workflowName}\n";
 *     echo "Deliverable: {$result->deliverableUrl}\n";
 *     echo "Email sent to: " . implode(', ', $result->emailSentTo) . "\n";
 *     echo "Time to value: {$result->timeToValueSeconds}s\n";
 * }
 * ```
 */
class DeliveryResult
{
    /**
     * Whether the delivery was successful.
     */
    public bool $success;

    /**
     * The lead ID that received the delivery.
     */
    public int $leadId;

    /**
     * The callable workflow name.
     */
    public string $callableName;

    /**
     * The workflow ID (if available).
     */
    public ?int $workflowId;

    /**
     * The workflow name (human readable).
     */
    public string $workflowName;

    /**
     * The workflow execution ID.
     */
    public ?string $executionId;

    /**
     * The created deliverable ID.
     */
    public ?int $deliverableId;

    /**
     * The URL of the deliverable (landing page or result page).
     */
    public string $deliverableUrl;

    /**
     * The title of the deliverable.
     */
    public string $deliverableTitle;

    /**
     * The raw workflow output/result.
     */
    public mixed $workflowOutput;

    /**
     * Whether an email was sent.
     */
    public bool $emailSent;

    /**
     * List of email addresses the notification was sent to.
     */
    public array $emailSentTo;

    /**
     * Raw email result (for debugging).
     */
    public ?array $emailResult;

    /**
     * Time to value in seconds (total time from start to delivery complete).
     */
    public float $timeToValueSeconds;

    /**
     * Raw attributes.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->success = $data['success'] ?? false;
        $this->leadId = $data['lead_id'] ?? 0;
        $this->callableName = $data['callable_name'] ?? '';
        $this->workflowId = $data['workflow_id'] ?? null;
        $this->workflowName = $data['workflow_name'] ?? $this->callableName;
        $this->executionId = $data['execution_id'] ?? null;
        $this->deliverableId = $data['deliverable_id'] ?? null;
        $this->deliverableUrl = $data['deliverable_url'] ?? '';
        $this->deliverableTitle = $data['deliverable_title'] ?? '';
        $this->workflowOutput = $data['workflow_output'] ?? null;
        $this->emailSent = $data['email_sent'] ?? false;
        $this->emailSentTo = $data['email_sent_to'] ?? [];
        $this->emailResult = $data['email_result'] ?? null;
        $this->timeToValueSeconds = $data['time_to_value_seconds'] ?? 0.0;
    }

    /**
     * Check if delivery was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if email notification was sent.
     */
    public function wasEmailSent(): bool
    {
        return $this->emailSent && !empty($this->emailSentTo);
    }

    /**
     * Get a summary string of the delivery.
     */
    public function getSummary(): string
    {
        $parts = [];

        $parts[] = $this->success ? 'SUCCESS' : 'FAILED';
        $parts[] = "Workflow: {$this->workflowName}";
        $parts[] = "Lead: #{$this->leadId}";

        if ($this->deliverableId) {
            $parts[] = "Deliverable: #{$this->deliverableId}";
        }

        if ($this->emailSent) {
            $parts[] = "Email sent to: " . implode(', ', $this->emailSentTo);
        }

        $parts[] = "Time: {$this->timeToValueSeconds}s";

        return implode(' | ', $parts);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'lead_id' => $this->leadId,
            'callable_name' => $this->callableName,
            'workflow_id' => $this->workflowId,
            'workflow_name' => $this->workflowName,
            'execution_id' => $this->executionId,
            'deliverable_id' => $this->deliverableId,
            'deliverable_url' => $this->deliverableUrl,
            'deliverable_title' => $this->deliverableTitle,
            'workflow_output' => $this->workflowOutput,
            'email_sent' => $this->emailSent,
            'email_sent_to' => $this->emailSentTo,
            'time_to_value_seconds' => $this->timeToValueSeconds,
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * Get raw attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}

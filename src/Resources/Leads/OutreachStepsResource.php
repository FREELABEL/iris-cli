<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Outreach Steps Resource
 *
 * Manage the outreach checklist/strategy for a lead.
 * Steps represent a sequence of outreach actions (email, phone, LinkedIn, etc.)
 * that should be taken to engage with a lead.
 *
 * @example
 * ```php
 * // List all steps for a lead
 * $result = $iris->leads->outreachSteps(123)->list();
 * foreach ($result['data']['steps'] as $step) {
 *     echo "{$step['order']}. [{$step['type']}] {$step['title']}\n";
 * }
 *
 * // Create a new step
 * $step = $iris->leads->outreachSteps(123)->create([
 *     'title' => 'Initial Email',
 *     'type' => 'email',
 *     'instructions' => 'Send introduction email with portfolio',
 * ]);
 *
 * // Mark step as completed
 * $iris->leads->outreachSteps(123)->update($stepId, ['is_completed' => true]);
 * ```
 */
class OutreachStepsResource
{
    protected Client $http;
    protected Config $config;
    protected int $leadId;

    /**
     * Available outreach step types.
     */
    public const TYPES = [
        'email',
        'phone',
        'sms',
        'visit',
        'linkedin',
        'social',
        'mail',
        'other',
    ];

    public function __construct(Client $http, Config $config, int $leadId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->leadId = $leadId;
    }

    /**
     * List all outreach steps for the lead.
     *
     * @return array Steps with stats (total, completed, pending, progress_percent)
     *
     * @example
     * ```php
     * $result = $outreachSteps->list();
     *
     * echo "Progress: {$result['data']['stats']['progress_percent']}%\n";
     * echo "Completed: {$result['data']['stats']['completed']}/{$result['data']['stats']['total']}\n";
     *
     * foreach ($result['data']['steps'] as $step) {
     *     $status = $step['is_completed'] ? '✓' : '○';
     *     echo "{$status} {$step['title']} ({$step['type']})\n";
     * }
     * ```
     */
    public function list(): array
    {
        return $this->http->get("/api/v1/leads/{$this->leadId}/outreach-steps");
    }

    /**
     * Alias for list() - matches CLI convention.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->list();
    }

    /**
     * Create a new outreach step.
     *
     * @param array{
     *     title: string,
     *     type: string,
     *     instructions?: string,
     *     due_date?: string,
     *     order?: int
     * } $data Step data
     * @return array Created step
     *
     * @example
     * ```php
     * // Simple step
     * $step = $outreachSteps->create([
     *     'title' => 'Send follow-up email',
     *     'type' => 'email',
     * ]);
     *
     * // Detailed step
     * $step = $outreachSteps->create([
     *     'title' => 'Discovery call',
     *     'type' => 'phone',
     *     'instructions' => 'Discuss project requirements and timeline',
     *     'due_date' => '2025-01-15',
     * ]);
     * ```
     */
    public function create(array $data): array
    {
        return $this->http->post("/api/v1/leads/{$this->leadId}/outreach-steps", $data);
    }

    /**
     * Update an outreach step.
     *
     * @param int $stepId Step ID
     * @param array{
     *     title?: string,
     *     type?: string,
     *     instructions?: string,
     *     due_date?: string,
     *     is_completed?: bool,
     *     notes?: string,
     *     order?: int
     * } $data Update data
     * @return array Updated step
     *
     * @example
     * ```php
     * // Mark as completed
     * $outreachSteps->update(5, ['is_completed' => true]);
     *
     * // Update with notes
     * $outreachSteps->update(5, [
     *     'is_completed' => true,
     *     'notes' => 'Left voicemail, will try again tomorrow',
     * ]);
     * ```
     */
    public function update(int $stepId, array $data): array
    {
        return $this->http->put("/api/v1/leads/{$this->leadId}/outreach-steps/{$stepId}", $data);
    }

    /**
     * Mark a step as completed.
     *
     * Convenience method for update($stepId, ['is_completed' => true]).
     *
     * @param int $stepId Step ID
     * @param string|null $notes Optional completion notes
     * @return array Updated step
     */
    public function complete(int $stepId, ?string $notes = null): array
    {
        $data = ['is_completed' => true];
        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($stepId, $data);
    }

    /**
     * Mark a step as incomplete (reopen).
     *
     * @param int $stepId Step ID
     * @return array Updated step
     */
    public function reopen(int $stepId): array
    {
        return $this->update($stepId, ['is_completed' => false]);
    }

    /**
     * Delete an outreach step.
     *
     * @param int $stepId Step ID
     * @return array Success response
     */
    public function delete(int $stepId): array
    {
        return $this->http->delete("/api/v1/leads/{$this->leadId}/outreach-steps/{$stepId}");
    }

    /**
     * Reorder outreach steps.
     *
     * @param array<int> $stepIds Array of step IDs in desired order
     * @return array Success response
     *
     * @example
     * ```php
     * // Move step 5 to first position
     * $outreachSteps->reorder([5, 3, 4, 6, 7]);
     * ```
     */
    public function reorder(array $stepIds): array
    {
        return $this->http->post("/api/v1/leads/{$this->leadId}/outreach-steps/reorder", [
            'step_ids' => $stepIds,
        ]);
    }

    /**
     * Initialize default outreach strategy for the lead.
     *
     * Creates a predefined set of outreach steps based on best practices.
     * Only works if the lead has no existing steps.
     *
     * @return array Created steps
     *
     * @example
     * ```php
     * // Set up default strategy for new lead
     * $result = $outreachSteps->initializeDefault();
     * echo "Created {$result['data']['steps']->count()} steps\n";
     * ```
     */
    public function initializeDefault(): array
    {
        return $this->http->post("/api/v1/leads/{$this->leadId}/outreach-steps/initialize-default", []);
    }

    /**
     * Clear all outreach steps for the lead.
     *
     * @return array Success response
     */
    public function clearAll(): array
    {
        return $this->http->delete("/api/v1/leads/{$this->leadId}/outreach-steps-clear");
    }

    /**
     * Get available step types.
     *
     * @return array<string>
     */
    public function getTypes(): array
    {
        return self::TYPES;
    }
}

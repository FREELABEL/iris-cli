<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Programs;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Programs Resource
 *
 * Manage membership programs, funnels, and enrollments.
 *
 * @example
 * ```php
 * // List all programs
 * $programs = $iris->programs->list(['bloq_id' => 40]);
 *
 * // Create a new program
 * $program = $iris->programs->create([
 *     'name' => 'AI Mastery Course',
 *     'description' => 'Learn AI from scratch',
 *     'bloq_id' => 40,
 *     'has_paid_membership' => true,
 *     'base_price' => 99.00,
 * ]);
 *
 * // Get a specific program
 * $program = $iris->programs->get(123);
 *
 * // Enroll a user
 * $enrollment = $iris->programs->enroll(123, 193);
 *
 * // Attach a workflow to a program
 * $iris->programs->attachWorkflow(123, 45);
 * ```
 */
class ProgramsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all programs with optional filters.
     *
     * @param array{
     *     bloq_id?: int,
     *     active?: bool,
     *     include_inactive?: bool,
     *     has_paid_membership?: bool,
     *     tier?: string,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return ProgramCollection
     */
    public function list(array $filters = []): ProgramCollection
    {
        $response = $this->http->get("/api/programs", $filters);

        return new ProgramCollection(
            array_map(fn($data) => new Program($data), $response['data'] ?? $response['programs'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Search for programs by name or description.
     *
     * @param string $query Search query
     * @param array{
     *     bloq_id?: int,
     *     tier?: string,
     *     active?: bool
     * } $filters Additional filters
     * @return ProgramCollection
     */
    public function search(string $query, array $filters = []): ProgramCollection
    {
        // Get all programs with filters, then search client-side
        // (API doesn't have a dedicated search endpoint)
        $programs = $this->list($filters);
        
        $query = strtolower($query);
        $filtered = array_filter($programs->all(), function(Program $program) use ($query) {
            return str_contains(strtolower($program->name ?? ''), $query)
                || str_contains(strtolower($program->description ?? ''), $query)
                || str_contains(strtolower($program->slug ?? ''), $query);
        });

        return new ProgramCollection(array_values($filtered), [
            'total' => count($filtered),
            'per_page' => count($filtered),
            'current_page' => 1,
            'last_page' => 1,
        ]);
    }

    /**
     * Get a specific program by ID.
     *
     * @param int $programId Program ID
     * @return Program
     */
    public function get(int $programId): Program
    {
        $response = $this->http->get("/api/programs/{$programId}");
        return new Program($response['program'] ?? $response);
    }

    /**
     * Create a new program.
     *
     * @param array{
     *     name: string,
     *     slug?: string,
     *     description?: string,
     *     landing_page_content?: string,
     *     image_url?: string,
     *     active?: bool,
     *     tier?: string,
     *     bloq_id?: int,
     *     mailjet_list_id?: string,
     *     has_paid_membership?: bool,
     *     requires_membership?: bool,
     *     allow_free_enrollment?: bool,
     *     base_price?: float,
     *     membership_features?: array,
     *     custom_fields?: array,
     *     enrollment_form_config?: array
     * } $data Program data
     * @return Program
     */
    public function create(array $data): Program
    {
        $response = $this->http->post("/api/v1/programs", $data);
        return new Program($response);
    }

    /**
     * Update an existing program.
     *
     * @param int $programId Program ID
     * @param array $data Program data to update
     * @return Program
     */
    public function update(int $programId, array $data): Program
    {
        $response = $this->http->put("/api/programs/{$programId}", $data);
        return new Program($response);
    }

    /**
     * Delete a program.
     *
     * @param int $programId Program ID
     * @return bool Success status
     */
    public function delete(int $programId): bool
    {
        $this->http->delete("/api/programs/{$programId}");
        return true;
    }

    /**
     * Enroll a user in a program.
     *
     * @param int $programId Program ID
     * @param int $userId User ID to enroll
     * @param array{
     *     package_id?: int,
     *     custom_fields?: array,
     *     enrollment_data?: array
     * } $data Additional enrollment data
     * @return ProgramEnrollment
     */
    public function enroll(int $programId, int $userId, array $data = []): ProgramEnrollment
    {
        $response = $this->http->post("/api/program-enrollments/enroll", array_merge([
            'program_id' => $programId,
            'user_id' => $userId
        ], $data));

        return new ProgramEnrollment($response);
    }

    /**
     * Cancel a user's enrollment in a program.
     *
     * @param int $programId Program ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function cancelEnrollment(int $programId, int $userId): bool
    {
        $this->http->post("/api/program-enrollments/cancel", [
            'program_id' => $programId,
            'user_id' => $userId
        ]);

        return true;
    }

    /**
     * Get a user's enrollments.
     *
     * @param int $userId User ID
     * @return array List of enrollments
     */
    public function getUserEnrollments(int $userId): array
    {
        return $this->http->get("/v1/program-enrollments/user/{$userId}");
    }

    /**
     * Get a user's programs (programs they're enrolled in).
     *
     * @param int $userId User ID
     * @return array List of programs
     */
    public function getUserPrograms(int $userId): array
    {
        return $this->http->get("/api/user-programs/{$userId}");
    }

    /**
     * Get program content.
     *
     * @param int $programId Program ID
     * @return array Program content items
     */
    public function getContent(int $programId): array
    {
        return $this->http->get("/v1/programs/{$programId}/content");
    }

    /**
     * Attach content to a program.
     *
     * @param int $programId Program ID
     * @param array{
     *     content_id: int,
     *     content_type?: string,
     *     display_order?: int,
     *     is_required?: bool
     * } $data Content attachment data
     * @return array Attachment result
     */
    public function attachContent(int $programId, array $data): array
    {
        return $this->http->post("/v1/programs/{$programId}/content", $data);
    }

    /**
     * Remove content from a program.
     *
     * @param int $programId Program ID
     * @param int $contentId Content ID to remove
     * @return bool Success status
     */
    public function detachContent(int $programId, int $contentId): bool
    {
        $this->http->delete("/v1/programs/{$programId}/content", [
            'content_id' => $contentId
        ]);

        return true;
    }

    /**
     * Get workflows attached to a program.
     *
     * @param int $programId Program ID
     * @return array List of workflows
     */
    public function getWorkflows(int $programId): array
    {
        return $this->http->get("/api/programs/{$programId}/workflows");
    }

    /**
     * Attach a workflow to a program.
     *
     * @param int $programId Program ID
     * @param int $workflowId Workflow ID to attach
     * @param array{
     *     is_required?: bool,
     *     display_order?: int,
     *     enrollment_trigger?: bool
     * } $config Workflow attachment configuration
     * @return array Attachment result
     */
    public function attachWorkflow(int $programId, int $workflowId, array $config = []): array
    {
        return $this->http->post("/api/programs/{$programId}/workflows", array_merge([
            'workflow_id' => $workflowId
        ], $config));
    }

    /**
     * Update workflow attachment configuration.
     *
     * @param int $programId Program ID
     * @param int $workflowId Workflow ID
     * @param array $config Updated configuration
     * @return array Update result
     */
    public function updateWorkflow(int $programId, int $workflowId, array $config): array
    {
        return $this->http->put("/api/programs/{$programId}/workflows", array_merge([
            'workflow_id' => $workflowId
        ], $config));
    }

    /**
     * Detach a workflow from a program.
     *
     * @param int $programId Program ID
     * @param int $workflowId Workflow ID to detach
     * @return bool Success status
     */
    public function detachWorkflow(int $programId, int $workflowId): bool
    {
        $this->http->delete("/api/programs/{$programId}/workflows/{$workflowId}");
        return true;
    }

    /**
     * Get workflow execution logs for a program.
     *
     * @param int $programId Program ID
     * @param array{
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return array Execution logs
     */
    public function getWorkflowExecutionLogs(int $programId, array $filters = []): array
    {
        return $this->http->get("/api/programs/{$programId}/execution-logs", $filters);
    }

    /**
     * Check if a user has access to a program.
     *
     * @param int $programId Program ID
     * @param int $userId User ID
     * @return array Access check result
     */
    public function checkAccess(int $programId, int $userId): array
    {
        return $this->http->get("/v1/programs/{$programId}/check-access", [
            'user_id' => $userId
        ]);
    }

    /**
     * Get program chat messages.
     *
     * @param int $programId Program ID
     * @param array{
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return array Chat messages
     */
    public function getChatMessages(int $programId, array $filters = []): array
    {
        return $this->http->get("/api/programs/{$programId}/chat/messages", $filters);
    }

    /**
     * Send a chat message to a program.
     *
     * @param int $programId Program ID
     * @param array{
     *     message: string,
     *     user_id: int,
     *     metadata?: array
     * } $data Message data
     * @return array Message result
     */
    public function sendChatMessage(int $programId, array $data): array
    {
        return $this->http->post("/api/programs/{$programId}/chat/messages", $data);
    }

    /**
     * Get program chat activity.
     *
     * @param int $programId Program ID
     * @return array Activity data
     */
    public function getChatActivity(int $programId): array
    {
        return $this->http->get("/api/programs/{$programId}/chat/activity");
    }

    /**
     * Get program packages (pricing tiers).
     *
     * @param int $programId Program ID
     * @return array List of packages
     */
    public function getPackages(int $programId): array
    {
        $program = $this->get($programId);
        return $program->packages ?? [];
    }

    /**
     * Get program memberships.
     *
     * @param int $programId Program ID
     * @param array{
     *     active?: bool,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return array List of memberships
     */
    public function getMemberships(int $programId, array $filters = []): array
    {
        return $this->http->get("/api/programs/{$programId}/memberships", $filters);
    }

    /**
     * Get enrollments for a specific program.
     *
     * @param int $programId Program ID
     * @param array{
     *     status?: string,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return array List of enrollments
     */
    public function getEnrollments(int $programId, array $filters = []): array
    {
        return $this->http->get("/api/v1/programs/{$programId}/enrollments", $filters);
    }

    /**
     * Alias for getEnrollments.
     * Useful for form-focused programs where enrollments are "submissions".
     */
    public function getSubmissions(int $programId, array $filters = []): array
    {
        return $this->getEnrollments($programId, $filters);
    }
}

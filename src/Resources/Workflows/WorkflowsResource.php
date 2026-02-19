<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Workflows Resource
 *
 * Execute V5 multi-step workflows with real-time progress tracking
 * and human-in-the-loop support.
 *
 * @example
 * ```php
 * // Execute a workflow
 * $workflow = $fl->workflows->execute([
 *     'agent_id' => 'agent_123',
 *     'query' => 'Research competitors and create a report',
 * ]);
 *
 * // Track progress
 * foreach ($workflow->steps() as $step) {
 *     echo "[{$step->progress}%] {$step->description}\n";
 * }
 *
 * // Get final result
 * $result = $workflow->result();
 * ```
 */
class WorkflowsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Execute a workflow.
     *
     * @param array{
     *     agent_id?: int|string,
     *     workflow_id?: int,
     *     query: string,
     *     bloq_id?: int,
     *     conversation_history?: array,
     *     require_approval?: bool,
     *     variables?: array,
     *     metadata?: array
     * } $params Workflow parameters
     * @return WorkflowRun
     */
    public function execute(array $params): WorkflowRun
    {
        $userId = $this->config->requireUserId();

        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/workflow-runs", [
            'agent_id' => $params['agent_id'] ?? null,
            'workflow_id' => $params['workflow_id'] ?? null,
            'query' => $params['query'],
            'bloq_id' => $params['bloq_id'] ?? null,
            'conversation_history' => $params['conversation_history'] ?? [],
            'require_approval' => $params['require_approval'] ?? false,
            'variables' => $params['variables'] ?? [],
            'metadata' => $params['metadata'] ?? [],
        ]);

        return new WorkflowRun($response, $this->http, $this->config);
    }

    /**
     * Get the status of a workflow run.
     *
     * @param string $runId Workflow run ID
     * @return WorkflowStatus
     */
    public function getStatus(string $runId): WorkflowStatus
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/workflow-runs/{$runId}");

        return new WorkflowStatus($response);
    }

    /**
     * Continue a paused workflow (after human input).
     *
     * @param string $runId Workflow run ID
     * @param array $input Input data to continue with
     * @return WorkflowRun
     */
    public function continue(string $runId, array $input = []): WorkflowRun
    {
        $response = $this->http->post("/api/v1/bloqs/workflow-runs/{$runId}/continue", [
            'input' => $input,
        ]);

        return new WorkflowRun($response, $this->http, $this->config);
    }

    /**
     * Process a specific workflow step.
     *
     * @param string $runId Workflow run ID
     * @param int $stepIndex Step index to process
     * @param array $data Step data
     * @return WorkflowStatus
     */
    public function processStep(string $runId, int $stepIndex, array $data = []): WorkflowStatus
    {
        $response = $this->http->post("/api/v1/bloqs/workflow-runs/{$runId}/process-step", [
            'step_index' => $stepIndex,
            'data' => $data,
        ]);

        return new WorkflowStatus($response);
    }

    /**
     * Complete a human task.
     *
     * @param string $taskId Human task ID
     * @param array{
     *     approved?: bool,
     *     feedback?: string,
     *     data?: array
     * } $response Human response
     * @return bool
     */
    public function completeTask(string $taskId, array $response): bool
    {
        $this->http->post("/api/v1/bloqs/workflow-human-tasks/{$taskId}/complete", $response);
        return true;
    }

    /**
     * Get a human task.
     *
     * @param string $taskId Human task ID
     * @return HumanTask
     */
    public function getTask(string $taskId): HumanTask
    {
        $response = $this->http->get("/api/v1/bloqs/workflow-human-tasks/{$taskId}");
        return new HumanTask($response);
    }

    /**
     * Generate a workflow from natural language description.
     *
     * @param string $description Natural language workflow description
     * @param array{
     *     agent_id?: int,
     *     bloq_id?: int,
     *     template_hints?: array
     * } $options Generation options
     * @return Workflow
     */
    public function generate(string $description, array $options = []): Workflow
    {
        $userId = $this->config->requireUserId();

        $response = $this->http->post("/api/v1/users/{$userId}/bloqs/workflows/generate", [
            'description' => $description,
            'agent_id' => $options['agent_id'] ?? null,
            'bloq_id' => $options['bloq_id'] ?? null,
            'template_hints' => $options['template_hints'] ?? [],
        ]);

        return new Workflow($response);
    }

    /**
     * Generate workflow with multi-agent team.
     *
     * @param string $description Workflow description
     * @param array $options Options
     * @return Workflow
     */
    public function generateWithAgents(string $description, array $options = []): Workflow
    {
        $response = $this->http->post('/api/v1/bloqs/workflows/generate-with-agents', [
            'description' => $description,
            'bloq_id' => $options['bloq_id'] ?? null,
            'agent_count' => $options['agent_count'] ?? 3,
        ]);

        return new Workflow($response);
    }

    /**
     * Generate clarifying questions for workflow.
     *
     * @param string $description Workflow description
     * @return array Questions
     */
    public function generateQuestions(string $description): array
    {
        return $this->http->post('/api/v1/bloqs/workflows/generate-questions', [
            'description' => $description,
        ]);
    }

    /**
     * List workflow templates.
     *
     * @param array{
     *     category?: string,
     *     search?: string,
     *     featured?: bool
     * } $filters Filter options
     * @return TemplateCollection
     */
    public function templates(array $filters = []): TemplateCollection
    {
        $endpoint = '/api/v1/templates';

        if (!empty($filters['featured'])) {
            $endpoint = '/api/v1/templates/featured';
            unset($filters['featured']);
        }

        $response = $this->http->get($endpoint, $filters);

        return new TemplateCollection(
            array_map(fn($data) => new WorkflowTemplate($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Get template categories.
     *
     * @return array Categories
     */
    public function templateCategories(): array
    {
        return $this->http->get('/api/v1/templates/categories');
    }

    /**
     * Import a workflow template.
     *
     * @param string $slug Template slug
     * @param array $variables Template variables
     * @return Workflow
     */
    public function importTemplate(string $slug, array $variables = []): Workflow
    {
        $response = $this->http->post("/api/v1/templates/{$slug}/import", [
            'variables' => $variables,
        ]);

        return new Workflow($response);
    }

    /**
     * List user's workflows.
     *
     * @param array $options List options
     * @return array
     */
    public function list(array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/workflows", $options);

        return $response;
    }

    /**
     * Update a workflow.
     *
     * @param int $workflowId Workflow ID
     * @param array{
     *     name?: string,
     *     description?: string,
     *     bloq_id?: int,
     *     steps?: array,
     *     settings?: array,
     *     agent_id?: int
     * } $data Update data
     * @return array
     *
     * @example
     * ```php
     * // Move workflow to a different bloq
     * $workflow = $iris->workflows->update(8, ['bloq_id' => 203]);
     *
     * // Update workflow name and steps
     * $workflow = $iris->workflows->update(8, [
     *     'name' => 'New Workflow Name',
     *     'steps' => [...],
     * ]);
     * ```
     */
    public function update(int $workflowId, array $data): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->put("/api/v1/users/{$userId}/bloqs/workflows/{$workflowId}", $data);

        return $response;
    }

    /**
     * List workflow runs for a user.
     *
     * @param array{
     *     status?: string,
     *     workflow_id?: int,
     *     page?: int,
     *     per_page?: int
     * } $options Filter options
     * @return WorkflowRunCollection
     */
    public function runs(array $options = []): WorkflowRunCollection
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/workflow-runs", $options);

        return new WorkflowRunCollection(
            array_map(fn($data) => new WorkflowRun($data, $this->http, $this->config), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Get workflow run logs.
     *
     * @param string $runId Workflow run ID
     * @return array Logs
     */
    public function getLogs(string $runId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/bloqs/workflow-runs/{$runId}/logs");
    }

    /**
     * Generate a webhook URL for a workflow.
     *
     * @param int $workflowId Workflow ID
     * @return array{url: string, secret: string}
     */
    public function generateWebhook(int $workflowId): array
    {
        return $this->http->post("/api/v1/bloqs/workflow/{$workflowId}/webhook-url");
    }

    /**
     * Execute a callable workflow and deliver the result to a lead.
     *
     * This is the unified delivery method that chains:
     * 1. Execute the callable workflow
     * 2. Wait for completion
     * 3. Create a deliverable from the result
     * 4. Send a beautiful email notification using AI-powered templates
     *
     * This solves the "last mile" delivery problem by combining all steps
     * into a single, atomic operation.
     *
     * @param int $leadId The lead ID to deliver to
     * @param string $callableName The callable workflow name (e.g., 'newsletter-generator')
     * @param array $input Input data for the workflow
     * @param array{
     *     send_email?: bool,
     *     email_subject?: string,
     *     recipient_emails?: array<string>,
     *     message_mode?: string,
     *     custom_context?: string,
     *     include_project_context?: bool,
     *     deliverable_title?: string
     * } $options Delivery options
     * @return DeliveryResult
     *
     * @example
     * ```php
     * // Execute workflow and deliver to lead in one call
     * $result = $iris->workflows->deliverToLead(
     *     522,                        // Lead ID
     *     'newsletter-generator',     // Callable workflow name
     *     ['topic' => 'AI for Law Firms', 'tone' => 'professional'],
     *     [
     *         'send_email' => true,
     *         'email_subject' => 'Your Newsletter is Ready!',
     *         'message_mode' => 'ai',  // AI-generated email content
     *     ]
     * );
     *
     * echo "Workflow: {$result->workflowName}\n";
     * echo "Deliverable ID: {$result->deliverableId}\n";
     * echo "Email sent to: " . implode(', ', $result->emailSentTo) . "\n";
     * echo "Time to value: {$result->timeToValueSeconds}s\n";
     * ```
     *
     * @example CLI usage:
     * ```bash
     * iris sdk:call workflows.deliverToLead 522 newsletter-generator \
     *   input='{"topic":"AI for Law Firms"}' \
     *   send_email=true \
     *   message_mode=ai
     * ```
     */
    public function deliverToLead(
        int $leadId,
        string $callableName,
        array $input = [],
        array $options = []
    ): DeliveryResult {
        $startTime = microtime(true);
        $userId = $this->config->requireUserId();

        // Step 1: Execute the callable workflow
        $executeResponse = $this->http->post('/api/v1/workflows/execute-callable', [
            'callable_name' => $callableName,
            'user_id' => $userId,
            'input' => $input,
            'context' => [
                'lead_id' => $leadId,
                'delivery_mode' => true,
            ],
        ]);

        if (!($executeResponse['success'] ?? false)) {
            throw new \IRIS\SDK\Exceptions\WorkflowException(
                $executeResponse['error'] ?? 'Workflow execution failed'
            );
        }

        $workflowResult = $executeResponse['output'] ?? null;
        $workflowInfo = $executeResponse['workflow'] ?? [];
        $executionId = $executeResponse['execution_id'] ?? null;

        // Step 2: Create a deliverable from the result
        // Determine what to deliver - could be a URL, content, or file
        $deliverableTitle = $options['deliverable_title']
            ?? $workflowInfo['name'] ?? $callableName;

        // Build the deliverable URL (pointing to the workflow result page)
        $deliverableUrl = $this->buildDeliverableUrl($executionId, $workflowInfo);

        $deliverableResponse = $this->http->post("/api/v1/leads/{$leadId}/deliverables", [
            'type' => 'link',
            'title' => $deliverableTitle . ' - ' . date('M j, Y g:i A'),
            'external_url' => $deliverableUrl,
        ]);

        $deliverable = $deliverableResponse['data']['deliverable'] ?? $deliverableResponse;
        $deliverableId = $deliverable['id'] ?? null;

        // Step 3: Send email notification (if enabled)
        $sendEmail = $options['send_email'] ?? true;
        $emailSentTo = [];
        $emailResult = null;

        if ($sendEmail && $deliverableId) {
            $emailOptions = [
                'deliverable_ids' => [$deliverableId],
                'message_mode' => $options['message_mode'] ?? 'ai',
                'include_project_context' => $options['include_project_context'] ?? true,
            ];

            if (isset($options['email_subject'])) {
                $emailOptions['subject'] = $options['email_subject'];
            }

            if (isset($options['recipient_emails'])) {
                $emailOptions['recipient_emails'] = $options['recipient_emails'];
            }

            if (isset($options['custom_context'])) {
                $emailOptions['custom_context'] = $options['custom_context'];
            }

            // Use generateAndSend pattern - preview then send
            try {
                // First generate the preview
                $preview = $this->http->post(
                    "/api/v1/leads/{$leadId}/deliverables/preview-email",
                    $emailOptions
                );

                // Then send with the generated content
                $emailResult = $this->http->post(
                    "/api/v1/leads/{$leadId}/deliverables/send",
                    array_merge($emailOptions, [
                        'email_content' => $preview['data']['body'] ?? $preview['body'] ?? '',
                        'subject' => $emailOptions['subject']
                            ?? $preview['data']['subject']
                            ?? $preview['subject']
                            ?? "Your {$deliverableTitle} is Ready!",
                    ])
                );

                $emailSentTo = $emailResult['data']['sent_to']
                    ?? $emailResult['sent_to']
                    ?? [];
            } catch (\Exception $e) {
                // Email failure shouldn't fail the whole delivery
                $emailResult = ['error' => $e->getMessage()];
            }
        }

        // Calculate time to value
        $timeToValue = round(microtime(true) - $startTime, 2);

        return new DeliveryResult([
            'success' => true,
            'lead_id' => $leadId,
            'callable_name' => $callableName,
            'workflow_id' => $workflowInfo['id'] ?? null,
            'workflow_name' => $workflowInfo['name'] ?? $callableName,
            'execution_id' => $executionId,
            'deliverable_id' => $deliverableId,
            'deliverable_url' => $deliverableUrl,
            'deliverable_title' => $deliverableTitle,
            'workflow_output' => $workflowResult,
            'email_sent' => !empty($emailSentTo),
            'email_sent_to' => $emailSentTo,
            'email_result' => $emailResult,
            'time_to_value_seconds' => $timeToValue,
        ]);
    }

    /**
     * Build the deliverable URL for a workflow execution result.
     *
     * @param string|null $executionId
     * @param array $workflowInfo
     * @return string
     */
    protected function buildDeliverableUrl(?string $executionId, array $workflowInfo): string
    {
        $baseUrl = $this->config->irisUrl ?? 'https://app.heyiris.io';

        // If we have a workflow slug, use the template landing page
        if (!empty($workflowInfo['slug'])) {
            return "{$baseUrl}/iris/templates/{$workflowInfo['slug']}";
        }

        // Otherwise link to the workflow run result
        if ($executionId) {
            return "{$baseUrl}/workflow-runs/{$executionId}";
        }

        // Fallback
        return "{$baseUrl}/workflows";
    }

    /**
     * List all callable workflows available for delivery.
     *
     * @param array{
     *     public?: bool,
     *     category?: string
     * } $filters Filter options
     * @return array List of callable workflows
     *
     * @example
     * ```php
     * $workflows = $iris->workflows->listCallable();
     * foreach ($workflows as $workflow) {
     *     echo "{$workflow['callable_name']}: {$workflow['description']}\n";
     * }
     * ```
     */
    public function listCallable(array $filters = []): array
    {
        $userId = $this->config->userId;

        $params = [
            'public' => $filters['public'] ?? true,
        ];

        if ($userId) {
            $params['user_id'] = $userId;
        }

        if (isset($filters['category'])) {
            $params['category'] = $filters['category'];
        }

        $response = $this->http->get('/api/v1/workflows/callable', $params);

        return $response['data'] ?? $response ?? [];
    }

    /**
     * Execute a callable workflow by name.
     *
     * @param string $callableName The callable workflow name
     * @param array $input Input data for the workflow
     * @param array $context Additional context
     * @return array Execution result
     *
     * @example
     * ```php
     * $result = $iris->workflows->executeCallable('newsletter-generator', [
     *     'topic' => 'AI for Law Firms',
     *     'tone' => 'professional',
     * ]);
     * ```
     */
    public function executeCallable(string $callableName, array $input = [], array $context = []): array
    {
        $userId = $this->config->requireUserId();

        $response = $this->http->post('/api/v1/workflows/execute-callable', [
            'callable_name' => $callableName,
            'user_id' => $userId,
            'input' => $input,
            'context' => $context,
        ]);

        return $response;
    }
}

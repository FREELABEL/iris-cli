<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Lead Deliverables Resource
 *
 * Manage deliverables (files and links) for leads.
 *
 * @example
 * ```php
 * // List deliverables
 * $deliverables = $iris->leads->deliverables(123)->list();
 *
 * // Add external link deliverable
 * $deliverable = $iris->leads->deliverables(123)->create([
 *     'type' => 'link',
 *     'title' => 'Trained AI Agent',
 *     'external_url' => 'https://app.heyiris.io/agents/456',
 * ]);
 *
 * // Upload file deliverable
 * $deliverable = $iris->leads->deliverables(123)->createFile([
 *     'title' => 'Monthly Report',
 *     'file_path' => '/path/to/report.pdf',
 * ]);
 * ```
 */
class DeliverablesResource
{
    protected Client $http;
    protected Config $config;
    protected int $leadId;

    public function __construct(Client $http, Config $config, int $leadId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->leadId = $leadId;
    }

    /**
     * List all deliverables for the lead.
     *
     * @return array
     *
     * @example
     * ```php
     * $deliverables = $iris->leads->deliverables(123)->list();
     *
     * foreach ($deliverables as $deliverable) {
     *     echo "{$deliverable['title']} ({$deliverable['type']})\n";
     *     echo "  URL: {$deliverable['url']}\n";
     * }
     * ```
     */
    public function list(): array
    {
        $response = $this->http->get("/api/v1/leads/{$this->leadId}/deliverables");

        return $response['deliverables'] ?? [];
    }

    /**
     * Create a new deliverable (external link or file upload).
     *
     * @param array $data Deliverable data
     *   - type: 'link' or 'file' (required)
     *   - title: Deliverable title (required)
     *   - external_url: URL for link type (required if type=link)
     *   - file: File resource for file type (required if type=file)
     *   - custom_request_id: Optional invoice/custom request ID
     *
     * @return array Created deliverable
     *
     * @example
     * ```php
     * // Create link deliverable
     * $deliverable = $iris->leads->deliverables(123)->create([
     *     'type' => 'link',
     *     'title' => 'Trained AI Agent Dashboard',
     *     'external_url' => 'https://app.heyiris.io/agents/456',
     * ]);
     *
     * // Create with invoice link
     * $deliverable = $iris->leads->deliverables(123)->create([
     *     'type' => 'link',
     *     'title' => 'December Newsletter',
     *     'external_url' => 'https://example.com/newsletter',
     *     'custom_request_id' => 789, // Link to invoice
     * ]);
     * ```
     */
    public function create(array $data): array
    {
        $response = $this->http->post("/api/v1/leads/{$this->leadId}/deliverables", $data);

        return $response['data']['deliverable'] ?? $response;
    }

    /**
     * Upload a file as a deliverable.
     *
     * @param string $filePath Absolute path to the file
     * @param array $options Additional options
     *   - title: Deliverable title (defaults to filename)
     *   - custom_request_id: Optional invoice/custom request ID
     *
     * @return array Created deliverable
     *
     * @example
     * ```php
     * // Upload file
     * $deliverable = $iris->leads->deliverables(123)->uploadFile(
     *     '/path/to/report.pdf',
     *     ['title' => 'Q4 2025 Report']
     * );
     * ```
     */
    public function uploadFile(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $data = [
            'type' => 'file',
            'title' => $options['title'] ?? basename($filePath),
            'file' => new \CURLFile($filePath),
        ];

        if (isset($options['custom_request_id'])) {
            $data['custom_request_id'] = $options['custom_request_id'];
        }

        $response = $this->http->post("/api/v1/leads/{$this->leadId}/deliverables", $data);

        return $response['data']['deliverable'] ?? $response;
    }

    /**
     * Update a deliverable.
     *
     * @param int $deliverableId Deliverable ID
     * @param array $data Update data
     *   - title: New title
     *   - external_url: New URL (for link type only)
     *
     * @return array Updated deliverable
     *
     * @example
     * ```php
     * $deliverable = $iris->leads->deliverables(123)->update(456, [
     *     'title' => 'Updated AI Agent Dashboard',
     *     'external_url' => 'https://app.heyiris.io/agents/789',
     * ]);
     * ```
     */
    public function update(int $deliverableId, array $data): array
    {
        $response = $this->http->patch(
            "/api/v1/leads/{$this->leadId}/deliverables/{$deliverableId}",
            $data
        );

        return $response['data']['deliverable'] ?? $response;
    }

    /**
     * Delete a deliverable.
     *
     * @param int $deliverableId Deliverable ID
     * @return bool
     *
     * @example
     * ```php
     * $success = $iris->leads->deliverables(123)->delete(456);
     * ```
     */
    public function delete(int $deliverableId): bool
    {
        $response = $this->http->delete("/api/v1/leads/{$this->leadId}/deliverables/{$deliverableId}");

        return $response['success'] ?? false;
    }

    /**
     * Preview the deliverable email before sending.
     *
     * Uses AI to generate a personalized email based on the lead context.
     *
     * @param array{
     *     deliverable_ids: array<int>,
     *     message_mode?: string,
     *     custom_context?: string,
     *     subject?: string,
     *     include_project_context?: bool,
     *     attach_invoice?: bool,
     *     plain_text?: bool
     * } $options Preview options
     * @return array Generated email preview with subject and body
     *
     * @example
     * ```php
     * // Generate AI email preview
     * $preview = $iris->leads->deliverables(16)->previewEmail([
     *     'deliverable_ids' => [203, 204],
     *     'message_mode' => 'ai',
     *     'subject' => 'Your deliverables are ready',
     *     'include_project_context' => true,
     * ]);
     *
     * echo "Subject: {$preview['subject']}\n";
     * echo "Body:\n{$preview['body']}\n";
     *
     * // If satisfied, send the email
     * $result = $iris->leads->deliverables(16)->send([
     *     'deliverable_ids' => [203, 204],
     *     'email_content' => $preview['body'],
     *     'subject' => $preview['subject'],
     * ]);
     * ```
     */
    public function previewEmail(array $options): array
    {
        $response = $this->http->post(
            "/api/v1/leads/{$this->leadId}/deliverables/preview-email",
            $options
        );

        return $response['data'] ?? $response;
    }

    /**
     * Send deliverable email notification to the lead.
     *
     * @param array{
     *     deliverable_ids: array<int>,
     *     recipient_emails?: array<string>,
     *     subject?: string,
     *     message?: string,
     *     email_content?: string,
     *     message_mode?: string,
     *     custom_context?: string,
     *     include_project_context?: bool,
     *     attach_invoice?: bool,
     *     attach_files?: bool,
     *     plain_text?: bool
     * } $options Email options
     * @return array Email send result
     *
     * @example
     * ```php
     * // Send with AI-generated content
     * $result = $iris->leads->deliverables(16)->send([
     *     'deliverable_ids' => [203],
     *     'message_mode' => 'ai',
     *     'subject' => 'Your deliverables are ready',
     *     'recipient_emails' => ['mike@greenleaf.co'],
     *     'include_project_context' => true,
     * ]);
     *
     * // Send with custom content
     * $result = $iris->leads->deliverables(16)->send([
     *     'deliverable_ids' => [203, 204],
     *     'subject' => 'Project Deliverables',
     *     'email_content' => 'Hi Michael, Your project files are ready...',
     *     'recipient_emails' => ['mike@greenleaf.co'],
     *     'attach_files' => true,
     * ]);
     *
     * if ($result['success']) {
     *     echo "Email sent to " . implode(', ', $result['sent_to']) . "\n";
     * }
     * ```
     */
    public function send(array $options = []): array
    {
        $response = $this->http->post("/api/v1/leads/{$this->leadId}/deliverables/send", $options);

        return $response['data'] ?? $response;
    }

    /**
     * Convenience method to preview and send deliverables email.
     *
     * First generates an AI preview, then sends it.
     *
     * @param array<int> $deliverableIds IDs of deliverables to send
     * @param array{
     *     recipient_emails?: array<string>,
     *     subject?: string,
     *     include_project_context?: bool,
     *     attach_invoice?: bool,
     *     attach_files?: bool
     * } $options Send options
     * @return array Send result
     *
     * @example
     * ```php
     * // Generate and send in one step
     * $result = $iris->leads->deliverables(16)->generateAndSend(
     *     [203, 204],
     *     [
     *         'subject' => 'Your project is complete!',
     *         'include_project_context' => true,
     *     ]
     * );
     * ```
     */
    public function generateAndSend(array $deliverableIds, array $options = []): array
    {
        // First generate the preview
        $preview = $this->previewEmail(array_merge([
            'deliverable_ids' => $deliverableIds,
            'message_mode' => 'ai',
        ], $options));

        // Then send with the generated content
        return $this->send(array_merge([
            'deliverable_ids' => $deliverableIds,
            'email_content' => $preview['body'] ?? $preview['email_content'] ?? '',
            'subject' => $preview['subject'] ?? $options['subject'] ?? 'Your deliverables are ready',
        ], $options));
    }
}

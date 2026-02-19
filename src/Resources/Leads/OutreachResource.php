<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Outreach Resource
 *
 * Manage lead outreach including AI-powered email generation and sending.
 *
 * @example
 * ```php
 * // Generate an AI email draft
 * $draft = $iris->leads->outreach(123)->generateEmail(
 *     'Follow up on our meeting last week',
 *     ['tone' => 'professional', 'include_cta' => true]
 * );
 *
 * // Send the composed email
 * $result = $iris->leads->outreach(123)->sendEmail([
 *     'to_email' => 'john@example.com',
 *     'subject' => $draft['draft']['subject'],
 *     'body_html' => $draft['draft']['body'],
 * ]);
 * ```
 */
class OutreachResource
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
     * Check if the lead is eligible for outreach.
     *
     * @return array Eligibility status with lead info and stats
     */
    public function checkEligibility(): array
    {
        return $this->http->get("/api/v1/leads/{$this->leadId}/outreach/check");
    }

    /**
     * Get comprehensive outreach information for the lead.
     *
     * @return array Lead info, eligibility, stats, and recent outreach history
     */
    public function getInfo(): array
    {
        return $this->http->get("/api/v1/leads/{$this->leadId}/outreach/info");
    }

    /**
     * Record an outreach attempt.
     *
     * @param string $content Description of the outreach attempt
     * @param array $metadata Additional metadata (email_subject, email_provider, etc.)
     * @return array Recorded note and updated stats
     */
    public function recordAttempt(string $content, array $metadata = []): array
    {
        return $this->http->post("/api/v1/leads/{$this->leadId}/outreach/record", [
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Set auto-respond status for the lead.
     *
     * @param bool $enabled Enable or disable auto-respond
     * @return array Updated lead info
     */
    public function setAutoRespond(bool $enabled): array
    {
        return $this->http->put("/api/v1/leads/{$this->leadId}/outreach/auto-respond", [
            'auto_respond' => $enabled,
        ]);
    }

    /**
     * Generate an AI-powered email draft for the lead.
     *
     * Uses lead context (notes, tags, history) and optional profile/agent info
     * to create a personalized email draft.
     *
     * @param string $prompt Instructions for the email (e.g., "Follow up on our meeting")
     * @param array{
     *     tone?: string,
     *     include_cta?: bool,
     *     max_length?: string,
     *     profile_id?: int,
     *     agent_id?: int,
     *     options?: array
     * } $options Generation options
     * @return array Generated draft with subject and body
     *
     * @example
     * ```php
     * // Basic email generation
     * $draft = $outreach->generateEmail('Follow up about the proposal');
     *
     * // With options
     * $draft = $outreach->generateEmail('Initial outreach', [
     *     'tone' => 'friendly',
     *     'include_cta' => true,
     *     'max_length' => 'short',
     * ]);
     *
     * // Revision mode - refine existing draft
     * $revised = $outreach->generateEmail('Make it more urgent', [
     *     'options' => [
     *         'revision_mode' => true,
     *         'current_subject' => $draft['draft']['subject'],
     *         'current_body' => $draft['draft']['body'],
     *     ]
     * ]);
     * ```
     */
    public function generateEmail(string $prompt, array $options = []): array
    {
        $data = array_merge(['prompt' => $prompt], $options);

        return $this->http->post("/api/v1/leads/{$this->leadId}/outreach/generate-email", $data);
    }

    /**
     * Send a composed email to the lead.
     *
     * Sends the email via Resend API, stores it in lead_email_messages,
     * and records it as an outreach attempt.
     *
     * @param array{
     *     to_email: string,
     *     subject: string,
     *     body_html: string,
     *     to_name?: string,
     *     body_text?: string,
     *     from_email?: string,
     *     sender_name?: string,
     *     plain_text_only?: bool
     * } $emailData Email data
     * @return array Sent email info
     *
     * @example
     * ```php
     * // Generate and send in one flow
     * $draft = $outreach->generateEmail('Follow up on proposal');
     *
     * $result = $outreach->sendEmail([
     *     'to_email' => 'john@example.com',
     *     'to_name' => 'John Doe',
     *     'subject' => $draft['draft']['subject'],
     *     'body_html' => $draft['draft']['body'],
     *     'sender_name' => 'Alex from IRIS',
     * ]);
     *
     * echo "Email sent! ID: {$result['email']['id']}";
     *
     * // Note: plain_text_only defaults to true for personal-looking emails
     * // To use HTML template styling, set plain_text_only to false:
     * $result = $outreach->sendEmail([
     *     'to_email' => 'john@example.com',
     *     'subject' => 'Quick note',
     *     'body_html' => 'Message with template.',
     *     'plain_text_only' => false, // Use styled HTML template
     * ]);
     * ```
     */
    public function sendEmail(array $emailData): array
    {
        return $this->http->post("/api/v1/leads/{$this->leadId}/outreach/send-email", $emailData);
    }

    /**
     * Generate and immediately send an email (convenience method).
     *
     * Combines generateEmail() and sendEmail() into a single call.
     * Useful for automated outreach workflows.
     *
     * @param string $toEmail Recipient email address
     * @param string $prompt Instructions for email generation
     * @param array $options Additional options for generation and sending
     * @return array Result with both draft and sent email info
     *
     * @example
     * ```php
     * $result = $outreach->generateAndSend(
     *     'john@example.com',
     *     'Initial cold outreach introducing our AI platform',
     *     [
     *         'tone' => 'professional',
     *         'sender_name' => 'Alex Mayo',
     *         'to_name' => 'John',
     *     ]
     * );
     * ```
     */
    public function generateAndSend(string $toEmail, string $prompt, array $options = []): array
    {
        // First generate the email
        $generationOptions = array_intersect_key($options, array_flip([
            'tone', 'include_cta', 'max_length', 'profile_id', 'agent_id', 'options'
        ]));

        $draft = $this->generateEmail($prompt, $generationOptions);

        if (!$draft['success']) {
            return $draft;
        }

        // Then send it
        $sendOptions = [
            'to_email' => $toEmail,
            'subject' => $draft['draft']['subject'],
            'body_html' => $draft['draft']['body'],
        ];

        if (isset($options['to_name'])) {
            $sendOptions['to_name'] = $options['to_name'];
        }
        if (isset($options['sender_name'])) {
            $sendOptions['sender_name'] = $options['sender_name'];
        }
        if (isset($options['from_email'])) {
            $sendOptions['from_email'] = $options['from_email'];
        }
        if (isset($options['plain_text_only'])) {
            $sendOptions['plain_text_only'] = $options['plain_text_only'];
        }

        $sendResult = $this->sendEmail($sendOptions);

        return [
            'success' => $sendResult['success'] ?? false,
            'draft' => $draft['draft'],
            'email' => $sendResult['email'] ?? null,
            'lead' => $sendResult['lead'] ?? null,
        ];
    }
}

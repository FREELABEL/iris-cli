<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Notes Sub-Resource
 *
 * Manage notes for a lead.
 *
 * @example
 * ```php
 * // List all notes for a lead
 * $notes = $iris->leads->notes(412)->all();
 *
 * // Create a note
 * $note = $iris->leads->notes(412)->create('Meeting scheduled for tomorrow');
 *
 * // Update a note
 * $note = $iris->leads->notes(412)->update(123, 'Updated content');
 *
 * // Delete a note
 * $iris->leads->notes(412)->delete(123);
 * ```
 */
class NotesResource
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
     * Get all notes for this lead.
     *
     * Notes are embedded in the lead response, so we fetch the lead
     * and extract the notes array.
     *
     * @return LeadNoteCollection
     */
    public function all(): LeadNoteCollection
    {
        // Notes are embedded in the lead response
        $response = $this->http->get("/api/v1/leads/{$this->leadId}");

        $notesData = $response['notes'] ?? [];
        $notes = array_map(
            fn($data) => new LeadNote($data),
            $notesData
        );

        return new LeadNoteCollection($notes, []);
    }

    /**
     * Get a specific note by ID.
     *
     * Fetches all notes and filters by ID since there's no dedicated endpoint.
     *
     * @param int $noteId Note ID
     * @return LeadNote
     * @throws \RuntimeException If note not found
     */
    public function get(int $noteId): LeadNote
    {
        $notes = $this->all();

        foreach ($notes as $note) {
            if ($note->id === $noteId) {
                return $note;
            }
        }

        throw new \RuntimeException("Note with ID {$noteId} not found on lead {$this->leadId}");
    }

    /**
     * Create a new note for this lead.
     *
     * @param string $content Note content
     * @param array{
     *     type?: string,
     *     activity_type?: string,
     *     activity_icon?: string
     * } $metadata Additional metadata
     * @return LeadNote
     */
    public function create(string $content, array $metadata = []): LeadNote
    {
        $response = $this->http->post("/api/v1/leads/{$this->leadId}/notes", array_merge(
            ['message' => $content],
            $metadata
        ));

        return new LeadNote($response['note'] ?? $response);
    }

    /**
     * Update an existing note.
     *
     * @param int $noteId Note ID
     * @param string $content Updated content
     * @param array $metadata Additional metadata
     * @return LeadNote
     */
    public function update(int $noteId, string $content, array $metadata = []): LeadNote
    {
        // API uses PUT, not PATCH
        $response = $this->http->put(
            "/api/v1/leads/{$this->leadId}/notes/{$noteId}",
            array_merge(['message' => $content], $metadata)
        );

        return new LeadNote($response['note'] ?? $response);
    }

    /**
     * Delete a note.
     *
     * Note: Uses the webhook endpoint which requires webhook auth.
     *
     * @param int $noteId Note ID
     * @return bool
     */
    public function delete(int $noteId): bool
    {
        // Delete endpoint is under webhooks prefix
        $this->http->delete("/api/v1/webhooks/leads/{$this->leadId}/notes/{$noteId}");

        return true;
    }

    /**
     * Add an outreach note (email sent, call made, etc.)
     *
     * @param string $content Outreach description
     * @param array{
     *     email_id?: int,
     *     to_email?: string,
     *     provider?: string,
     *     channel?: string
     * } $metadata Outreach metadata
     * @return LeadNote
     */
    public function addOutreach(string $content, array $metadata = []): LeadNote
    {
        return $this->create($content, array_merge(
            ['type' => 'outreach'],
            $metadata
        ));
    }
}

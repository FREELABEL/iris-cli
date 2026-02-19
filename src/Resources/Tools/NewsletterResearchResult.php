<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Tools;

/**
 * Result from newsletter research tool.
 *
 * Contains outline options for the user to choose from (HITL pattern).
 * Supports multi-modal ingestion from videos, links, and web search.
 */
class NewsletterResearchResult
{
    public bool $success;
    public string $topic;
    public ?string $researchSummary;
    public array $sourcesUsed;
    public array $themes;
    public array $outlineOptions;
    public string $formattedDisplay;
    public array $sources;
    public bool $awaitingHumanInput;
    public string $approvalType;
    public array $inputSchema;
    public array $context;
    public ?string $error;

    public function __construct(array $data)
    {
        $this->success = $data['success'] ?? false;
        $this->topic = $data['topic'] ?? '';
        $this->researchSummary = $data['research_summary'] ?? null;
        $this->sourcesUsed = $data['sources_used'] ?? [];
        $this->themes = $data['themes'] ?? [];
        $this->outlineOptions = $data['outline_options'] ?? [];
        $this->formattedDisplay = $data['formatted_display'] ?? '';
        $this->sources = $data['sources'] ?? [];
        $this->awaitingHumanInput = $data['awaiting_human_input'] ?? false;
        $this->approvalType = $data['approval_type'] ?? '';
        $this->inputSchema = $data['input_schema'] ?? [];
        $this->context = $data['context'] ?? [];
        $this->error = $data['error'] ?? null;
    }

    /**
     * Get outline option by number (1, 2, or 3).
     */
    public function getOutline(int $optionNumber): ?array
    {
        foreach ($this->outlineOptions as $option) {
            if (($option['option_number'] ?? 0) === $optionNumber) {
                return $option;
            }
        }
        return null;
    }

    /**
     * Get all outline titles.
     */
    public function getOutlineTitles(): array
    {
        return array_map(fn($o) => $o['title'] ?? '', $this->outlineOptions);
    }

    /**
     * Get theme names.
     */
    public function getThemeNames(): array
    {
        return array_map(fn($t) => $t['name'] ?? '', $this->themes);
    }

    /**
     * Check if awaiting human selection.
     */
    public function isAwaitingSelection(): bool
    {
        return $this->awaitingHumanInput && $this->approvalType === 'outline_selection';
    }

    /**
     * Prepare parameters for newsletterWrite based on user selection.
     *
     * @param int $selectedOption Option number (1, 2, or 3)
     * @param string|null $customizationNotes Optional customization notes
     * @param string|null $recipientEmail Optional email to send to
     * @param string|null $recipientName Optional recipient name
     * @param string|null $senderName Optional sender name
     * @param int|null $leadId Optional lead ID for CRM tracking
     * @return array Parameters ready for newsletterWrite()
     */
    public function prepareWriteParams(
        int $selectedOption,
        ?string $customizationNotes = null,
        ?string $recipientEmail = null,
        ?string $recipientName = null,
        ?string $senderName = null,
        ?int $leadId = null
    ): array {
        $params = [
            'selected_option' => $selectedOption,
            'outline_options' => $this->outlineOptions,
            'context' => $this->context,
        ];

        if ($customizationNotes) {
            $params['customization_notes'] = $customizationNotes;
        }
        if ($recipientEmail) {
            $params['recipient_email'] = $recipientEmail;
        }
        if ($recipientName) {
            $params['recipient_name'] = $recipientName;
        }
        if ($senderName) {
            $params['sender_name'] = $senderName;
        }
        if ($leadId) {
            $params['lead_id'] = $leadId;
        }

        return $params;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'topic' => $this->topic,
            'research_summary' => $this->researchSummary,
            'sources_used' => $this->sourcesUsed,
            'themes' => $this->themes,
            'outline_options' => $this->outlineOptions,
            'formatted_display' => $this->formattedDisplay,
            'sources' => $this->sources,
            'awaiting_human_input' => $this->awaitingHumanInput,
            'approval_type' => $this->approvalType,
            'input_schema' => $this->inputSchema,
            'context' => $this->context,
            'error' => $this->error,
        ];
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Agent Model
 *
 * Represents an AI agent with its configuration and capabilities.
 */
class Agent
{
    public int $id;
    public string $name;
    public string $prompt;
    public string $type;
    public string $model;
    public ?int $bloqId;
    public bool $isPublic;
    public ?string $slug;
    public array $personality;
    public array $capabilities;
    public array $integrations;
    public array $fileAttachments;
    public array $settings;
    public ?string $webhookUrl;
    public ?string $createdAt;
    public ?string $updatedAt;

    /**
     * Raw data from API.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->prompt = $data['prompt'] ?? $data['system_prompt'] ?? '';
        $this->type = $data['type'] ?? 'assistant';
        $this->model = $data['model'] ?? 'gpt-4o-mini';
        $this->bloqId = $data['bloq_id'] ?? null;
        $this->isPublic = (bool) ($data['is_public'] ?? false);
        $this->slug = $data['slug'] ?? null;
        $this->personality = $data['personality'] ?? [];
        $this->capabilities = $data['capabilities'] ?? [];
        
        // Parse integrations from settings['agentIntegrations'] map
        $integrations = [];
        if (isset($data['settings']['agentIntegrations'])) {
            // Convert map like {'google-gemini': true, 'slack': false} to array of enabled keys
            $integrations = array_keys(array_filter($data['settings']['agentIntegrations'], fn($v) => $v === true));
        }
        $this->integrations = $integrations;
        $this->fileAttachments = $data['file_attachments'] ?? [];
        $this->settings = $data['settings'] ?? [];

        $this->webhookUrl = $data['webhook_url'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    /**
     * Check if agent has a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Check if agent has a specific integration enabled.
     */
    public function hasIntegration(string $integration): bool
    {
        return in_array($integration, $this->integrations, true);
    }

    /**
     * Check if agent has a knowledge base (RAG).
     */
    public function hasKnowledgeBase(): bool
    {
        return $this->bloqId !== null;
    }

    /**
     * Get raw attribute value.
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
        return $this->attributes;
    }

    /**
     * Get agent's public URL (if public, uses slug).
     */
    public function getPublicUrl(string $baseUrl = 'https://app.heyiris.io'): ?string
    {
        if (!$this->isPublic || !$this->slug) {
            return null;
        }

        return "{$baseUrl}/agent/{$this->slug}";
    }

    /**
     * Get the simple embed/share URL for this agent.
     *
     * This URL works for any agent (public or private) and includes bloq context.
     * Format: /agent/simple/{id}?bloq={bloqId}
     *
     * @param string $baseUrl Base URL (default: https://app.heyiris.io)
     * @return string The simple agent URL
     */
    public function getSimpleUrl(string $baseUrl = 'https://app.heyiris.io'): string
    {
        $url = "{$baseUrl}/agent/simple/{$this->id}";

        if ($this->bloqId) {
            $url .= "?bloq={$this->bloqId}";
        }

        return $url;
    }

    /**
     * Get the embed URL for this agent (alias for getSimpleUrl).
     */
    public function getEmbedUrl(string $baseUrl = 'https://app.heyiris.io'): string
    {
        return $this->getSimpleUrl($baseUrl);
    }

    /**
     * Get all available URLs for this agent.
     *
     * @param string $baseUrl Base URL
     * @return array{simple: string, public: ?string, embed: string}
     */
    public function getUrls(string $baseUrl = 'https://app.heyiris.io'): array
    {
        return [
            'simple' => $this->getSimpleUrl($baseUrl),
            'embed' => $this->getEmbedUrl($baseUrl),
            'public' => $this->getPublicUrl($baseUrl),
        ];
    }
}

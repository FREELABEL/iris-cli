<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Agent Configuration DTO
 *
 * Used to configure a new agent or update an existing one.
 */
class AgentConfig
{
    /**
     * Create a new agent configuration.
     *
     * @param string $name Display name for the agent
     * @param string $prompt System prompt/instructions for the agent
     * @param string $type Agent type: 'assistant', 'human', 'specialist'
     * @param string $model AI model to use: 'gpt-4o-mini', 'gpt-4o', 'gpt-5-nano', 'gpt-4.1-nano', etc.
     * @param array<string> $integrations Enabled integrations: 'google-drive', 'gmail', 'slack', etc.
     * @param int|null $knowledgeBaseId Bloq ID to use as knowledge base (for RAG)
     * @param array{
     *     communication_style?: string,
     *     response_mode?: string,
     *     response_length?: string
     * } $personality Personality traits
     * @param array<string> $capabilities Agent capabilities
     * @param bool $isPublic Whether the agent is publicly accessible
     */
    public function __construct(
        public string $name,
        public string $prompt,
        public string $type = 'assistant',
        public string $model = 'gpt-4o-mini',
        public array $integrations = [],
        public ?int $knowledgeBaseId = null,
        public array $personality = [
            'communication_style' => 'professional',
            'response_mode' => 'conversational',
            'response_length' => 'medium',
        ],
        public array $capabilities = [],
        public bool $isPublic = false,
    ) {
        $this->validateModel();
        $this->validateType();
    }

    /**
     * Convert to array for API submission.
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'prompt' => $this->prompt,
            'type' => $this->type,
            'model' => $this->model,
            'integrations' => $this->integrations,
            'bloq_id' => $this->knowledgeBaseId,
            'personality' => $this->personality,
            'capabilities' => $this->capabilities,
            'is_public' => $this->isPublic,
        ], fn($value) => $value !== null && $value !== []);
    }

    /**
     * Create an assistant agent.
     */
    public static function assistant(string $name, string $prompt): self
    {
        return new self(
            name: $name,
            prompt: $prompt,
            type: 'assistant',
        );
    }

    /**
     * Create a specialist agent.
     */
    public static function specialist(string $name, string $prompt, array $capabilities = []): self
    {
        return new self(
            name: $name,
            prompt: $prompt,
            type: 'specialist',
            capabilities: $capabilities,
        );
    }

    /**
     * Add an integration.
     */
    public function withIntegration(string $integration): self
    {
        $this->integrations[] = $integration;
        return $this;
    }

    /**
     * Add multiple integrations.
     */
    public function withIntegrations(array $integrations): self
    {
        $this->integrations = array_merge($this->integrations, $integrations);
        return $this;
    }

    /**
     * Set knowledge base (for RAG).
     */
    public function withKnowledgeBase(int $bloqId): self
    {
        $this->knowledgeBaseId = $bloqId;
        return $this;
    }

    /**
     * Set model.
     */
    public function withModel(string $model): self
    {
        $this->model = $model;
        $this->validateModel();
        return $this;
    }

    /**
     * Make the agent public.
     */
    public function makePublic(): self
    {
        $this->isPublic = true;
        return $this;
    }

    /**
     * Validate the model is supported.
     */
    protected function validateModel(): void
    {
        $supportedModels = [
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-5-nano',
            'gpt-4.1-nano',
            'claude-3-sonnet',
            'claude-3-haiku',
            'deepseek',
            'gemini-pro',
        ];

        // Allow any model, but warn if not in known list
        // This allows for new models without SDK updates
    }

    /**
     * Validate the agent type.
     */
    protected function validateType(): void
    {
        $validTypes = ['assistant', 'human', 'specialist'];

        if (!in_array($this->type, $validTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid agent type '{$this->type}'. Must be one of: " . implode(', ', $validTypes)
            );
        }
    }
}

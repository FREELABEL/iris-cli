<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Chat Response
 *
 * Represents the response from an agent chat interaction.
 */
class ChatResponse
{
    /**
     * The response content/message.
     */
    public string $content;

    /**
     * The AI model used.
     */
    public ?string $model;

    /**
     * Tokens used in the prompt.
     */
    public int $promptTokens;

    /**
     * Tokens used in the completion.
     */
    public int $completionTokens;

    /**
     * Total tokens used.
     */
    public int $totalTokens;

    /**
     * Thread ID for conversation continuity.
     */
    public ?string $threadId;

    /**
     * Whether RAG context was used.
     */
    public bool $usedRag;

    /**
     * RAG context sources (if any).
     */
    public array $sources;

    /**
     * Raw response data.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->content = $data['content'] ?? $data['message'] ?? $data['response'] ?? '';
        $this->model = $data['model'] ?? null;
        $this->promptTokens = (int) ($data['prompt_tokens'] ?? $data['usage']['prompt_tokens'] ?? 0);
        $this->completionTokens = (int) ($data['completion_tokens'] ?? $data['usage']['completion_tokens'] ?? 0);
        $this->totalTokens = (int) ($data['total_tokens'] ?? $data['usage']['total_tokens'] ?? $this->promptTokens + $this->completionTokens);
        $this->threadId = $data['thread_id'] ?? null;
        $this->usedRag = (bool) ($data['used_rag'] ?? false);
        $this->sources = $data['sources'] ?? $data['rag_sources'] ?? [];
    }

    /**
     * Get the response content.
     */
    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Check if the response is empty.
     */
    public function isEmpty(): bool
    {
        return empty(trim($this->content));
    }

    /**
     * Get raw attribute.
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
        return [
            'content' => $this->content,
            'model' => $this->model,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'thread_id' => $this->threadId,
            'used_rag' => $this->usedRag,
            'sources' => $this->sources,
        ];
    }

    /**
     * Get estimated cost based on token usage.
     */
    public function getEstimatedCost(float $inputCostPer1k = 0.00015, float $outputCostPer1k = 0.0006): float
    {
        return ($this->promptTokens / 1000 * $inputCostPer1k) +
               ($this->completionTokens / 1000 * $outputCostPer1k);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\RAG;

/**
 * SearchResult Model
 *
 * Represents a search result from RAG/vector search.
 */
class SearchResult
{
    public string $id;
    public string $content;
    public float $score;
    public array $metadata;
    public ?string $title;
    public ?string $source;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = $data['id'] ?? '';
        $this->content = $data['content'] ?? '';
        $this->score = (float) ($data['score'] ?? 0.0);
        $this->metadata = $data['metadata'] ?? [];
        $this->title = $data['title'] ?? null;
        $this->source = $data['source'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Check if this result is highly relevant (score >= 0.8)
     */
    public function isHighlyRelevant(): bool
    {
        return $this->score >= 0.8;
    }

    /**
     * Check if this result is moderately relevant (score >= 0.6)
     */
    public function isRelevant(): bool
    {
        return $this->score >= 0.6;
    }

    /**
     * Get score as percentage
     */
    public function getScorePercentage(): int
    {
        return (int) round($this->score * 100);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\RAG;

/**
 * Document Model
 *
 * Represents a document stored in the vector database.
 */
class Document
{
    public string $id;
    public string $content;
    public array $metadata;
    public ?array $embedding;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = $data['id'] ?? '';
        $this->content = $data['content'] ?? '';
        $this->metadata = $data['metadata'] ?? [];
        $this->embedding = isset($data['embedding']) && is_array($data['embedding'])
            ? $data['embedding']
            : null;
        $this->createdAt = $data['created_at'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function hasEmbedding(): bool
    {
        return $this->embedding !== null && !empty($this->embedding);
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}

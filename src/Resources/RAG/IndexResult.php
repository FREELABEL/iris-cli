<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\RAG;

/**
 * IndexResult Model
 *
 * Represents the result of indexing content.
 */
class IndexResult
{
    public string $vectorId;
    public bool $success;
    public int $tokensUsed;
    public ?string $message;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->vectorId = $data['vector_id'] ?? $data['id'] ?? '';
        $this->success = (bool) ($data['success'] ?? true);
        $this->tokensUsed = (int) ($data['tokens_used'] ?? 0);
        $this->message = $data['message'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isFailed(): bool
    {
        return !$this->success;
    }

    public function hasError(): bool
    {
        return $this->isFailed();
    }
}

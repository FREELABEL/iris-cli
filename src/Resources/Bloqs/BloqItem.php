<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

/**
 * BloqItem Model
 *
 * Represents an item within a BloqList.
 */
class BloqItem
{
    public int $id;
    public int $listId;
    public string $title;
    public ?string $content;
    public ?string $type;
    public int $position;
    public bool $isPublic;
    public ?array $metadata;
    public ?string $createdAt;
    public ?string $updatedAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->listId = (int) ($data['list_id'] ?? 0);
        $this->title = $data['title'] ?? '';
        $this->content = $data['content'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->position = (int) ($data['position'] ?? 0);
        $this->isPublic = (bool) ($data['is_public'] ?? false);
        $this->metadata = isset($data['metadata']) && is_array($data['metadata']) 
            ? $data['metadata'] 
            : null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function hasContent(): bool
    {
        return !empty($this->content);
    }
}

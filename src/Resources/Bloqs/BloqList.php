<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

/**
 * BloqList Model
 *
 * Represents a list within a Bloq.
 */
class BloqList
{
    public int $id;
    public int $bloqId;
    public string $title;
    public ?string $type;
    public int $position;
    public int $itemCount;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?array $items;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->bloqId = (int) ($data['bloq_id'] ?? 0);
        $this->title = $data['title'] ?? '';
        $this->type = $data['type'] ?? null;
        $this->position = (int) ($data['position'] ?? 0);
        $this->itemCount = (int) ($data['item_count'] ?? 0);
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        $this->items = $data['items'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function hasItems(): bool
    {
        return $this->itemCount > 0;
    }
}

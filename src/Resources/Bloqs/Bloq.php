<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

/**
 * Bloq Model
 *
 * Represents a Bloq - a container for organized lists and items.
 */
class Bloq
{
    public int $id;
    public string $title;
    public ?string $description;
    public int $userId;
    public bool $isPinned;
    public ?string $color;
    public ?string $icon;
    public int $itemCount;
    public int $listCount;
    public ?string $createdAt;
    public ?string $updatedAt;
    public ?array $lists;
    public ?array $items;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->isPinned = (bool) ($data['is_pinned'] ?? false);
        $this->color = $data['color'] ?? null;
        $this->icon = $data['icon'] ?? null;
        $this->itemCount = (int) ($data['item_count'] ?? 0);
        $this->listCount = (int) ($data['list_count'] ?? 0);
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        $this->lists = $data['lists'] ?? null;
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

    public function hasLists(): bool
    {
        return $this->listCount > 0;
    }

    public function hasItems(): bool
    {
        return $this->itemCount > 0;
    }
}

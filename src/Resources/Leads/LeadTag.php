<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

/**
 * LeadTag Model
 *
 * Represents a tag that can be applied to leads.
 */
class LeadTag
{
    public int $id;
    public string $name;
    public ?string $color;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->color = $data['color'] ?? null;
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
}

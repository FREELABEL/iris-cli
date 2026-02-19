<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

/**
 * LeadStage Model
 *
 * Represents a stage in the lead pipeline.
 */
class LeadStage
{
    public int $id;
    public string $name;
    public ?string $color;
    public int $position;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->color = $data['color'] ?? null;
        $this->position = (int) ($data['position'] ?? 0);
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

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

/**
 * LeadNote Model
 *
 * Represents a note associated with a lead.
 */
class LeadNote
{
    public int $id;
    public ?int $userId;
    public string $type;
    public string $content;
    public ?string $activityType;
    public ?string $activityIcon;
    public bool $isSystemGenerated;
    public ?string $createdAt;
    public ?string $updatedAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->type = $data['type'] ?? 'note';
        $this->content = $data['content'] ?? '';
        $this->activityType = $data['activity_type'] ?? null;
        $this->activityIcon = $data['activity_icon'] ?? null;
        $this->isSystemGenerated = (bool) ($data['is_system_generated'] ?? false);
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

    public function isOutreach(): bool
    {
        return $this->type === 'outreach';
    }

    public function isNote(): bool
    {
        return $this->type === 'note';
    }

    public function isSystemNote(): bool
    {
        return $this->isSystemGenerated;
    }
}

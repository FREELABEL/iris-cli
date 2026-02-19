<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

/**
 * LeadActivity Model
 *
 * Represents an activity associated with a lead.
 */
class LeadActivity
{
    public int $id;
    public int $leadId;
    public string $type;
    public string $content;
    public ?array $metadata;
    public ?int $userId;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->leadId = (int) ($data['lead_id'] ?? 0);
        $this->type = $data['type'] ?? '';
        $this->content = $data['content'] ?? '';
        $this->metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : null;
        $this->userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
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

    public function isAiMessage(): bool
    {
        return $this->type === 'ai_message';
    }

    public function isEmail(): bool
    {
        return $this->type === 'email_sent' || $this->type === 'email_received';
    }

    public function isCall(): bool
    {
        return $this->type === 'call';
    }

    public function isMeeting(): bool
    {
        return $this->type === 'meeting';
    }
}

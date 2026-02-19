<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

/**
 * Lead Model
 *
 * Represents a sales lead in the CRM.
 */
class Lead
{
    public int $id;
    public string $name;
    public ?string $nickname;
    public ?string $email;
    public ?string $phone;
    public ?string $company;
    public ?string $title;
    public ?string $source;
    public ?string $status;
    public ?string $leadType;
    public ?int $stageId;
    public ?string $stageName;
    public ?int $outreachAgentId;
    public ?float $score;
    public array $tags;
    public ?array $customFields;
    public ?array $contactInfo;
    public string|array|null $notes;
    public ?string $lastContactedAt;
    public ?string $createdAt;
    public ?string $updatedAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        // Use nickname as primary display name, fallback to name
        $this->nickname = $data['nickname'] ?? null;
        $this->name = $data['name'] ?? $data['nickname'] ?? '';

        // Get email/phone/company from direct fields or contact_info
        $contactInfo = $data['contact_info'] ?? [];
        if (is_string($contactInfo)) {
            $contactInfo = json_decode($contactInfo, true) ?? [];
        }
        $this->contactInfo = $contactInfo;

        $this->email = $data['email'] ?? $contactInfo['email'] ?? null;
        $this->phone = $data['phone'] ?? $contactInfo['phone'] ?? null;
        $this->company = $data['company'] ?? $contactInfo['company'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->source = $data['source'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->leadType = $data['lead_type'] ?? null;
        $this->stageId = isset($data['stage_id']) ? (int) $data['stage_id'] : null;
        $this->stageName = $data['stage_name'] ?? null;
        $this->outreachAgentId = isset($data['outreach_agent_id']) ? (int) $data['outreach_agent_id'] : null;
        $this->score = isset($data['score']) ? (float) $data['score'] : null;
        $this->tags = $data['tags'] ?? [];
        $this->customFields = isset($data['custom_fields']) && is_array($data['custom_fields'])
            ? $data['custom_fields']
            : null;
        $this->notes = $data['notes'] ?? null;
        $this->lastContactedAt = $data['last_contacted_at'] ?? null;
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

    public function hasEmail(): bool
    {
        return !empty($this->email);
    }

    public function hasPhone(): bool
    {
        return !empty($this->phone);
    }

    public function isHot(): bool
    {
        return $this->score !== null && $this->score > 80;
    }

    public function hasTag(string $tagName): bool
    {
        return in_array($tagName, $this->tags, true);
    }
}

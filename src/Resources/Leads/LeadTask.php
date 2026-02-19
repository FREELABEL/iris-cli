<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

/**
 * LeadTask Model
 *
 * Represents a task associated with a lead.
 */
class LeadTask
{
    public int $id;
    public int $leadId;
    public string $title;
    public ?string $description;
    public string $status;
    public ?string $dueDate;
    public int $position;
    public ?string $completedAt;
    public ?string $createdAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->leadId = (int) ($data['lead_id'] ?? 0);
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->status = $data['status'] ?? 'pending';
        $this->dueDate = $data['due_date'] ?? null;
        $this->position = (int) ($data['position'] ?? 0);
        $this->completedAt = $data['completed_at'] ?? null;
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

    public function isOverdue(): bool
    {
        if (!$this->dueDate || $this->isCompleted()) {
            return false;
        }

        return strtotime($this->dueDate) < time();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' || !empty($this->completedAt);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}

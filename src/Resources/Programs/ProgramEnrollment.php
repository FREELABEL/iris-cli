<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Programs;

/**
 * ProgramEnrollment Model
 *
 * Represents a user's enrollment in a program.
 */
class ProgramEnrollment
{
    public int $id;
    public int $programId;
    public int $userId;
    public ?int $packageId;
    public ?string $status;
    public ?float $amountPaid;
    public ?string $enrolledAt;
    public ?string $expiresAt;
    public ?string $cancelledAt;
    public ?array $customFields;
    public ?array $enrollmentData;
    public ?string $createdAt;
    public ?string $updatedAt;

    // Relationships
    public ?array $program;
    public ?array $user;
    public ?array $package;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->programId = (int) ($data['program_id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->packageId = isset($data['package_id']) ? (int) $data['package_id'] : null;
        $this->status = $data['status'] ?? null;
        $this->amountPaid = isset($data['amount_paid']) ? (float) $data['amount_paid'] : null;
        $this->enrolledAt = $data['enrolled_at'] ?? null;
        $this->expiresAt = $data['expires_at'] ?? null;
        $this->cancelledAt = $data['cancelled_at'] ?? null;
        
        $this->customFields = isset($data['custom_fields']) && is_array($data['custom_fields'])
            ? $data['custom_fields']
            : null;
        
        $this->enrollmentData = isset($data['enrollment_data']) && is_array($data['enrollment_data'])
            ? $data['enrollment_data']
            : null;

        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;

        // Relationships
        $this->program = $data['program'] ?? null;
        $this->user = $data['user'] ?? null;
        $this->package = $data['package'] ?? null;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Check if enrollment is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if enrollment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelledAt !== null;
    }

    /**
     * Check if enrollment has expired.
     */
    public function hasExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return strtotime($this->expiresAt) < time();
    }

    /**
     * Check if enrollment is paid.
     */
    public function isPaid(): bool
    {
        return $this->amountPaid !== null && $this->amountPaid > 0;
    }

    /**
     * Get enrollment duration in days.
     */
    public function getDurationDays(): ?int
    {
        if ($this->enrolledAt === null) {
            return null;
        }

        $endDate = $this->cancelledAt ?? $this->expiresAt ?? 'now';
        $start = strtotime($this->enrolledAt);
        $end = strtotime($endDate);

        return (int) ceil(($end - $start) / 86400);
    }

    /**
     * Get a specific custom field value.
     */
    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return $this->customFields[$key] ?? $default;
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Payments;

/**
 * Represents an agent wallet transaction.
 */
class Transaction
{
    public string $transactionId;
    public int $agentId;
    public int $walletId;
    public int $userId;
    public string $type;
    public int $amountCents;
    public int $balanceBeforeCents;
    public int $balanceAfterCents;
    public ?string $counterpartyType;
    public ?int $counterpartyId;
    public string $status;
    public ?string $failureReason;
    public ?string $traceId;
    public array $metadata;
    public ?string $createdAt;
    public ?string $updatedAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->transactionId = $data['transaction_id'] ?? $data['id'] ?? '';
        $this->agentId = (int) ($data['agent_id'] ?? 0);
        $this->walletId = (int) ($data['wallet_id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->type = $data['type'] ?? '';
        $this->amountCents = (int) ($data['amount_cents'] ?? 0);
        $this->balanceBeforeCents = (int) ($data['balance_before_cents'] ?? 0);
        $this->balanceAfterCents = (int) ($data['balance_after_cents'] ?? 0);
        $this->counterpartyType = $data['counterparty_type'] ?? null;
        $this->counterpartyId = isset($data['counterparty_id']) ? (int) $data['counterparty_id'] : null;
        $this->status = $data['status'] ?? '';
        $this->failureReason = $data['failure_reason'] ?? null;
        $this->traceId = $data['trace_id'] ?? null;
        $this->metadata = $data['metadata'] ?? [];
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    /**
     * Get amount formatted as dollars.
     */
    public function amountDollars(): float
    {
        return $this->amountCents / 100;
    }

    /**
     * Check if this is a credit (money in).
     */
    public function isCredit(): bool
    {
        return in_array($this->type, ['fund', 'refund']);
    }

    /**
     * Check if this is a debit (money out).
     */
    public function isDebit(): bool
    {
        return !$this->isCredit();
    }

    /**
     * Check if transaction completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get raw attribute value.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}

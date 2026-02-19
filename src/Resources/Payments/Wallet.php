<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Payments;

/**
 * Represents an agent wallet.
 *
 * @example
 * ```php
 * $wallet = $iris->payments->getWallet(11);
 * echo "Balance: $" . ($wallet->balanceCents / 100) . "\n";
 * echo "Status: {$wallet->status}\n";
 * ```
 */
class Wallet
{
    public int $id;
    public int $agentId;
    public int $userId;
    public int $balanceCents;
    public string $currency;
    public ?int $dailyLimitCents;
    public ?int $monthlyLimitCents;
    public ?int $perTransactionLimitCents;
    public bool $autoFundEnabled;
    public ?int $autoFundThresholdCents;
    public ?int $autoFundAmountCents;
    public string $status;
    public ?string $frozenReason;
    public int $totalFundedCents;
    public int $totalSpentCents;
    public array $metadata;
    public ?string $createdAt;
    public ?string $updatedAt;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->id = (int) ($data['id'] ?? 0);
        $this->agentId = (int) ($data['agent_id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->balanceCents = (int) ($data['balance_cents'] ?? 0);
        $this->currency = $data['currency'] ?? 'credits';
        $this->dailyLimitCents = isset($data['daily_limit_cents']) ? (int) $data['daily_limit_cents'] : null;
        $this->monthlyLimitCents = isset($data['monthly_limit_cents']) ? (int) $data['monthly_limit_cents'] : null;
        $this->perTransactionLimitCents = isset($data['per_transaction_limit_cents']) ? (int) $data['per_transaction_limit_cents'] : null;
        $this->autoFundEnabled = (bool) ($data['auto_fund_enabled'] ?? false);
        $this->autoFundThresholdCents = isset($data['auto_fund_threshold_cents']) ? (int) $data['auto_fund_threshold_cents'] : null;
        $this->autoFundAmountCents = isset($data['auto_fund_amount_cents']) ? (int) $data['auto_fund_amount_cents'] : null;
        $this->status = $data['status'] ?? 'active';
        $this->frozenReason = $data['frozen_reason'] ?? null;
        $this->totalFundedCents = (int) ($data['total_funded_cents'] ?? 0);
        $this->totalSpentCents = (int) ($data['total_spent_cents'] ?? 0);
        $this->metadata = $data['metadata'] ?? [];
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    /**
     * Get balance formatted as dollars.
     */
    public function balanceDollars(): float
    {
        return $this->balanceCents / 100;
    }

    /**
     * Check if wallet is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if wallet is frozen.
     */
    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    /**
     * Check if wallet can afford a given amount.
     */
    public function canAfford(int $amountCents): bool
    {
        return $this->isActive() && $this->balanceCents >= $amountCents;
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

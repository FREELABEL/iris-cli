<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Payments;

/**
 * Collection of agent wallet transactions.
 */
class TransactionCollection
{
    /** @var Transaction[] */
    public array $items;
    public array $meta;

    /**
     * @param Transaction[] $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * Get total count.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Sum all transaction amounts in cents.
     */
    public function totalCents(): int
    {
        return array_sum(array_map(fn(Transaction $t) => $t->amountCents, $this->items));
    }
}

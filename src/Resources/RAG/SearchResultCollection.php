<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\RAG;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * SearchResult Collection
 *
 * A collection of SearchResult instances.
 */
class SearchResultCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<SearchResult>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<SearchResult> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<SearchResult>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?SearchResult
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn(SearchResult $result) => $result->toArray(), $this->items);
    }

    /**
     * Get only highly relevant results (score >= 0.8)
     */
    public function highlyRelevant(): self
    {
        return new self(
            array_filter($this->items, fn(SearchResult $result) => $result->isHighlyRelevant()),
            $this->meta
        );
    }

    /**
     * Get only relevant results (score >= 0.6)
     */
    public function relevant(): self
    {
        return new self(
            array_filter($this->items, fn(SearchResult $result) => $result->isRelevant()),
            $this->meta
        );
    }

    /**
     * Sort by score descending
     */
    public function sortByScore(): self
    {
        $items = $this->items;
        usort($items, fn(SearchResult $a, SearchResult $b) => $b->score <=> $a->score);
        
        return new self($items, $this->meta);
    }

    /**
     * Get top N results
     */
    public function top(int $n): self
    {
        return new self(
            array_slice($this->items, 0, $n),
            $this->meta
        );
    }

    /**
     * Get average score
     */
    public function averageScore(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $total = array_sum(array_map(fn(SearchResult $result) => $result->score, $this->items));
        return $total / $this->count();
    }
}

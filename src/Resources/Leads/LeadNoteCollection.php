<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * LeadNote Collection
 *
 * A collection of LeadNote instances.
 */
class LeadNoteCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<LeadNote>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<LeadNote> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<LeadNote>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?LeadNote
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
        return array_map(fn(LeadNote $note) => $note->toArray(), $this->items);
    }

    /**
     * Get only outreach notes
     */
    public function outreach(): self
    {
        return new self(
            array_values(array_filter($this->items, fn(LeadNote $note) => $note->isOutreach())),
            $this->meta
        );
    }

    /**
     * Get only regular notes (non-outreach)
     */
    public function notes(): self
    {
        return new self(
            array_values(array_filter($this->items, fn(LeadNote $note) => $note->isNote())),
            $this->meta
        );
    }

    /**
     * Get only system-generated notes
     */
    public function systemGenerated(): self
    {
        return new self(
            array_values(array_filter($this->items, fn(LeadNote $note) => $note->isSystemNote())),
            $this->meta
        );
    }

    /**
     * Get only user-created notes (non-system)
     */
    public function userCreated(): self
    {
        return new self(
            array_values(array_filter($this->items, fn(LeadNote $note) => !$note->isSystemNote())),
            $this->meta
        );
    }

    /**
     * Search notes by content
     */
    public function search(string $query): self
    {
        $query = strtolower($query);
        return new self(
            array_values(array_filter($this->items, fn(LeadNote $note) =>
                str_contains(strtolower($note->content), $query)
            )),
            $this->meta
        );
    }

    /**
     * Sort by created date (newest first)
     */
    public function sortByNewest(): self
    {
        $items = $this->items;
        usort($items, fn(LeadNote $a, LeadNote $b) =>
            strtotime($b->createdAt ?? '0') <=> strtotime($a->createdAt ?? '0')
        );

        return new self($items, $this->meta);
    }

    /**
     * Sort by created date (oldest first)
     */
    public function sortByOldest(): self
    {
        $items = $this->items;
        usort($items, fn(LeadNote $a, LeadNote $b) =>
            strtotime($a->createdAt ?? '0') <=> strtotime($b->createdAt ?? '0')
        );

        return new self($items, $this->meta);
    }
}

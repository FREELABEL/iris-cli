<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Articles;

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Article Collection
 *
 * A collection of Article models with pagination support.
 */
class ArticleCollection implements IteratorAggregate, Countable
{
    /** @var Article[] */
    private array $articles;
    private array $meta;

    /**
     * @param Article[] $articles
     * @param array $meta
     */
    public function __construct(array $articles, array $meta = [])
    {
        $this->articles = $articles;
        $this->meta = $meta;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->articles);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->articles);
    }

    /**
     * @return Article[]
     */
    public function all(): array
    {
        return $this->articles;
    }

    /**
     * @return Article|null
     */
    public function first(): ?Article
    {
        return $this->articles[0] ?? null;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_map(fn($article) => $article->toArray(), $this->articles);
    }
}
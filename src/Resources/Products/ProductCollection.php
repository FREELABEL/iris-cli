<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Products;

use IteratorAggregate;
use Countable;
use ArrayIterator;

class ProductCollection implements IteratorAggregate, Countable
{
    /** @var Product[] */
    private array $products;
    private array $meta;

    public function __construct(array $products, array $meta = [])
    {
        $this->products = $products;
        $this->meta = $meta;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->products);
    }

    public function count(): int
    {
        return count($this->products);
    }

    /** @return Product[] */
    public function all(): array
    {
        return $this->products;
    }

    public function first(): ?Product
    {
        return $this->products[0] ?? null;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn($p) => $p->toArray(), $this->products);
    }
}

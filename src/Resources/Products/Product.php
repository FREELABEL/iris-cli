<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Products;

class Product
{
    public int $id;
    public ?int $user_id;
    public ?int $profile_id;
    public string $title;
    public ?string $subtitle;
    public ?string $description;
    public ?string $short_description;
    public ?string $photo;
    public float $price;
    public ?float $retail_price;
    public ?int $quantity;
    public ?string $tags;
    public ?string $currency_code;
    public bool $is_active;
    public ?string $created_at;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->profile_id = isset($data['profile_id']) ? (int) $data['profile_id'] : null;
        $this->title = $data['title'] ?? '';
        $this->subtitle = $data['subtitle'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->short_description = $data['short_description'] ?? null;
        $this->photo = $data['photo'] ?? null;
        $this->price = (float) ($data['price'] ?? 0);
        $this->retail_price = isset($data['retail_price']) ? (float) $data['retail_price'] : null;
        $this->quantity = isset($data['quantity']) ? (int) $data['quantity'] : null;
        $this->tags = $data['tags'] ?? null;
        $this->currency_code = $data['currency_code'] ?? 'USD';
        $this->is_active = (bool) ($data['is_active'] ?? true);
        $this->created_at = $data['created_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'profile_id' => $this->profile_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'photo' => $this->photo,
            'price' => $this->price,
            'retail_price' => $this->retail_price,
            'quantity' => $this->quantity,
            'tags' => $this->tags,
            'currency_code' => $this->currency_code,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}

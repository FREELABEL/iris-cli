<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Services;

/**
 * Service Model
 *
 * Represents a service offering.
 */
class Service
{
    public int $id;
    public ?int $user_id;
    public ?int $profile_id;
    public ?int $bloq_id;
    public string $title;
    public ?string $description;
    public ?string $photo;
    public float $price;
    public ?float $price_max;
    public bool $custom_request_required;
    public int $delivery_amount;
    public string $delivery_frequency;
    public int $status;
    public ?string $payment_recipient_type;
    public ?string $keywords;
    public ?array $checklist;
    public ?array $addons;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? $data['pk'] ?? 0);
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->profile_id = isset($data['profile_id']) ? (int) $data['profile_id'] : null;
        $this->bloq_id = isset($data['bloq_id']) ? (int) $data['bloq_id'] : null;
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->photo = $data['photo'] ?? null;
        $this->price = (float) ($data['price'] ?? 0);
        $this->price_max = isset($data['price_max']) ? (float) $data['price_max'] : null;
        $this->custom_request_required = (bool) ($data['custom_request_required'] ?? false);
        $this->delivery_amount = (int) ($data['delivery_amount'] ?? 3);
        $this->delivery_frequency = $data['delivery_frequency'] ?? 'days';
        $this->status = (int) ($data['status'] ?? 1);
        $this->payment_recipient_type = $data['payment_recipient_type'] ?? 'auto';
        $this->keywords = $data['keywords'] ?? null;
        
        // Parse checklist and addons if they're JSON strings
        $this->checklist = $this->parseJsonField($data['checklist'] ?? null);
        $this->addons = $this->parseJsonField($data['addons'] ?? null);
        
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    private function parseJsonField($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'profile_id' => $this->profile_id,
            'bloq_id' => $this->bloq_id,
            'title' => $this->title,
            'description' => $this->description,
            'photo' => $this->photo,
            'price' => $this->price,
            'price_max' => $this->price_max,
            'custom_request_required' => $this->custom_request_required,
            'delivery_amount' => $this->delivery_amount,
            'delivery_frequency' => $this->delivery_frequency,
            'status' => $this->status,
            'payment_recipient_type' => $this->payment_recipient_type,
            'keywords' => $this->keywords,
            'checklist' => $this->checklist,
            'addons' => $this->addons,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

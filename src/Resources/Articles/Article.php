<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Articles;

/**
 * Article Model
 *
 * Represents an article resource from the API.
 */
class Article
{
    public int $id;
    public int $profile_id;
    public string $title;
    public string $content;
    public ?string $photo;
    public ?bool $is_bulletin;
    public int $status;
    public int $views;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->profile_id = $data['profile_id'];
        $this->title = $data['title'];
        $this->content = $data['content'];
        $this->photo = $data['photo'] ?? null;
        $this->is_bulletin = $data['is_bulletin'] ?? null;
        $this->status = $data['status'];
        $this->views = $data['views'];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'title' => $this->title,
            'content' => $this->content,
            'photo' => $this->photo,
            'is_bulletin' => $this->is_bulletin,
            'status' => $this->status,
            'views' => $this->views,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
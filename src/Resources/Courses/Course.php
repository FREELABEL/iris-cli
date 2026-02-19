<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Courses;

/**
 * Course Model
 *
 * Represents a course in the marketplace.
 */
class Course
{
    public ?int $id;
    public ?string $title;
    public ?string $description;
    public ?array $instructor;
    public ?string $difficultyLevel;
    public ?int $estimatedDurationMinutes;
    public ?int $chapterCount;
    public ?int $contentCount;
    public ?string $thumbnailUrl;
    public ?float $price;
    public ?bool $isFree;
    public ?bool $isEnrolled;
    public ?array $learningObjectives;
    public ?array $chapters;
    public ?string $createdAt;
    public ?string $updatedAt;

    /**
     * @param array $data Course data from API
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->instructor = $data['instructor'] ?? null;
        $this->difficultyLevel = $data['difficulty_level'] ?? null;
        $this->estimatedDurationMinutes = $data['estimated_duration_minutes'] ?? null;
        $this->chapterCount = $data['chapter_count'] ?? null;
        $this->contentCount = $data['content_count'] ?? null;
        $this->thumbnailUrl = $data['thumbnail_url'] ?? null;
        $this->price = isset($data['price']) ? (float)$data['price'] : null;
        $this->isFree = $data['is_free'] ?? null;
        $this->isEnrolled = $data['is_enrolled'] ?? null;
        $this->learningObjectives = $data['learning_objectives'] ?? null;
        $this->chapters = $data['chapters'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    /**
     * Check if the course is free.
     */
    public function isFree(): bool
    {
        return $this->isFree === true || $this->price === 0.0 || $this->price === null;
    }

    /**
     * Check if the course is paid.
     */
    public function isPaid(): bool
    {
        return !$this->isFree();
    }

    /**
     * Get the difficulty level display name.
     */
    public function getDifficultyDisplay(): string
    {
        return match($this->difficultyLevel) {
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
            'expert' => 'Expert',
            default => 'Unknown'
        };
    }

    /**
     * Get estimated duration in hours.
     */
    public function getDurationHours(): ?float
    {
        if ($this->estimatedDurationMinutes === null) {
            return null;
        }
        return round($this->estimatedDurationMinutes / 60, 1);
    }

    /**
     * Get instructor name.
     */
    public function getInstructorName(): ?string
    {
        return $this->instructor['name'] ?? null;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'instructor' => $this->instructor,
            'difficulty_level' => $this->difficultyLevel,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'chapter_count' => $this->chapterCount,
            'content_count' => $this->contentCount,
            'thumbnail_url' => $this->thumbnailUrl,
            'price' => $this->price,
            'is_free' => $this->isFree,
            'is_enrolled' => $this->isEnrolled,
            'learning_objectives' => $this->learningObjectives,
            'chapters' => $this->chapters,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

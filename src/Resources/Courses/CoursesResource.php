<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Courses;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Courses Resource
 *
 * Manage courses, enrollments, chapters, and content.
 *
 * @example
 * ```php
 * // List marketplace courses
 * $courses = $iris->courses->list(['difficulty' => 'beginner']);
 *
 * // Get course details
 * $course = $iris->courses->get(123);
 *
 * // Enroll in a course
 * $enrollment = $iris->courses->enroll(123, 456);
 *
 * // Track progress
 * $iris->courses->updateProgress(123, 456, [
 *     'chapter_id' => 1,
 *     'content_id' => 10,
 *     'completed' => true
 * ]);
 *
 * // Create a course
 * $course = $iris->courses->create([
 *     'program_id' => 5,
 *     'difficulty_level' => 'intermediate',
 *     'learning_objectives' => ['Learn X', 'Master Y']
 * ]);
 * ```
 */
class CoursesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List published courses for marketplace.
     *
     * @param array{
     *     difficulty?: string,
     *     search?: string,
     *     instructor_id?: int,
     *     per_page?: int,
     *     page?: int
     * } $filters Filter options
     * @return CourseCollection
     */
    public function list(array $filters = []): CourseCollection
    {
        $response = $this->http->get("/api/v1/courses", $filters);

        return new CourseCollection(
            array_map(fn($data) => new Course($data), $response['data'] ?? []),
            $response['pagination'] ?? []
        );
    }

    /**
     * Search for courses by title or description.
     *
     * @param string $query Search query
     * @param array{
     *     difficulty?: string,
     *     instructor_id?: int
     * } $filters Additional filters
     * @return CourseCollection
     */
    public function search(string $query, array $filters = []): CourseCollection
    {
        return $this->list(array_merge(['search' => $query], $filters));
    }

    /**
     * Get course detail with chapters.
     *
     * @param int $courseId Course ID
     * @return Course
     */
    public function get(int $courseId): Course
    {
        $response = $this->http->get("/api/v1/courses/{$courseId}");
        return new Course($response['course'] ?? $response);
    }

    /**
     * Create a new course.
     *
     * @param array{
     *     program_id: int,
     *     difficulty_level: string,
     *     estimated_duration_minutes?: int,
     *     learning_objectives?: array,
     *     thumbnail_url?: string,
     *     instructor_id?: int
     * } $data Course data
     * @return Course
     */
    public function create(array $data): Course
    {
        $response = $this->http->post("/api/v1/courses", $data);
        return new Course($response['course'] ?? $response);
    }

    /**
     * Update an existing course.
     *
     * @param int $courseId Course ID
     * @param array $data Course data to update
     * @return Course
     */
    public function update(int $courseId, array $data): Course
    {
        $response = $this->http->put("/api/v1/courses/{$courseId}", $data);
        return new Course($response['course'] ?? $response);
    }

    /**
     * Delete a course.
     *
     * @param int $courseId Course ID
     * @return bool Success status
     */
    public function delete(int $courseId): bool
    {
        $this->http->delete("/api/v1/courses/{$courseId}");
        return true;
    }

    /**
     * Enroll a user in a course.
     *
     * @param int $courseId Course ID
     * @param int $userId User ID
     * @return array Enrollment data
     */
    public function enroll(int $courseId, int $userId): array
    {
        return $this->http->post("/api/v1/courses/{$courseId}/enroll", [
            'user_id' => $userId
        ]);
    }

    /**
     * Get user's progress in a course.
     *
     * @param int $courseId Course ID
     * @param int $userId User ID
     * @return array Progress data
     */
    public function getProgress(int $courseId, int $userId): array
    {
        return $this->http->get("/api/v1/courses/{$courseId}/progress", [
            'user_id' => $userId
        ]);
    }

    /**
     * Update user's progress in a course.
     *
     * @param int $courseId Course ID
     * @param int $userId User ID
     * @param array{
     *     chapter_id?: int,
     *     content_id?: int,
     *     completed?: bool,
     *     progress_percentage?: float,
     *     time_spent_seconds?: int
     * } $data Progress data
     * @return array Updated progress
     */
    public function updateProgress(int $courseId, int $userId, array $data): array
    {
        return $this->http->post("/api/v1/courses/{$courseId}/progress", array_merge([
            'user_id' => $userId
        ], $data));
    }

    /**
     * Add a chapter to a course.
     *
     * @param int $courseId Course ID
     * @param array{
     *     title: string,
     *     description?: string,
     *     display_order?: int
     * } $data Chapter data
     * @return array Chapter data
     */
    public function addChapter(int $courseId, array $data): array
    {
        return $this->http->post("/api/v1/courses/{$courseId}/chapters", $data);
    }

    /**
     * Update a chapter.
     *
     * @param int $courseId Course ID
     * @param int $chapterId Chapter ID
     * @param array $data Chapter data to update
     * @return array Updated chapter
     */
    public function updateChapter(int $courseId, int $chapterId, array $data): array
    {
        return $this->http->put("/api/v1/courses/{$courseId}/chapters/{$chapterId}", $data);
    }

    /**
     * Delete a chapter.
     *
     * @param int $courseId Course ID
     * @param int $chapterId Chapter ID
     * @return bool Success status
     */
    public function deleteChapter(int $courseId, int $chapterId): bool
    {
        $this->http->delete("/api/v1/courses/{$courseId}/chapters/{$chapterId}");
        return true;
    }

    /**
     * Reorder chapters in a course.
     *
     * @param int $courseId Course ID
     * @param array $chapterOrder Array of chapter IDs in desired order
     * @return bool Success status
     */
    public function reorderChapters(int $courseId, array $chapterOrder): bool
    {
        $this->http->put("/api/v1/courses/{$courseId}/chapters/reorder", [
            'chapter_order' => $chapterOrder
        ]);
        return true;
    }

    /**
     * Add content to a chapter.
     *
     * @param int $courseId Course ID
     * @param int $chapterId Chapter ID
     * @param array{
     *     content_type: string,
     *     content_data: array,
     *     display_order?: int
     * } $data Content data
     * @return array Content data
     */
    public function addContentToChapter(int $courseId, int $chapterId, array $data): array
    {
        return $this->http->post("/api/v1/courses/{$courseId}/chapters/{$chapterId}/content", $data);
    }

    /**
     * Remove content from a chapter.
     *
     * @param int $courseId Course ID
     * @param int $chapterId Chapter ID
     * @param int $contentId Content ID
     * @return bool Success status
     */
    public function removeContentFromChapter(int $courseId, int $chapterId, int $contentId): bool
    {
        $this->http->delete("/api/v1/courses/{$courseId}/chapters/{$chapterId}/content/{$contentId}");
        return true;
    }

    /**
     * Reorder content in a chapter.
     *
     * @param int $courseId Course ID
     * @param int $chapterId Chapter ID
     * @param array $contentOrder Array of content IDs in desired order
     * @return bool Success status
     */
    public function reorderChapterContent(int $courseId, int $chapterId, array $contentOrder): bool
    {
        $this->http->put("/api/v1/courses/{$courseId}/chapters/{$chapterId}/content/reorder", [
            'content_order' => $contentOrder
        ]);
        return true;
    }
}

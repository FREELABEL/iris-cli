<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Tools;

/**
 * Result from recruitment query generation tool.
 */
class RecruitmentResult
{
    public bool $success;
    public string $platform;
    public array $requirements;
    public array $searchUrls;
    public array $booleanQueries;
    public string $extractionScript;
    public string $instructions;
    public ?string $content;
    public ?string $title;
    public ?string $error;

    public function __construct(array $data)
    {
        $this->success = $data['success'] ?? false;
        $this->platform = $data['platform'] ?? 'linkedin';
        $this->requirements = $data['requirements'] ?? [];
        $this->searchUrls = $data['search_urls'] ?? [];
        $this->booleanQueries = $data['boolean_queries'] ?? [];
        $this->extractionScript = $data['extraction_script'] ?? '';
        $this->instructions = $data['instructions'] ?? '';
        $this->content = $data['content'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->error = $data['error'] ?? null;
    }

    /**
     * Get must-have skills from requirements.
     */
    public function getMustHaveSkills(): array
    {
        return $this->requirements['must_have_skills'] ?? [];
    }

    /**
     * Get nice-to-have skills from requirements.
     */
    public function getNiceToHaveSkills(): array
    {
        return $this->requirements['nice_to_have_skills'] ?? [];
    }

    /**
     * Get title keywords for searching.
     */
    public function getTitleKeywords(): array
    {
        return $this->requirements['title_keywords'] ?? [];
    }

    /**
     * Get the primary search URL.
     */
    public function getPrimaryUrl(): ?string
    {
        return $this->searchUrls[0]['url'] ?? null;
    }

    /**
     * Get all search URLs as simple array.
     */
    public function getUrls(): array
    {
        return array_map(fn($u) => $u['url'] ?? '', $this->searchUrls);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'platform' => $this->platform,
            'requirements' => $this->requirements,
            'search_urls' => $this->searchUrls,
            'boolean_queries' => $this->booleanQueries,
            'extraction_script' => $this->extractionScript,
            'instructions' => $this->instructions,
            'content' => $this->content,
            'title' => $this->title,
            'error' => $this->error,
        ];
    }
}

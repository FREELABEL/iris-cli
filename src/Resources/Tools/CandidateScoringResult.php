<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Tools;

/**
 * Result from candidate scoring tool.
 */
class CandidateScoringResult
{
    public bool $success;
    public array $rankedCandidates;
    public array $strongMatches;
    public array $goodMatches;
    public array $potentialMatches;
    public array $lowMatches;
    public array $summary;
    public ?string $reportUrl;
    public ?string $markdownUrl;
    public ?string $report;
    public ?string $error;

    public function __construct(array $data)
    {
        $this->success = $data['success'] ?? true;

        $candidates = $data['candidates'] ?? $data['ranked_candidates'] ?? [];
        $this->rankedCandidates = $candidates['all'] ?? [];
        $this->strongMatches = $candidates['strong'] ?? [];
        $this->goodMatches = $candidates['good'] ?? [];
        $this->potentialMatches = $candidates['potential'] ?? [];
        $this->lowMatches = $candidates['low'] ?? [];

        $this->summary = $data['summary'] ?? [
            'total_candidates' => count($this->rankedCandidates),
            'strong_matches' => count($this->strongMatches),
            'good_matches' => count($this->goodMatches),
            'potential_matches' => count($this->potentialMatches),
        ];

        $this->reportUrl = $data['url'] ?? null;
        $this->markdownUrl = $data['markdown_url'] ?? null;
        $this->report = $data['report'] ?? null;
        $this->error = $data['error'] ?? null;
    }

    /**
     * Get total number of candidates scored.
     */
    public function getTotalCount(): int
    {
        return count($this->rankedCandidates);
    }

    /**
     * Get top N candidates by score.
     */
    public function getTopCandidates(int $limit = 10): array
    {
        return array_slice($this->rankedCandidates, 0, $limit);
    }

    /**
     * Get candidates above a score threshold.
     */
    public function getCandidatesAboveScore(float $minScore): array
    {
        return array_filter(
            $this->rankedCandidates,
            fn($c) => ($c['overall_score'] ?? 0) >= $minScore
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'ranked_candidates' => $this->rankedCandidates,
            'strong_matches' => $this->strongMatches,
            'good_matches' => $this->goodMatches,
            'potential_matches' => $this->potentialMatches,
            'low_matches' => $this->lowMatches,
            'summary' => $this->summary,
            'report_url' => $this->reportUrl,
            'markdown_url' => $this->markdownUrl,
            'error' => $this->error,
        ];
    }
}

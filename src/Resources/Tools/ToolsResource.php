<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Tools;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Tools Resource
 *
 * Invoke Neuron AI tools directly for specialized operations like:
 * - Recruitment query generation
 * - Candidate scoring
 * - Market research
 * - Lead enrichment
 *
 * @example
 * ```php
 * // Generate recruitment search queries from a job description
 * $result = $iris->tools->recruitment([
 *     'job_description' => 'Senior Solutions Engineer...',
 *     'platform' => 'linkedin',
 *     'location' => 'Austin, TX',
 *     'experience_level' => 'senior',
 * ]);
 *
 * // Or from a PDF file
 * $result = $iris->tools->recruitment([
 *     'job_description_file' => '/path/to/job.pdf',
 *     'platform' => 'linkedin',
 * ]);
 * ```
 */
class ToolsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Invoke a tool by name.
     *
     * @param string $toolName Tool name (e.g., 'recruitmentQueryGenerator', 'candidateScorer')
     * @param array $params Tool parameters
     * @return array Tool result
     */
    public function invoke(string $toolName, array $params = []): array
    {
        return $this->http->post('/api/v1/tools/invoke', [
            'tool' => $toolName,
            'params' => $params,
        ]);
    }

    /**
     * List available tools.
     *
     * @return array List of available tools with descriptions
     */
    public function list(): array
    {
        return $this->http->get('/api/v1/tools');
    }

    /**
     * Generate recruitment search queries from a job description.
     *
     * This tool analyzes a job description and generates:
     * - Platform-specific search URLs (LinkedIn, GitHub, Twitter)
     * - Boolean search queries for manual searching
     * - Browser extraction scripts for data collection
     * - Extracted job requirements for scoring
     *
     * @param array{
     *     job_description?: string,
     *     job_description_file?: string,
     *     platform?: string,
     *     location?: string,
     *     experience_level?: string
     * } $params Tool parameters
     * @return RecruitmentResult
     *
     * @example From text
     * ```php
     * $result = $iris->tools->recruitment([
     *     'job_description' => 'Senior Solutions Engineer at Austin, TX...',
     *     'platform' => 'linkedin',
     *     'location' => 'Austin, TX',
     *     'experience_level' => 'senior',
     * ]);
     *
     * echo "Search URLs:\n";
     * foreach ($result->searchUrls as $url) {
     *     echo "  - {$url['label']}: {$url['url']}\n";
     * }
     * ```
     *
     * @example From PDF file
     * ```php
     * $result = $iris->tools->recruitment([
     *     'job_description_file' => '/path/to/Senior Solutions Engineer.pdf',
     *     'platform' => 'linkedin',
     *     'location' => 'Austin, TX',
     * ]);
     * ```
     */
    public function recruitment(array $params = []): RecruitmentResult
    {
        // If a file path is provided, read it and send as base64
        if (isset($params['job_description_file']) && file_exists($params['job_description_file'])) {
            $filePath = $params['job_description_file'];
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $content = file_get_contents($filePath);

            // Send file content as base64
            $params['job_description_file_content'] = base64_encode($content);
            $params['job_description_file_extension'] = $extension;

            // Remove the file path since we're sending content
            unset($params['job_description_file']);
        }

        $response = $this->http->post('/api/v1/tools/recruitment/generate-queries', $params);
        return new RecruitmentResult($response);
    }

    /**
     * Score and rank candidates against job requirements.
     *
     * Takes candidate profiles (from browser extraction) and job requirements,
     * scores each candidate using a weighted algorithm, and generates a report.
     *
     * @param array{
     *     candidate_data: string,
     *     requirements: array,
     *     job_description?: string,
     *     scoring_weights?: array
     * } $params Tool parameters
     * @return CandidateScoringResult
     *
     * @example
     * ```php
     * // After extracting candidates from LinkedIn
     * $candidateJson = '[{"name": "Jane Smith", "title": "Solutions Engineer"...}]';
     *
     * $result = $iris->tools->scoreCandidates([
     *     'candidate_data' => $candidateJson,
     *     'requirements' => $recruitmentResult->requirements,
     * ]);
     *
     * echo "Strong Matches: " . count($result->strongMatches) . "\n";
     * foreach ($result->rankedCandidates as $candidate) {
     *     echo "  {$candidate['rank']}. {$candidate['name']} - {$candidate['overall_score']}%\n";
     * }
     * ```
     */
    public function scoreCandidates(array $params = []): CandidateScoringResult
    {
        $response = $this->http->post('/api/v1/tools/recruitment/score-candidates', $params);
        return new CandidateScoringResult($response);
    }

    /**
     * Enrich a lead with contact information using ReAct AI.
     *
     * Uses AI-powered search and scraping to find emails, phones, websites.
     *
     * @param int $leadId Lead ID to enrich
     * @param array{
     *     goal?: string,
     *     max_iterations?: int,
     *     use_native_http?: bool
     * } $options Enrichment options
     * @return array Enrichment result
     */
    public function enrichLead(int $leadId, array $options = []): array
    {
        return $this->http->post("/api/v1/leads/{$leadId}/enrich-react", $options);
    }

    /**
     * Research a topic and generate newsletter outline options.
     *
     * This tool performs comprehensive research using Tavily and generates
     * 3 distinct newsletter outline options for the user to choose from.
     * Returns a HITL (Human-in-the-Loop) response for outline selection.
     *
     * @param array{
     *     topic: string,
     *     source_url?: string,
     *     audience?: string,
     *     tone?: string,
     *     newsletter_length?: string
     * } $params Tool parameters
     * @return NewsletterResearchResult
     *
     * @example
     * ```php
     * $result = $iris->tools->newsletterResearch([
     *     'topic' => 'AI trends in healthcare 2025',
     *     'audience' => 'healthcare professionals',
     *     'tone' => 'professional',
     *     'newsletter_length' => 'standard',
     * ]);
     *
     * echo "Choose an outline:\n";
     * foreach ($result->outlineOptions as $option) {
     *     echo "  {$option['option_number']}. {$option['title']}\n";
     * }
     * ```
     */
    public function newsletterResearch(array $params = []): NewsletterResearchResult
    {
        $response = $this->http->post('/api/v1/tools/newsletter/research', $params);
        return new NewsletterResearchResult($response);
    }

    /**
     * Generate a complete newsletter from a selected outline.
     *
     * Takes the outline selection from newsletterResearch and generates
     * the full newsletter content as a background job with progress tracking.
     *
     * @param array{
     *     selected_option: int,
     *     outline_options: array,
     *     context: array,
     *     customization_notes?: string,
     *     recipient_email?: string,
     *     recipient_name?: string,
     *     sender_name?: string,
     *     lead_id?: int
     * } $params Tool parameters
     * @return array Background job response
     *
     * @example
     * ```php
     * // After user selects option 2 from newsletterResearch
     * $result = $iris->tools->newsletterWrite([
     *     'selected_option' => 2,
     *     'outline_options' => $researchResult->outlineOptions,
     *     'context' => $researchResult->context,
     *     'customization_notes' => 'Focus more on practical applications',
     *     'recipient_email' => 'john@example.com',
     *     'sender_name' => 'Alex',
     * ]);
     * ```
     */
    public function newsletterWrite(array $params = []): array
    {
        return $this->http->post('/api/v1/tools/newsletter/write', $params);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\RAG;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * RAG Resource
 *
 * Retrieval-Augmented Generation - semantic search and vector storage.
 *
 * @example
 * ```php
 * // Index content
 * $result = $iris->rag->index('This is important content', [
 *     'bloq_id' => 123,
 *     'title' => 'Meeting Notes',
 * ]);
 *
 * // Query knowledge base
 * $results = $iris->rag->query('What were the key takeaways?', [
 *     'bloq_id' => 123,
 * ]);
 *
 * // Index a file
 * $result = $iris->rag->indexFile('/path/to/document.pdf', [
 *     'type' => 'research',
 * ]);
 * ```
 */
class RAGResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Query knowledge base with semantic search.
     *
     * @param string $question Natural language question
     * @param array{
     *     bloq_id?: int,
     *     agent_id?: int,
     *     type?: string
     * } $filters Metadata filters
     * @param int $topK Number of results to return
     * @return SearchResultCollection
     */
    public function query(string $question, array $filters = [], int $topK = 5): SearchResultCollection
    {
        $data = array_merge(
            ['query' => $question, 'top_k' => $topK],
            $filters
        );

        $response = $this->http->post("/api/v1/vector/search", $data);

        $results = array_map(
            fn($data) => new SearchResult($data),
            $response['results'] ?? $response['data'] ?? $response
        );

        return new SearchResultCollection($results, $response['meta'] ?? []);
    }

    /**
     * Index new content for vector search.
     *
     * @param string $content Text content to index
     * @param array{
     *     bloq_id?: int,
     *     agent_id?: int,
     *     title?: string,
     *     type?: string
     * } $metadata Metadata for the content
     * @return IndexResult
     */
    public function index(string $content, array $metadata = []): IndexResult
    {
        $data = array_merge(['content' => $content], $metadata);
        $response = $this->http->post("/api/v1/vector/store", $data);

        return new IndexResult($response);
    }

    /**
     * Index a file (auto-extracts content).
     *
     * @param string $filePath Path to file
     * @param array $metadata Additional metadata
     * @return IndexResult
     */
    public function indexFile(string $filePath, array $metadata = []): IndexResult
    {
        $response = $this->http->upload("/api/v1/vector/store", $filePath, $metadata);

        return new IndexResult($response);
    }

    /**
     * Search for similar documents.
     *
     * @param string $query Search query
     * @param int $limit Max results
     * @param array{
     *     bloq_id?: int,
     *     agent_id?: int,
     *     type?: string
     * } $filters Metadata filters
     * @return SearchResultCollection
     */
    public function searchSimilar(string $query, int $limit = 5, array $filters = []): SearchResultCollection
    {
        $data = array_merge(
            ['query' => $query, 'limit' => $limit],
            $filters
        );

        $response = $this->http->post("/api/v1/search/", $data);

        $results = array_map(
            fn($data) => new SearchResult($data),
            $response['results'] ?? $response['data'] ?? $response
        );

        return new SearchResultCollection($results, $response['meta'] ?? []);
    }

    /**
     * Get a specific vector/document by ID.
     *
     * @param string $vectorId Vector/document ID
     * @return Document
     */
    public function getVector(string $vectorId): Document
    {
        $response = $this->http->get("/api/v1/vector/{$vectorId}");

        return new Document($response);
    }

    /**
     * Delete indexed content.
     *
     * @param string $vectorId Vector/document ID
     * @return bool
     */
    public function delete(string $vectorId): bool
    {
        $this->http->delete("/api/v1/vector/{$vectorId}");

        return true;
    }

    /**
     * Get search suggestions.
     *
     * @param string $query Partial query
     * @return array<string> Suggestions
     */
    public function suggestions(string $query): array
    {
        $response = $this->http->get("/api/v1/search/suggestions", ['query' => $query]);

        return $response['suggestions'] ?? $response;
    }

    /**
     * Perform a general search (alternative endpoint).
     *
     * @param string $query Search query
     * @param array{
     *     limit?: int,
     *     filters?: array,
     *     type?: string
     * } $options Search options
     * @return SearchResultCollection
     */
    public function search(string $query, array $options = []): SearchResultCollection
    {
        $data = array_merge(['query' => $query], $options);
        $response = $this->http->post("/api/v1/search/", $data);

        $results = array_map(
            fn($data) => new SearchResult($data),
            $response['results'] ?? $response['data'] ?? $response
        );

        return new SearchResultCollection($results, $response['meta'] ?? []);
    }
}

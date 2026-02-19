<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\RAG\SearchResult;
use IRIS\SDK\Resources\RAG\SearchResultCollection;
use IRIS\SDK\Resources\RAG\IndexResult;
use IRIS\SDK\Resources\RAG\Document;

class RAGResourceTest extends TestCase
{
    public function test_query_knowledge_base(): void
    {
        $this->mockResponse('POST', '/api/v1/vector/search', [
            'results' => [
                ['id' => '1', 'content' => 'Result 1', 'score' => 0.95],
                ['id' => '2', 'content' => 'Result 2', 'score' => 0.85],
            ],
        ]);

        $results = $this->iris->rag->query('What is the answer?');

        $this->assertInstanceOf(SearchResultCollection::class, $results);
        $this->assertCount(2, $results);
        $this->assertTrue($results->first()->isHighlyRelevant());
    }

    public function test_index_content(): void
    {
        $this->mockResponse('POST', '/api/v1/vector/store', [
            'vector_id' => 'vec_123',
            'success' => true,
            'tokens_used' => 150,
        ]);

        $result = $this->iris->rag->index('Important content', [
            'bloq_id' => 456,
            'title' => 'Meeting Notes',
        ]);

        $this->assertInstanceOf(IndexResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('vec_123', $result->vectorId);
        $this->assertEquals(150, $result->tokensUsed);
    }

    public function test_search_similar(): void
    {
        $this->mockResponse('POST', '/api/v1/search/', [
            'results' => [
                ['id' => '1', 'content' => 'Similar content', 'score' => 0.75],
            ],
        ]);

        $results = $this->iris->rag->searchSimilar('query text', 5);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->isRelevant());
    }

    public function test_get_vector(): void
    {
        $this->mockResponse('GET', '/api/v1/vector/vec_123', [
            'id' => 'vec_123',
            'content' => 'Stored content',
            'metadata' => ['type' => 'note'],
        ]);

        $doc = $this->iris->rag->getVector('vec_123');

        $this->assertInstanceOf(Document::class, $doc);
        $this->assertEquals('vec_123', $doc->id);
        $this->assertEquals('Stored content', $doc->content);
    }

    public function test_delete_vector(): void
    {
        $this->mockResponse('DELETE', '/api/v1/vector/vec_123', [
            'success' => true,
        ]);

        $result = $this->iris->rag->delete('vec_123');

        $this->assertTrue($result);
    }

    public function test_suggestions(): void
    {
        $this->mockResponse('GET', '/api/v1/search/suggestions', [
            'suggestions' => ['query 1', 'query 2', 'query 3'],
        ]);

        $suggestions = $this->iris->rag->suggestions('que');

        $this->assertCount(3, $suggestions);
        $this->assertEquals('query 1', $suggestions[0]);
    }

    public function test_search_result_helpers(): void
    {
        $result = new SearchResult([
            'id' => '1',
            'content' => 'Test',
            'score' => 0.95,
            'metadata' => [],
        ]);

        $this->assertTrue($result->isHighlyRelevant());
        $this->assertTrue($result->isRelevant());
        $this->assertEquals(95, $result->getScorePercentage());
    }
}

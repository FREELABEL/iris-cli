<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Integration;

use PHPUnit\Framework\TestCase;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;
use IRIS\SDK\Resources\Leads\Lead;

/**
 * Leads Integration Test
 *
 * Tests against the live API. Requires valid credentials in .env file.
 * Run with: vendor/bin/phpunit tests/Integration/LeadsIntegrationTest.php
 *
 * Environment variables:
 * - IRIS_ENV: 'local' or 'production'
 * - IRIS_API_KEY or IRIS_PROD_API_KEY/IRIS_LOCAL_API_KEY
 * - IRIS_USER_ID
 *
 * @group integration
 * @group leads
 */
class LeadsIntegrationTest extends TestCase
{
    private ?IRIS $iris = null;
    private ?int $testLeadId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Load .env from SDK directory
        $envPath = __DIR__ . '/../../.env';
        if (!file_exists($envPath)) {
            $this->markTestSkipped('No .env file found. Copy .env.example to .env and configure credentials.');
        }

        try {
            $this->iris = new IRIS([]);
        } catch (\Exception $e) {
            $this->markTestSkipped('Failed to initialize IRIS SDK: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $this->iris = null;
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_can_list_leads(): void
    {
        $leads = $this->iris->leads->list(['per_page' => 5]);

        $this->assertNotNull($leads);
        $this->assertIsIterable($leads);
    }

    /**
     * @test
     */
    public function it_can_search_leads(): void
    {
        // First get any lead to know a name to search for
        $leads = $this->iris->leads->list(['per_page' => 1]);

        if (count($leads) === 0) {
            $this->markTestSkipped('No leads available to test search');
        }

        $firstLead = $leads->first();
        $searchTerm = substr($firstLead->name ?? $firstLead->nickname ?? 'a', 0, 3);

        // Search for that lead
        $results = $this->iris->leads->search(['search' => $searchTerm, 'per_page' => 5]);

        $this->assertIsArray($results);
        // Results should be an array of lead data (not wrapped in 'data' key)
        if (count($results) > 0) {
            $this->assertArrayHasKey('id', $results[0]);
        }
    }

    /**
     * @test
     */
    public function it_can_get_aggregation_statistics(): void
    {
        $stats = $this->iris->leads->aggregation()->statistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_leads', $stats);
        $this->assertArrayHasKey('total_tasks', $stats);
        $this->assertArrayHasKey('incomplete_tasks', $stats);
    }

    /**
     * @test
     */
    public function it_can_list_aggregated_leads(): void
    {
        $result = $this->iris->leads->aggregation()->list(['per_page' => 5]);

        $this->assertIsArray($result);

        // Response can be either {data: [...]} or just [...]
        $leads = $result['data'] ?? $result;

        if (count($leads) > 0) {
            $lead = is_array($leads[0] ?? null) ? $leads[0] : $leads;
            $this->assertArrayHasKey('id', $lead);
            // nickname or name should be present
            $this->assertTrue(
                isset($lead['nickname']) || isset($lead['name']),
                'Lead should have nickname or name'
            );
        }
    }

    /**
     * @test
     */
    public function it_can_get_single_lead(): void
    {
        // First get a lead ID
        $leads = $this->iris->leads->list(['per_page' => 1]);

        if (count($leads) === 0) {
            $this->markTestSkipped('No leads available to test get');
        }

        $leadId = $leads->first()->id;
        $lead = $this->iris->leads->get($leadId);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertEquals($leadId, $lead->id);
    }

    /**
     * @test
     */
    public function it_can_update_lead(): void
    {
        // Get a lead to update
        $leads = $this->iris->leads->list(['per_page' => 1]);

        if (count($leads) === 0) {
            $this->markTestSkipped('No leads available to test update');
        }

        $leadId = $leads->first()->id;
        $originalName = $leads->first()->name;

        // Update with a test name
        $testName = 'Integration Test ' . date('H:i:s');
        $lead = $this->iris->leads->update($leadId, ['name' => $testName]);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertEquals($testName, $lead->name);
        $this->assertEquals($testName, $lead->nickname);

        // Restore original name
        $this->iris->leads->update($leadId, ['name' => $originalName]);
    }

    /**
     * @test
     */
    public function it_can_update_lead_company(): void
    {
        $leads = $this->iris->leads->list(['per_page' => 1]);

        if (count($leads) === 0) {
            $this->markTestSkipped('No leads available to test company update');
        }

        $leadId = $leads->first()->id;
        $originalCompany = $leads->first()->company;

        // Update company
        $testCompany = 'Test Company ' . date('H:i:s');
        $lead = $this->iris->leads->update($leadId, ['company' => $testCompany]);

        $this->assertEquals($testCompany, $lead->company);

        // Restore original
        $this->iris->leads->update($leadId, ['company' => $originalCompany ?? '']);
    }

    /**
     * @test
     */
    public function search_returns_data_array_not_wrapped_response(): void
    {
        $results = $this->iris->leads->search(['per_page' => 5]);

        // Should be an array of leads, not {'data': [...], 'meta': {...}}
        $this->assertIsArray($results);

        if (count($results) > 0) {
            // First element should be a lead, not have 'data' key
            $this->assertArrayNotHasKey('data', $results);
            $this->assertArrayHasKey('id', $results[0]);
        }
    }

    /**
     * @test
     */
    public function it_can_get_lead_tags(): void
    {
        try {
            $tags = $this->iris->leads->tags();
            $this->assertIsArray($tags);
        } catch (\Exception $e) {
            // Tags might not be available for all users
            $this->markTestSkipped('Could not fetch tags: ' . $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_can_get_lead_stages(): void
    {
        try {
            $stages = $this->iris->leads->stages();
            $this->assertIsArray($stages);
        } catch (\Exception $e) {
            // Stages might not be available for all users
            $this->markTestSkipped('Could not fetch stages: ' . $e->getMessage());
        }
    }
}

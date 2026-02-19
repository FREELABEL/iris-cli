<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Integration;

use PHPUnit\Framework\TestCase;
use IRIS\SDK\IRIS;

/**
 * Integration test for Lead Aggregation API
 * 
 * Tests real API calls to lead aggregation endpoints for user 193
 *
 * @group integration
 * @group leads
 */
class LeadAggregationIntegrationTest extends TestCase
{
    private IRIS $iris;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $apiKey = getenv('IRIS_API_KEY');
        $baseUrl = getenv('IRIS_API_URL') ?: 'https://api.iris.ai';
        
        if (!$apiKey) {
            $this->markTestSkipped('IRIS_API_KEY environment variable not set');
        }
        
        $this->iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => 193,
            'base_url' => $baseUrl,
        ]);
    }
    
    public function testGetLeadAggregationStatistics(): void
    {
        // Use leads resource to make raw API call via getHttpClient
        $reflection = new \ReflectionClass($this->iris);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $http = $httpProperty->getValue($this->iris);
        
        $response = $http->get('/api/v1/leads/aggregation/statistics');
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        $data = $response['data'];
        
        // Verify statistics structure
        $this->assertArrayHasKey('total_leads', $data);
        $this->assertArrayHasKey('total_tasks', $data);
        $this->assertArrayHasKey('incomplete_tasks', $data);
        $this->assertArrayHasKey('completed_tasks', $data);
        $this->assertArrayHasKey('leads_with_tasks', $data);
        $this->assertArrayHasKey('leads_with_incomplete_tasks', $data);
        
        // Verify data types
        $this->assertIsInt($data['total_leads']);
        $this->assertIsInt($data['total_tasks']);
        $this->assertIsInt($data['incomplete_tasks']);
        
        // Verify recent activity
        if (isset($data['recent_activity'])) {
            $this->assertArrayHasKey('notes_last_24h', $data['recent_activity']);
            $this->assertArrayHasKey('notes_last_7d', $data['recent_activity']);
            $this->assertArrayHasKey('tasks_completed_last_7d', $data['recent_activity']);
        }
        
        // Verify top priority leads
        if (isset($data['top_priority_leads'])) {
            $this->assertIsArray($data['top_priority_leads']);
            
            if (count($data['top_priority_leads']) > 0) {
                $firstLead = $data['top_priority_leads'][0];
                $this->assertArrayHasKey('id', $firstLead);
                $this->assertArrayHasKey('nickname', $firstLead);
                $this->assertArrayHasKey('priority_score', $firstLead);
                $this->assertArrayHasKey('status', $firstLead);
            }
        }
        
        echo "\n📊 Lead Statistics for User 193:\n";
        echo "  Total Leads: {$data['total_leads']}\n";
        echo "  Total Tasks: {$data['total_tasks']}\n";
        echo "  Incomplete Tasks: {$data['incomplete_tasks']}\n";
        echo "  Active Leads: {$data['leads_with_incomplete_tasks']}\n";
    }
    
    public function testGetLeadAggregationList(): void
    {
        $reflection = new \ReflectionClass($this->iris);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $http = $httpProperty->getValue($this->iris);
        
        $response = $http->get('/api/v1/leads/aggregation', [
            'per_page' => 10,
        ]);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        $leads = $response['data'];
        $this->assertIsArray($leads);
        
        if (count($leads) > 0) {
            $firstLead = $leads[0];
            
            // Verify lead structure
            $this->assertArrayHasKey('id', $firstLead);
            $this->assertArrayHasKey('nickname', $firstLead);
            $this->assertArrayHasKey('status', $firstLead);
            $this->assertArrayHasKey('priority_score', $firstLead);
            $this->assertArrayHasKey('tasks_summary', $firstLead);
            
            // Verify tasks summary
            $tasksSummary = $firstLead['tasks_summary'];
            $this->assertArrayHasKey('total', $tasksSummary);
            $this->assertArrayHasKey('incomplete', $tasksSummary);
            $this->assertArrayHasKey('completed', $tasksSummary);
            
            echo "\n📋 Lead Aggregation List (User 193):\n";
            echo "  Found {$response['meta']['total']} total leads\n";
            echo "  Showing " . count($leads) . " leads\n";
            
            foreach (array_slice($leads, 0, 3) as $lead) {
                echo "  - [{$lead['priority_score']}] {$lead['nickname']} ({$lead['status']})\n";
            }
        }
    }
    
    public function testGetHighPriorityLeads(): void
    {
        $reflection = new \ReflectionClass($this->iris);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $http = $httpProperty->getValue($this->iris);
        
        $response = $http->get('/api/v1/leads/aggregation', [
            'has_incomplete_tasks' => 1,
            'order_by' => 'priority',
            'order_direction' => 'desc',
            'per_page' => 5,
        ]);
        
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        
        $leads = $response['data'];
        
        echo "\n🔥 High Priority Leads (User 193):\n";
        
        if (count($leads) > 0) {
            // Verify leads are sorted by priority
            $previousPriority = PHP_INT_MAX;
            
            foreach ($leads as $lead) {
                $this->assertArrayHasKey('priority_score', $lead);
                $this->assertLessThanOrEqual($previousPriority, $lead['priority_score']);
                $previousPriority = $lead['priority_score'];
                
                // Verify has incomplete tasks
                $this->assertGreaterThan(0, $lead['tasks_summary']['incomplete']);
                
                echo "  Priority {$lead['priority_score']}: {$lead['nickname']}\n";
                echo "    Tasks: {$lead['tasks_summary']['incomplete']} incomplete / {$lead['tasks_summary']['total']} total\n";
            }
        } else {
            echo "  No leads with incomplete tasks found\n";
        }
    }
    
    public function testGetSpecificLeadDetails(): void
    {
        $reflection = new \ReflectionClass($this->iris);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $http = $httpProperty->getValue($this->iris);
        
        // First get a lead ID from the list
        $listResponse = $http->get('/api/v1/leads/aggregation', ['per_page' => 1]);
        
        if (empty($listResponse['data'])) {
            $this->markTestSkipped('No leads available for user 193');
        }
        
        $leadId = $listResponse['data'][0]['id'];
        
        // Get full details
        $response = $this->iris->http->get("/api/v1/leads/aggregation/{$leadId}");
        
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        $lead = $response['data'];
        
        // Verify complete lead structure
        $this->assertArrayHasKey('id', $lead);
        $this->assertArrayHasKey('nickname', $lead);
        $this->assertArrayHasKey('name', $lead);
        $this->assertArrayHasKey('email', $lead);
        $this->assertArrayHasKey('company', $lead);
        $this->assertArrayHasKey('status', $lead);
        $this->assertArrayHasKey('priority_score', $lead);
        $this->assertArrayHasKey('tasks', $lead);
        
        echo "\n🔍 Lead Details (ID: {$leadId}):\n";
        echo "  Name: {$lead['nickname']}\n";
        echo "  Email: {$lead['email']}\n";
        echo "  Company: {$lead['company']}\n";
        echo "  Status: {$lead['status']}\n";
        echo "  Priority Score: {$lead['priority_score']}\n";
        echo "  Tasks: " . count($lead['tasks']) . "\n";
    }
    
    public function testGetLeadRequirements(): void
    {
        $reflection = new \ReflectionClass($this->iris);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $http = $httpProperty->getValue($this->iris);
        
        // Get a lead with tasks
        $listResponse = $http->get('/api/v1/leads/aggregation', [
            'has_incomplete_tasks' => 1,
            'per_page' => 1,
        ]);
        
        if (empty($listResponse['data'])) {
            $this->markTestSkipped('No leads with tasks available for user 193');
        }
        
        $leadId = $listResponse['data'][0]['id'];
        
        // Get requirements
        $response = $this->iris->http->get("/api/v1/leads/aggregation/{$leadId}/requirements");
        
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        $data = $response['data'];
        
        $this->assertArrayHasKey('lead_id', $data);
        $this->assertArrayHasKey('lead_name', $data);
        $this->assertArrayHasKey('extracted_requirements', $data);
        
        echo "\n🎯 Requirements for Lead {$leadId} ({$data['lead_name']}):\n";
        
        if (!empty($data['extracted_requirements'])) {
            foreach ($data['extracted_requirements'] as $req) {
                echo "  [{$req['type']}] {$req['text']}\n";
                echo "    Source: {$req['source']}\n";
            }
        } else {
            echo "  No requirements extracted\n";
        }
    }
    
    public function testLeadAggregationPagination(): void
    {
        $reflection = new \ReflectionClass($this->iris);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $http = $httpProperty->getValue($this->iris);
        
        $page1 = $http->get('/api/v1/leads/aggregation', [
            'per_page' => 5,
            'page' => 1,
        ]);
        
        $this->assertTrue($page1['success']);
        $this->assertArrayHasKey('meta', $page1);
        
        $meta = $page1['meta'];
        $this->assertArrayHasKey('current_page', $meta);
        $this->assertArrayHasKey('last_page', $meta);
        $this->assertArrayHasKey('per_page', $meta);
        $this->assertArrayHasKey('total', $meta);
        
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(5, $meta['per_page']);
        
        echo "\n📄 Pagination Info:\n";
        echo "  Total Leads: {$meta['total']}\n";
        echo "  Per Page: {$meta['per_page']}\n";
        echo "  Total Pages: {$meta['last_page']}\n";
    }
}

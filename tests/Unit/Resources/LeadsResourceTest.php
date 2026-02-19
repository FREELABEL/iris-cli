<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Leads\Lead;
use IRIS\SDK\Resources\Leads\LeadCollection;
use IRIS\SDK\Resources\Leads\LeadActivity;
use IRIS\SDK\Resources\Leads\LeadTask;

class LeadsResourceTest extends TestCase
{
    public function test_list_leads(): void
    {
        $this->mockResponse('GET', '/api/v1/leads', [
            'data' => [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ],
            'meta' => ['total' => 2],
        ]);

        $leads = $this->iris->leads->list();

        $this->assertInstanceOf(LeadCollection::class, $leads);
        $this->assertCount(2, $leads);
        $this->assertEquals('John Doe', $leads->first()->name);
    }

    public function test_search_leads(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/aggregation', [
            'data' => [
                [
                    'id' => 1,
                    'nickname' => 'John Doe',
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'status' => 'New',
                    'priority_score' => 75,
                    'tasks_summary' => ['total' => 2, 'incomplete' => 1, 'completed' => 1],
                ],
            ],
            'meta' => ['total' => 1],
        ]);

        $results = $this->iris->leads->search(['search' => 'john']);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['nickname']);
        $this->assertEquals(75, $results[0]['priority_score']);
    }

    public function test_search_leads_returns_data_array(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/aggregation', [
            'data' => [
                ['id' => 1, 'nickname' => 'Emily Test', 'status' => 'New'],
                ['id' => 2, 'nickname' => 'Emily Mayo', 'status' => 'Qualified'],
            ],
            'meta' => ['total' => 2, 'per_page' => 15],
        ]);

        $results = $this->iris->leads->search(['search' => 'emily']);

        // Should return data array directly, not the full response
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertArrayNotHasKey('meta', $results);
        $this->assertEquals('Emily Test', $results[0]['nickname']);
    }

    public function test_update_lead(): void
    {
        $this->mockResponse('PUT', '/api/v1/leads/414', [
            'id' => 414,
            'nickname' => 'Updated Name',
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'company' => 'Updated Company',
            'contact_info' => [
                'email' => 'updated@example.com',
                'company' => 'Updated Company',
            ],
        ]);

        $lead = $this->iris->leads->update(414, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'company' => 'Updated Company',
        ]);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertEquals(414, $lead->id);
        $this->assertEquals('Updated Name', $lead->name);
        $this->assertEquals('Updated Name', $lead->nickname);
        $this->assertEquals('Updated Company', $lead->company);
    }

    public function test_update_lead_updates_both_name_and_nickname(): void
    {
        $this->mockResponse('PUT', '/api/v1/leads/123', [
            'id' => 123,
            'nickname' => 'New Display Name',
            'name' => 'New Display Name',
            'company' => 'New Company',
            'contact_info' => ['company' => 'New Company'],
        ]);

        $lead = $this->iris->leads->update(123, [
            'name' => 'New Display Name',
            'company' => 'New Company',
        ]);

        // Verify both name and nickname are set correctly
        $this->assertEquals('New Display Name', $lead->name);
        $this->assertEquals('New Display Name', $lead->nickname);
        $this->assertEquals('New Company', $lead->company);
    }

    public function test_aggregation_list(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/aggregation', [
            'data' => [
                [
                    'id' => 1,
                    'nickname' => 'Priority Lead',
                    'priority_score' => 85,
                    'tasks_summary' => ['total' => 3, 'incomplete' => 2, 'completed' => 1],
                    'has_recent_activity' => true,
                ],
            ],
            'meta' => ['total' => 1],
        ]);

        $result = $this->iris->leads->aggregation()->list(['per_page' => 10]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals('Priority Lead', $result['data'][0]['nickname']);
    }

    public function test_aggregation_statistics(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/aggregation/statistics', [
            'data' => [
                'total_leads' => 496,
                'total_tasks' => 26,
                'incomplete_tasks' => 22,
                'active_leads' => 11,
            ],
        ]);

        $stats = $this->iris->leads->aggregation()->statistics();

        $this->assertEquals(496, $stats['total_leads']);
        $this->assertEquals(26, $stats['total_tasks']);
        $this->assertEquals(22, $stats['incomplete_tasks']);
    }

    public function test_aggregation_get_single_lead(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/aggregation/123', [
            'data' => [
                'id' => 123,
                'nickname' => 'Test Lead',
                'priority_score' => 65,
                'tasks' => [
                    ['id' => 1, 'title' => 'Follow up', 'is_completed' => false],
                ],
                'tasks_summary' => ['total' => 1, 'incomplete' => 1, 'completed' => 0],
            ],
        ]);

        $lead = $this->iris->leads->aggregation()->get(123);

        $this->assertEquals(123, $lead['id']);
        $this->assertEquals('Test Lead', $lead['nickname']);
        $this->assertCount(1, $lead['tasks']);
    }

    public function test_create_lead(): void
    {
        $this->mockResponse('POST', '/api/v1/leads', [
            'id' => 789,
            'name' => 'New Lead',
            'email' => 'new@example.com',
            'company' => 'Test Co',
        ]);

        $lead = $this->iris->leads->create([
            'name' => 'New Lead',
            'email' => 'new@example.com',
            'company' => 'Test Co',
        ]);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertEquals(789, $lead->id);
        $this->assertTrue($lead->hasEmail());
    }

    public function test_get_lead(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/456', [
            'id' => 456,
            'name' => 'Test Lead',
            'score' => 85.5,
        ]);

        $lead = $this->iris->leads->get(456);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertEquals(456, $lead->id);
        $this->assertTrue($lead->isHot());
    }

    public function test_add_note(): void
    {
        $this->mockResponse('POST', '/api/v1/leads/456/notes', [
            'id' => 1,
            'content' => 'Important note',
        ]);

        $result = $this->iris->leads->addNote(456, 'Important note');

        $this->assertEquals('Important note', $result['content']);
    }

    public function test_activities_sub_resource(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/456/activities', [
            'data' => [
                ['id' => 1, 'lead_id' => 456, 'type' => 'call', 'content' => 'Called client'],
            ],
        ]);

        $activities = $this->iris->leads->activities(456)->all();

        $this->assertCount(1, $activities);
        $this->assertInstanceOf(LeadActivity::class, $activities->first());
    }

    public function test_tasks_sub_resource(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/456/tasks', [
            'data' => [
                ['id' => 1, 'lead_id' => 456, 'title' => 'Follow up', 'status' => 'pending'],
            ],
        ]);

        $tasks = $this->iris->leads->tasks(456)->all();

        $this->assertCount(1, $tasks);
        $this->assertInstanceOf(LeadTask::class, $tasks->first());
        $this->assertTrue($tasks->first()->isPending());
    }

    public function test_generate_response(): void
    {
        $this->mockResponse('GET', '/api/v1/leads/456/generate-response', [
            'response' => 'Generated AI response',
        ]);

        $response = $this->iris->leads->generateResponse(456, 'Context here');

        $this->assertEquals('Generated AI response', $response);
    }

    public function test_attach_bloq(): void
    {
        $this->mockResponse('POST', '/api/v1/leads/456/attach-bloq', [
            'success' => true,
        ]);

        $result = $this->iris->leads->attachBloq(456, 789);

        $this->assertTrue($result);
    }
}

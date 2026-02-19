<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Workflows\Workflow;
use IRIS\SDK\Resources\Workflows\WorkflowCollection;
use IRIS\SDK\Resources\Workflows\WorkflowRun;
use IRIS\SDK\Resources\Workflows\WorkflowRunCollection;
use IRIS\SDK\Resources\Workflows\WorkflowStatus;
use IRIS\SDK\Resources\Workflows\WorkflowTemplate;
use IRIS\SDK\Resources\Workflows\TemplateCollection;
use IRIS\SDK\Resources\Workflows\HumanTask;

/**
 * WorkflowsResource Tests
 *
 * Tests for V5 multi-step workflow execution, generation, and templates.
 */
class WorkflowsResourceTest extends TestCase
{
    // ========================================
    // Workflow Execution
    // ========================================

    public function test_execute_workflow(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/workflow-runs', [
            'workflow_id' => 'wf_abc123',
            'status' => 'running',
            'current_step' => 0,
            'total_steps' => 3,
            'created_at' => '2025-12-23T10:00:00Z',
        ]);

        $workflow = $this->iris->workflows->execute([
            'agent_id' => 456,
            'query' => 'Research competitors and create a report',
            'bloq_id' => 32,
        ]);

        $this->assertInstanceOf(WorkflowRun::class, $workflow);
    }

    public function test_execute_workflow_with_approval(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/workflow-runs', [
            'workflow_id' => 'wf_xyz789',
            'status' => 'running',
            'require_approval' => true,
        ]);

        $workflow = $this->iris->workflows->execute([
            'query' => 'Execute sensitive task',
            'agent_id' => 456,
            'require_approval' => true,
        ]);

        $this->assertInstanceOf(WorkflowRun::class, $workflow);
    }

    public function test_execute_workflow_with_variables(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/workflow-runs', [
            'workflow_id' => 'wf_var123',
            'status' => 'running',
        ]);

        $workflow = $this->iris->workflows->execute([
            'workflow_id' => 100,
            'query' => 'Run template workflow',
            'variables' => [
                'target_company' => 'Acme Inc',
                'output_format' => 'pdf',
            ],
        ]);

        $this->assertInstanceOf(WorkflowRun::class, $workflow);
    }

    // ========================================
    // Workflow Status & Control
    // ========================================

    public function test_get_workflow_status(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/workflow-runs/wf_abc123', [
            'workflow_id' => 'wf_abc123',
            'status' => 'completed',
            'current_step' => 3,
            'total_steps' => 3,
            'result' => [
                'summary' => 'Analysis complete',
                'artifacts' => ['report.pdf'],
            ],
        ]);

        $status = $this->iris->workflows->getStatus('wf_abc123');

        $this->assertInstanceOf(WorkflowStatus::class, $status);
        $this->assertEquals('completed', $status->status);
    }

    public function test_continue_paused_workflow(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflow-runs/wf_paused/continue', [
            'workflow_id' => 'wf_paused',
            'status' => 'running',
        ]);

        $workflow = $this->iris->workflows->continue('wf_paused', [
            'user_input' => 'Approved to proceed',
        ]);

        $this->assertInstanceOf(WorkflowRun::class, $workflow);
    }

    public function test_process_step(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflow-runs/wf_step/process-step', [
            'workflow_id' => 'wf_step',
            'status' => 'running',
            'current_step' => 2,
        ]);

        $status = $this->iris->workflows->processStep('wf_step', 2, [
            'data' => 'Step data',
        ]);

        $this->assertInstanceOf(WorkflowStatus::class, $status);
    }

    // ========================================
    // Human Tasks (Human-in-the-Loop)
    // ========================================

    public function test_get_human_task(): void
    {
        $this->mockResponse('GET', '/api/v1/bloqs/workflow-human-tasks/task_123', [
            'id' => 'task_123',
            'workflow_run_id' => 'wf_abc',
            'type' => 'approval',
            'title' => 'Review research findings',
            'description' => 'Please review the competitive analysis before proceeding',
            'options' => ['approve', 'reject', 'request_changes'],
            'created_at' => '2025-12-23T10:00:00Z',
        ]);

        $task = $this->iris->workflows->getTask('task_123');

        $this->assertInstanceOf(HumanTask::class, $task);
        $this->assertEquals('approval', $task->type);
    }

    public function test_complete_human_task(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflow-human-tasks/task_123/complete', [
            'success' => true,
        ]);

        $result = $this->iris->workflows->completeTask('task_123', [
            'approved' => true,
            'feedback' => 'Looks good!',
        ]);

        $this->assertTrue($result);
    }

    public function test_complete_human_task_with_rejection(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflow-human-tasks/task_456/complete', [
            'success' => true,
        ]);

        $result = $this->iris->workflows->completeTask('task_456', [
            'approved' => false,
            'feedback' => 'Please revise the analysis',
            'data' => ['revisions_needed' => ['section_3', 'conclusion']],
        ]);

        $this->assertTrue($result);
    }

    // ========================================
    // Workflow Generation
    // ========================================

    public function test_generate_workflow(): void
    {
        $this->mockResponse('POST', '/api/v1/users/123/bloqs/workflows/generate', [
            'id' => 'wf_gen123',
            'name' => 'Generated Workflow',
            'steps' => [
                ['name' => 'Research', 'type' => 'agent'],
                ['name' => 'Analysis', 'type' => 'agent'],
                ['name' => 'Report', 'type' => 'agent'],
            ],
        ]);

        $workflow = $this->iris->workflows->generate(
            'Research competitors and create a comprehensive report',
            ['agent_id' => 456]
        );

        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertEquals('Generated Workflow', $workflow->name);
    }

    public function test_generate_workflow_with_agents(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflows/generate-with-agents', [
            'id' => 'wf_team123',
            'name' => 'Multi-Agent Workflow',
            'agents' => [
                ['id' => 1, 'name' => 'Researcher', 'role' => 'research'],
                ['id' => 2, 'name' => 'Analyst', 'role' => 'analysis'],
                ['id' => 3, 'name' => 'Writer', 'role' => 'writing'],
            ],
        ]);

        $workflow = $this->iris->workflows->generateWithAgents(
            'Create a marketing campaign',
            ['agent_count' => 3, 'bloq_id' => 32]
        );

        $this->assertInstanceOf(Workflow::class, $workflow);
    }

    public function test_generate_clarifying_questions(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflows/generate-questions', [
            'questions' => [
                'What industry are the competitors in?',
                'What specific metrics should be analyzed?',
                'What is the target audience for the report?',
            ],
        ]);

        $result = $this->iris->workflows->generateQuestions(
            'Research competitors and create a report'
        );

        $this->assertArrayHasKey('questions', $result);
        $this->assertCount(3, $result['questions']);
    }

    // ========================================
    // Templates
    // ========================================

    public function test_list_templates(): void
    {
        $this->mockResponse('GET', '/api/v1/templates', [
            'data' => [
                ['id' => 1, 'slug' => 'content-creation', 'name' => 'Content Creation'],
                ['id' => 2, 'slug' => 'research-report', 'name' => 'Research Report'],
            ],
            'meta' => ['total' => 2],
        ]);

        $templates = $this->iris->workflows->templates();

        $this->assertInstanceOf(TemplateCollection::class, $templates);
        $this->assertCount(2, $templates);
    }

    public function test_list_featured_templates(): void
    {
        $this->mockResponse('GET', '/api/v1/templates/featured', [
            'data' => [
                ['id' => 1, 'slug' => 'top-template', 'name' => 'Featured Template'],
            ],
            'meta' => ['total' => 1],
        ]);

        $templates = $this->iris->workflows->templates(['featured' => true]);

        $this->assertCount(1, $templates);
    }

    public function test_get_template_categories(): void
    {
        $this->mockResponse('GET', '/api/v1/templates/categories', [
            'categories' => [
                ['slug' => 'marketing', 'name' => 'Marketing', 'count' => 10],
                ['slug' => 'sales', 'name' => 'Sales', 'count' => 8],
                ['slug' => 'research', 'name' => 'Research', 'count' => 5],
            ],
        ]);

        $categories = $this->iris->workflows->templateCategories();

        $this->assertArrayHasKey('categories', $categories);
        $this->assertCount(3, $categories['categories']);
    }

    public function test_import_template(): void
    {
        $this->mockResponse('POST', '/api/v1/templates/content-creation/import', [
            'id' => 'wf_imported',
            'name' => 'My Content Workflow',
            'template_slug' => 'content-creation',
            'steps' => [
                ['name' => 'Research', 'type' => 'agent'],
                ['name' => 'Draft', 'type' => 'agent'],
            ],
        ]);

        $workflow = $this->iris->workflows->importTemplate('content-creation', [
            'topic' => 'AI in healthcare',
        ]);

        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertEquals('My Content Workflow', $workflow->name);
    }

    // ========================================
    // Workflow Listing
    // ========================================

    public function test_list_workflows(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/workflows', [
            'data' => [
                ['id' => 1, 'name' => 'Workflow 1', 'status' => 'active'],
                ['id' => 2, 'name' => 'Workflow 2', 'status' => 'draft'],
            ],
            'meta' => ['total' => 2],
        ]);

        $workflows = $this->iris->workflows->list();

        $this->assertInstanceOf(WorkflowCollection::class, $workflows);
        $this->assertCount(2, $workflows);
    }

    public function test_list_workflow_runs(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/workflow-runs', [
            'data' => [
                ['workflow_id' => 'wf_1', 'status' => 'completed'],
                ['workflow_id' => 'wf_2', 'status' => 'running'],
                ['workflow_id' => 'wf_3', 'status' => 'failed'],
            ],
            'meta' => ['total' => 3],
        ]);

        $runs = $this->iris->workflows->runs();

        $this->assertInstanceOf(WorkflowRunCollection::class, $runs);
        $this->assertCount(3, $runs);
    }

    public function test_list_workflow_runs_with_filters(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/workflow-runs', [
            'data' => [
                ['workflow_id' => 'wf_completed', 'status' => 'completed'],
            ],
            'meta' => ['total' => 1],
        ]);

        $runs = $this->iris->workflows->runs([
            'status' => 'completed',
            'per_page' => 50,
        ]);

        $this->assertCount(1, $runs);
    }

    // ========================================
    // Logs & Debugging
    // ========================================

    public function test_get_workflow_logs(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/bloqs/workflow-runs/wf_abc/logs', [
            'logs' => [
                ['timestamp' => '2025-12-23T10:00:00Z', 'level' => 'info', 'message' => 'Workflow started'],
                ['timestamp' => '2025-12-23T10:00:05Z', 'level' => 'info', 'message' => 'Step 1 completed'],
                ['timestamp' => '2025-12-23T10:00:10Z', 'level' => 'info', 'message' => 'Workflow completed'],
            ],
        ]);

        $logs = $this->iris->workflows->getLogs('wf_abc');

        $this->assertArrayHasKey('logs', $logs);
        $this->assertCount(3, $logs['logs']);
    }

    // ========================================
    // Webhooks
    // ========================================

    public function test_generate_workflow_webhook(): void
    {
        $this->mockResponse('POST', '/api/v1/bloqs/workflow/100/webhook-url', [
            'url' => 'https://api.iris.ai/webhooks/workflow/abc123',
            'secret' => 'whsec_workflow_xyz',
        ]);

        $result = $this->iris->workflows->generateWebhook(100);

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('secret', $result);
    }
}

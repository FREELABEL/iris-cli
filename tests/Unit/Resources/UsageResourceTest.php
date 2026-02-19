<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;

/**
 * UsageResource Tests
 *
 * Tests for API usage tracking, token consumption, and billing metrics.
 */
class UsageResourceTest extends TestCase
{
    // ========================================
    // Summary & Overview
    // ========================================

    public function test_get_usage_summary(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/summary', [
            'tokens_used' => 150000,
            'tokens_limit' => 500000,
            'api_calls' => 1250,
            'storage_used_mb' => 45.5,
            'agents_count' => 12,
            'workflows_count' => 8,
            'period' => 'current_month',
        ]);

        $summary = $this->iris->usage->summary();

        $this->assertEquals(150000, $summary['tokens_used']);
        $this->assertEquals(500000, $summary['tokens_limit']);
        $this->assertEquals(1250, $summary['api_calls']);
    }

    public function test_get_usage_details(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/details', [
            'data' => [
                ['date' => '2025-12-20', 'tokens' => 5000, 'api_calls' => 50],
                ['date' => '2025-12-21', 'tokens' => 8000, 'api_calls' => 80],
                ['date' => '2025-12-22', 'tokens' => 7500, 'api_calls' => 75],
            ],
        ]);

        $details = $this->iris->usage->details([
            'period' => 'month',
            'group_by' => 'day',
        ]);

        $this->assertArrayHasKey('data', $details);
        $this->assertCount(3, $details['data']);
    }

    public function test_get_usage_details_with_date_range(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/details', [
            'data' => [
                ['date' => '2025-01-15', 'tokens' => 10000],
            ],
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $details = $this->iris->usage->details([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);

        $this->assertArrayHasKey('start_date', $details);
    }

    // ========================================
    // Usage Breakdowns
    // ========================================

    public function test_get_usage_by_agent(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/by-agent', [
            'agents' => [
                ['id' => 1, 'name' => 'Marketing Agent', 'tokens_used' => 50000, 'api_calls' => 400],
                ['id' => 2, 'name' => 'Support Agent', 'tokens_used' => 30000, 'api_calls' => 250],
            ],
        ]);

        $usage = $this->iris->usage->byAgent();

        $this->assertArrayHasKey('agents', $usage);
        $this->assertCount(2, $usage['agents']);
        $this->assertEquals('Marketing Agent', $usage['agents'][0]['name']);
    }

    public function test_get_usage_by_model(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/by-model', [
            'models' => [
                'gpt-4o-mini' => ['tokens' => 80000, 'cost' => 1.20],
                'gpt-5-nano' => ['tokens' => 50000, 'cost' => 0.50],
                'gpt-4.1-nano' => ['tokens' => 20000, 'cost' => 0.20],
            ],
        ]);

        $usage = $this->iris->usage->byModel();

        $this->assertArrayHasKey('models', $usage);
        $this->assertArrayHasKey('gpt-4o-mini', $usage['models']);
        $this->assertEquals(80000, $usage['models']['gpt-4o-mini']['tokens']);
    }

    // ========================================
    // Billing & Subscription
    // ========================================

    public function test_get_billing(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/billing', [
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'next_billing_date' => '2025-01-15',
            'amount' => 49.99,
            'currency' => 'USD',
        ]);

        $billing = $this->iris->usage->billing();

        $this->assertEquals('pro', $billing['plan']);
        $this->assertEquals(49.99, $billing['amount']);
    }

    public function test_get_package(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/package', [
            'id' => 'pro',
            'name' => 'Pro Plan',
            'features' => [
                'max_agents' => 50,
                'max_workflows' => 100,
                'tokens_per_month' => 500000,
                'storage_gb' => 10,
            ],
        ]);

        $package = $this->iris->usage->package();

        $this->assertEquals('Pro Plan', $package['name']);
        $this->assertEquals(500000, $package['features']['tokens_per_month']);
    }

    public function test_get_subscription(): void
    {
        $this->mockResponse('GET', '/api/v1/billing/subscription', [
            'id' => 'sub_abc123',
            'status' => 'active',
            'plan_id' => 'pro',
            'current_period_start' => '2025-12-01',
            'current_period_end' => '2025-12-31',
            'cancel_at_period_end' => false,
        ]);

        $subscription = $this->iris->usage->subscription();

        $this->assertEquals('active', $subscription['status']);
        $this->assertFalse($subscription['cancel_at_period_end']);
    }

    public function test_get_available_plans(): void
    {
        $this->mockResponse('GET', '/api/v1/billing/plans', [
            'plans' => [
                ['id' => 'free', 'name' => 'Free', 'price' => 0],
                ['id' => 'starter', 'name' => 'Starter', 'price' => 19.99],
                ['id' => 'pro', 'name' => 'Pro', 'price' => 49.99],
                ['id' => 'enterprise', 'name' => 'Enterprise', 'price' => 199.99],
            ],
        ]);

        $plans = $this->iris->usage->availablePlans();

        $this->assertArrayHasKey('plans', $plans);
        $this->assertCount(4, $plans['plans']);
    }

    // ========================================
    // Quota & Limits
    // ========================================

    public function test_get_quota(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/quota', [
            'tokens_remaining' => 350000,
            'tokens_limit' => 500000,
            'api_calls_remaining' => 8750,
            'api_calls_limit' => 10000,
            'storage_remaining_mb' => 954.5,
            'storage_limit_mb' => 1000,
        ]);

        $quota = $this->iris->usage->quota();

        $this->assertEquals(350000, $quota['tokens_remaining']);
        $this->assertEquals(500000, $quota['tokens_limit']);
    }

    // ========================================
    // History
    // ========================================

    public function test_get_usage_history(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/history', [
            'history' => [
                ['month' => '2025-12', 'tokens' => 150000, 'cost' => 15.50],
                ['month' => '2025-11', 'tokens' => 120000, 'cost' => 12.00],
                ['month' => '2025-10', 'tokens' => 180000, 'cost' => 18.00],
            ],
        ]);

        $history = $this->iris->usage->history(3);

        $this->assertArrayHasKey('history', $history);
        $this->assertCount(3, $history['history']);
    }

    // ========================================
    // Workflow & Storage Stats
    // ========================================

    public function test_get_workflow_stats(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/workflows', [
            'total_runs' => 250,
            'successful' => 230,
            'failed' => 15,
            'cancelled' => 5,
            'avg_duration_seconds' => 45,
            'total_steps_executed' => 1250,
        ]);

        $stats = $this->iris->usage->workflowStats();

        $this->assertEquals(250, $stats['total_runs']);
        $this->assertEquals(230, $stats['successful']);
    }

    public function test_get_storage_usage(): void
    {
        $this->mockResponse('GET', '/api/v1/users/123/usage/storage', [
            'total_used_mb' => 45.5,
            'limit_mb' => 1000,
            'by_type' => [
                'pdf' => 20.5,
                'csv' => 15.0,
                'docx' => 8.0,
                'other' => 2.0,
            ],
        ]);

        $storage = $this->iris->usage->storage();

        $this->assertEquals(45.5, $storage['total_used_mb']);
        $this->assertArrayHasKey('by_type', $storage);
    }

    // ========================================
    // Credits
    // ========================================

    public function test_get_credit_status(): void
    {
        $this->mockResponse('GET', '/api/v1/billing/credit-status', [
            'credits_remaining' => 5000,
            'credits_used' => 15000,
            'plan_limit' => 20000,
            'bonus_credits' => 500,
            'expires_at' => '2025-12-31',
        ]);

        $credits = $this->iris->usage->creditStatus();

        $this->assertEquals(5000, $credits['credits_remaining']);
        $this->assertEquals(15000, $credits['credits_used']);
    }

    public function test_get_credit_history(): void
    {
        $this->mockResponse('GET', '/api/v1/billing/credit-history', [
            'transactions' => [
                ['id' => 1, 'type' => 'usage', 'amount' => -100, 'description' => 'Agent chat'],
                ['id' => 2, 'type' => 'purchase', 'amount' => 5000, 'description' => 'Credit purchase'],
            ],
        ]);

        $history = $this->iris->usage->creditHistory([
            'limit' => 10,
        ]);

        $this->assertArrayHasKey('transactions', $history);
        $this->assertCount(2, $history['transactions']);
    }
}

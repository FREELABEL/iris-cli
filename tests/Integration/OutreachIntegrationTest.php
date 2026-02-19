<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Integration;

use PHPUnit\Framework\TestCase;
use IRIS\SDK\IRIS;
use Dotenv\Dotenv;

/**
 * Outreach Integration Tests
 *
 * Tests the complete outreach pipeline:
 * - Outreach steps (CRUD + initializeDefault)
 * - AI email generation (without sending)
 * - Eligibility checks
 * - Progress tracking
 *
 * @group integration
 * @group outreach
 */
class OutreachIntegrationTest extends TestCase
{
    private static ?IRIS $iris = null;
    private static int $testLeadId = 510; // Jo's Coffee - existing test lead
    private static array $createdStepIds = [];

    public static function setUpBeforeClass(): void
    {
        // Load environment
        $sdkRoot = dirname(__DIR__, 2);
        if (file_exists($sdkRoot . '/.env')) {
            $dotenv = Dotenv::createImmutable($sdkRoot);
            $dotenv->safeLoad();
        }

        $apiKey = $_ENV['IRIS_API_KEY'] ?? getenv('IRIS_API_KEY');
        $userId = $_ENV['IRIS_USER_ID'] ?? getenv('IRIS_USER_ID');

        if (!$apiKey || !$userId) {
            self::markTestSkipped('IRIS_API_KEY and IRIS_USER_ID required for integration tests');
        }

        self::$iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => (int) $userId,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up created steps
        if (self::$iris && !empty(self::$createdStepIds)) {
            $outreachSteps = self::$iris->leads->outreachSteps(self::$testLeadId);
            foreach (self::$createdStepIds as $stepId) {
                try {
                    $outreachSteps->delete($stepId);
                } catch (\Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }
    }

    // =========================================================================
    // OUTREACH STEPS TESTS
    // =========================================================================

    public function test_list_outreach_steps(): void
    {
        $result = self::$iris->leads->outreachSteps(self::$testLeadId)->list();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('steps', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('outreach_types', $result);

        // Verify stats structure
        $stats = $result['stats'];
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('progress_percent', $stats);

        // Verify outreach types
        $types = $result['outreach_types'];
        $this->assertArrayHasKey('email', $types);
        $this->assertArrayHasKey('phone', $types);
        $this->assertArrayHasKey('linkedin', $types);
        $this->assertArrayHasKey('social', $types);
        $this->assertArrayHasKey('visit', $types);
    }

    public function test_create_outreach_step(): void
    {
        $stepData = [
            'title' => 'Integration Test Step',
            'type' => 'email',
            'instructions' => 'This is a test step created by integration tests',
        ];

        $result = self::$iris->leads->outreachSteps(self::$testLeadId)->create($stepData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('step', $result);

        $step = $result['step'];
        $this->assertArrayHasKey('id', $step);
        $this->assertEquals('Integration Test Step', $step['title']);
        $this->assertEquals('email', $step['type']);
        $this->assertEmpty($step['is_completed']);

        // Track for cleanup
        self::$createdStepIds[] = $step['id'];
    }

    public function test_update_outreach_step(): void
    {
        // Create a step first
        $result = self::$iris->leads->outreachSteps(self::$testLeadId)->create([
            'title' => 'Step to Update',
            'type' => 'phone',
        ]);
        $step = $result['step'];
        self::$createdStepIds[] = $step['id'];

        // Update it
        $updateResult = self::$iris->leads->outreachSteps(self::$testLeadId)->update($step['id'], [
            'title' => 'Updated Step Title',
            'instructions' => 'Added instructions',
        ]);

        $updated = $updateResult['step'] ?? $updateResult;
        $this->assertEquals('Updated Step Title', $updated['title']);
        $this->assertEquals('Added instructions', $updated['instructions']);
    }

    public function test_complete_outreach_step(): void
    {
        // Create a step
        $result = self::$iris->leads->outreachSteps(self::$testLeadId)->create([
            'title' => 'Step to Complete',
            'type' => 'email',
        ]);
        $step = $result['step'];
        self::$createdStepIds[] = $step['id'];

        // Complete it using convenience method
        $completeResult = self::$iris->leads->outreachSteps(self::$testLeadId)->complete(
            $step['id'],
            'Completed via integration test'
        );

        $completed = $completeResult['step'] ?? $completeResult;
        $this->assertTrue((bool) $completed['is_completed']);
        $this->assertEquals('Completed via integration test', $completed['notes']);
    }

    public function test_reopen_outreach_step(): void
    {
        // Create and complete a step
        $result = self::$iris->leads->outreachSteps(self::$testLeadId)->create([
            'title' => 'Step to Reopen',
            'type' => 'phone',
        ]);
        $step = $result['step'];
        self::$createdStepIds[] = $step['id'];

        $outreachSteps = self::$iris->leads->outreachSteps(self::$testLeadId);
        $outreachSteps->complete($step['id']);

        // Reopen it
        $reopenResult = $outreachSteps->reopen($step['id']);

        $reopened = $reopenResult['step'] ?? $reopenResult;
        $this->assertEmpty($reopened['is_completed']);
    }

    public function test_delete_outreach_step(): void
    {
        // Create a step
        $result = self::$iris->leads->outreachSteps(self::$testLeadId)->create([
            'title' => 'Step to Delete',
            'type' => 'sms',
        ]);
        $step = $result['step'];

        // Delete it
        $deleteResult = self::$iris->leads->outreachSteps(self::$testLeadId)->delete($step['id']);

        $this->assertIsArray($deleteResult);

        // Verify it's gone
        $list = self::$iris->leads->outreachSteps(self::$testLeadId)->list();
        $stepIds = array_column($list['steps'], 'id');
        $this->assertNotContains($step['id'], $stepIds);
    }

    public function test_get_outreach_step_types(): void
    {
        $types = self::$iris->leads->outreachSteps(self::$testLeadId)->getTypes();

        $this->assertContains('email', $types);
        $this->assertContains('phone', $types);
        $this->assertContains('sms', $types);
        $this->assertContains('linkedin', $types);
        $this->assertContains('social', $types);
        $this->assertContains('visit', $types);
        $this->assertContains('mail', $types);
        $this->assertContains('other', $types);
    }

    // =========================================================================
    // OUTREACH EMAIL TESTS (NO SENDING)
    // =========================================================================

    public function test_check_eligibility(): void
    {
        $result = self::$iris->leads->outreach(self::$testLeadId)->checkEligibility();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('eligibility', $result);
        $this->assertArrayHasKey('stats', $result);

        // Eligibility structure
        $eligibility = $result['eligibility'];
        $this->assertArrayHasKey('eligible', $eligibility);
        $this->assertArrayHasKey('reason', $eligibility);
    }

    public function test_get_outreach_info(): void
    {
        $result = self::$iris->leads->outreach(self::$testLeadId)->getInfo();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('lead', $result);
        $this->assertArrayHasKey('eligibility', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('recent_outreach', $result);

        // Lead info
        $lead = $result['lead'];
        $this->assertEquals(self::$testLeadId, $lead['id']);
    }

    public function test_generate_email_draft(): void
    {
        $prompt = 'Write a friendly introduction email about AI customer service for coffee shops';

        $result = self::$iris->leads->outreach(self::$testLeadId)->generateEmail($prompt, [
            'tone' => 'friendly',
            'include_cta' => true,
            'max_length' => 'short',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('draft', $result);

        // Draft structure
        $draft = $result['draft'];
        $this->assertArrayHasKey('subject', $draft);
        $this->assertArrayHasKey('body', $draft);
        $this->assertNotEmpty($draft['subject']);
        $this->assertNotEmpty($draft['body']);

        // Context used
        $this->assertArrayHasKey('context_used', $result);
        $this->assertArrayHasKey('options_applied', $result);

        // Options applied correctly
        $options = $result['options_applied'];
        $this->assertEquals('friendly', $options['tone']);
        $this->assertTrue($options['include_cta']);
        $this->assertEquals('short', $options['max_length']);
    }

    public function test_generate_email_with_professional_tone(): void
    {
        $result = self::$iris->leads->outreach(self::$testLeadId)->generateEmail(
            'Introduce our enterprise AI solutions',
            ['tone' => 'professional', 'max_length' => 'medium']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('professional', $result['options_applied']['tone']);
    }

    public function test_generate_email_revision_mode(): void
    {
        // First, generate initial draft
        $initial = self::$iris->leads->outreach(self::$testLeadId)->generateEmail(
            'Initial cold outreach',
            ['tone' => 'friendly']
        );

        $this->assertTrue($initial['success']);

        // Now request a revision
        $revised = self::$iris->leads->outreach(self::$testLeadId)->generateEmail(
            'Make it shorter and more urgent',
            [
                'options' => [
                    'revision_mode' => true,
                    'current_subject' => $initial['draft']['subject'],
                    'current_body' => $initial['draft']['body'],
                ]
            ]
        );

        $this->assertTrue($revised['success']);
        $this->assertNotEmpty($revised['draft']['subject']);
        $this->assertNotEmpty($revised['draft']['body']);
    }

    // =========================================================================
    // PROGRESS TRACKING TESTS
    // =========================================================================

    public function test_progress_tracking(): void
    {
        $outreachSteps = self::$iris->leads->outreachSteps(self::$testLeadId);

        // Clear existing steps for clean test
        $outreachSteps->clearAll();

        // Create 3 steps
        $step1Result = $outreachSteps->create(['title' => 'Progress Test 1', 'type' => 'email']);
        $step2Result = $outreachSteps->create(['title' => 'Progress Test 2', 'type' => 'phone']);
        $step3Result = $outreachSteps->create(['title' => 'Progress Test 3', 'type' => 'email']);

        $step1 = $step1Result['step'];
        $step2 = $step2Result['step'];
        $step3 = $step3Result['step'];

        self::$createdStepIds = array_merge(self::$createdStepIds, [
            $step1['id'], $step2['id'], $step3['id']
        ]);

        // Check initial progress (0%)
        $list = $outreachSteps->list();
        $this->assertEquals(0, $list['stats']['progress_percent']);
        $this->assertEquals(3, $list['stats']['total']);
        $this->assertEquals(0, $list['stats']['completed']);

        // Complete first step (~33%)
        $outreachSteps->complete($step1['id']);
        $list = $outreachSteps->list();
        $this->assertEqualsWithDelta(33, $list['stats']['progress_percent'], 1); // Allow rounding variance
        $this->assertEquals(1, $list['stats']['completed']);

        // Complete second step (~66%)
        $outreachSteps->complete($step2['id']);
        $list = $outreachSteps->list();
        $this->assertEqualsWithDelta(66, $list['stats']['progress_percent'], 1); // Allow rounding variance
        $this->assertEquals(2, $list['stats']['completed']);

        // Complete third step (100%)
        $outreachSteps->complete($step3['id']);
        $list = $outreachSteps->list();
        $this->assertEquals(100, $list['stats']['progress_percent']);
        $this->assertEquals(3, $list['stats']['completed']);
    }

    // =========================================================================
    // MULTI-CHANNEL OUTREACH TESTS
    // =========================================================================

    public function test_multi_channel_outreach_sequence(): void
    {
        $outreachSteps = self::$iris->leads->outreachSteps(self::$testLeadId);

        // Create a multi-channel sequence (Pulse Strategy)
        $emailResult = $outreachSteps->create([
            'title' => 'Day 1: AI Email (Intro & Demo)',
            'type' => 'email',
            'instructions' => 'Send AI-generated intro email with demo link',
        ]);
        $emailStep = $emailResult['step'];

        $socialResult = $outreachSteps->create([
            'title' => 'Day 3: Social/Visit Check-in',
            'type' => 'social',
            'instructions' => 'Check local interest via social or in-person visit',
        ]);
        $socialStep = $socialResult['step'];

        $followUpResult = $outreachSteps->create([
            'title' => 'Day 7: Follow-up Email',
            'type' => 'email',
            'instructions' => 'Send case study and success stories',
        ]);
        $followUpStep = $followUpResult['step'];

        self::$createdStepIds = array_merge(self::$createdStepIds, [
            $emailStep['id'], $socialStep['id'], $followUpStep['id']
        ]);

        // Verify sequence
        $list = $outreachSteps->list();
        $this->assertGreaterThanOrEqual(3, $list['stats']['total']);

        // Verify types
        $types = array_column($list['steps'], 'type');
        $this->assertContains('email', $types);
        $this->assertContains('social', $types);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_generate_email_without_lead_email(): void
    {
        // Lead 510 has no email - generation should still work
        $result = self::$iris->leads->outreach(self::$testLeadId)->generateEmail(
            'Create an email for this coffee shop',
            ['tone' => 'friendly']
        );

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['draft']['subject']);

        // Note: Cannot send without email, but draft generation works
    }

    public function test_empty_outreach_steps(): void
    {
        $outreachSteps = self::$iris->leads->outreachSteps(self::$testLeadId);

        // Clear all
        $outreachSteps->clearAll();

        // List should return empty with 0% progress
        $list = $outreachSteps->list();
        $this->assertEquals(0, $list['stats']['total']);
        $this->assertEquals(0, $list['stats']['progress_percent']);
    }
}

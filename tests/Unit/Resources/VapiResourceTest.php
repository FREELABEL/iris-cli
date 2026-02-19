<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;

/**
 * VapiResource Tests
 *
 * Tests for Voice AI phone numbers, assistants, and call management.
 */
class VapiResourceTest extends TestCase
{
    // ========================================
    // Phone Number Management
    // ========================================

    public function test_list_phone_numbers(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/phone-numbers', [
            'phone_numbers' => [
                [
                    'id' => 'dd3905f2-08d6-4dc2-a50f-f0c937ada251',
                    'phone_number' => '+15551234567',
                    'agent_id' => 335,
                    'status' => 'active',
                ],
                [
                    'id' => 'aa1234f2-08d6-4dc2-a50f-f0c937ada123',
                    'phone_number' => '+15559876543',
                    'agent_id' => null,
                    'status' => 'available',
                ],
            ],
        ]);

        $numbers = $this->iris->vapi->phoneNumbers();

        $this->assertArrayHasKey('phone_numbers', $numbers);
        $this->assertCount(2, $numbers['phone_numbers']);
    }

    public function test_get_phone_number(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/phone-numbers/dd3905f2-08d6-4dc2-a50f-f0c937ada251', [
            'id' => 'dd3905f2-08d6-4dc2-a50f-f0c937ada251',
            'phone_number' => '+15551234567',
            'agent_id' => 335,
            'status' => 'active',
            'use_dynamic_assistant' => true,
            'created_at' => '2025-12-01T10:00:00Z',
        ]);

        $number = $this->iris->vapi->getPhoneNumber('dd3905f2-08d6-4dc2-a50f-f0c937ada251');

        $this->assertEquals('+15551234567', $number['phone_number']);
        $this->assertEquals(335, $number['agent_id']);
    }

    public function test_configure_phone_number(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/phone-numbers/dd3905f2-08d6-4dc2-a50f-f0c937ada251/configure', [
            'id' => 'dd3905f2-08d6-4dc2-a50f-f0c937ada251',
            'agent_id' => 335,
            'use_dynamic_assistant' => true,
            'allow_override' => true,
            'status' => 'active',
        ]);

        $result = $this->iris->vapi->configurePhoneNumber('dd3905f2-08d6-4dc2-a50f-f0c937ada251', [
            'agent_id' => 335,
            'use_dynamic_assistant' => true,
            'allow_override' => true,
        ]);

        $this->assertEquals(335, $result['agent_id']);
        $this->assertTrue($result['use_dynamic_assistant']);
    }

    public function test_disconnect_phone_number(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/phone-numbers/dd3905f2-08d6-4dc2-a50f-f0c937ada251/disconnect', [
            'success' => true,
            'message' => 'Phone number disconnected',
        ]);

        $result = $this->iris->vapi->disconnectPhoneNumber('dd3905f2-08d6-4dc2-a50f-f0c937ada251');

        $this->assertTrue($result['success']);
    }

    // ========================================
    // Assistant Management
    // ========================================

    public function test_sync_assistant(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/sync-assistant', [
            'success' => true,
            'assistant_id' => 'vapi_asst_abc123',
            'agent_id' => 335,
            'synced_at' => '2025-12-23T10:00:00Z',
        ]);

        $result = $this->iris->vapi->syncAssistant(335);

        $this->assertTrue($result['success']);
        $this->assertEquals('vapi_asst_abc123', $result['assistant_id']);
    }

    public function test_get_assistant(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/assistant', [
            'assistant_id' => 'vapi_asst_abc123',
            'agent_id' => 335,
            'voice' => 'Lily',
            'language' => 'en-US',
            'first_message' => 'Hello! How can I help you today?',
        ]);

        $assistant = $this->iris->vapi->getAssistant(335);

        $this->assertEquals('vapi_asst_abc123', $assistant['assistant_id']);
        $this->assertEquals('Lily', $assistant['voice']);
    }

    // ========================================
    // Voice Settings
    // ========================================

    public function test_update_voice_settings(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/assistant/update-voice', [
            'success' => true,
            'voice' => 'Lily',
            'language' => 'en-US',
            'speed' => 1.0,
            'pitch' => 1.0,
        ]);

        $result = $this->iris->vapi->updateVoice(335, [
            'voice' => 'Lily',
            'language' => 'en-US',
            'speed' => 1.0,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Lily', $result['voice']);
    }

    public function test_list_voices(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/voices', [
            'voices' => [
                ['id' => 'lily', 'name' => 'Lily', 'language' => 'en-US', 'gender' => 'female'],
                ['id' => 'james', 'name' => 'James', 'language' => 'en-US', 'gender' => 'male'],
                ['id' => 'sophia', 'name' => 'Sophia', 'language' => 'en-GB', 'gender' => 'female'],
            ],
        ]);

        $voices = $this->iris->vapi->voices();

        $this->assertArrayHasKey('voices', $voices);
        $this->assertCount(3, $voices['voices']);
    }

    // ========================================
    // Handoff Settings
    // ========================================

    public function test_get_handoff_settings(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/assistant/handoff', [
            'enabled' => true,
            'phone_number' => '+15559876543',
            'mode' => 'blind',
            'message' => 'Let me transfer you to a human agent...',
            'sms_notifications' => true,
        ]);

        $handoff = $this->iris->vapi->getHandoff(335);

        $this->assertTrue($handoff['enabled']);
        $this->assertEquals('blind', $handoff['mode']);
    }

    public function test_update_handoff_settings(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/assistant/update-handoff', [
            'success' => true,
            'enabled' => true,
            'phone_number' => '8788765657',
            'mode' => 'warm',
            'message' => 'Transferring you to a specialist...',
        ]);

        $result = $this->iris->vapi->updateHandoff(335, [
            'enabled' => true,
            'phone_number' => '8788765657',
            'mode' => 'warm',
            'message' => 'Transferring you to a specialist...',
            'sms_notifications' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('warm', $result['mode']);
    }

    // ========================================
    // Call Management
    // ========================================

    public function test_get_call_history(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/calls', [
            'calls' => [
                [
                    'id' => 'call_abc123',
                    'agent_id' => 335,
                    'phone_number' => '+15551234567',
                    'direction' => 'inbound',
                    'duration_seconds' => 180,
                    'status' => 'completed',
                    'created_at' => '2025-12-23T10:00:00Z',
                ],
                [
                    'id' => 'call_xyz789',
                    'agent_id' => 335,
                    'phone_number' => '+15559876543',
                    'direction' => 'outbound',
                    'duration_seconds' => 120,
                    'status' => 'completed',
                    'created_at' => '2025-12-22T15:00:00Z',
                ],
            ],
            'total' => 2,
        ]);

        $history = $this->iris->vapi->callHistory(['limit' => 50]);

        $this->assertArrayHasKey('calls', $history);
        $this->assertCount(2, $history['calls']);
    }

    public function test_get_call_history_with_filters(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/calls', [
            'calls' => [
                [
                    'id' => 'call_filter1',
                    'agent_id' => 335,
                    'status' => 'completed',
                ],
            ],
            'total' => 1,
        ]);

        $history = $this->iris->vapi->callHistory([
            'agent_id' => 335,
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
        ]);

        $this->assertCount(1, $history['calls']);
    }

    public function test_get_call_details(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/calls/call_abc123', [
            'id' => 'call_abc123',
            'agent_id' => 335,
            'phone_number' => '+15551234567',
            'direction' => 'inbound',
            'duration_seconds' => 180,
            'status' => 'completed',
            'transcript_available' => true,
            'recording_available' => true,
            'metadata' => [
                'lead_id' => 412,
            ],
        ]);

        $call = $this->iris->vapi->getCall('call_abc123');

        $this->assertEquals('call_abc123', $call['id']);
        $this->assertEquals(180, $call['duration_seconds']);
        $this->assertTrue($call['transcript_available']);
    }

    public function test_get_call_transcript(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/calls/call_abc123/transcript', [
            'call_id' => 'call_abc123',
            'transcript' => [
                ['role' => 'assistant', 'content' => 'Hello! How can I help you today?', 'timestamp' => 0],
                ['role' => 'user', 'content' => 'Hi, I have a question about my account.', 'timestamp' => 3.5],
                ['role' => 'assistant', 'content' => 'Of course! I would be happy to help with your account.', 'timestamp' => 5.2],
            ],
        ]);

        $transcript = $this->iris->vapi->getTranscript('call_abc123');

        $this->assertArrayHasKey('transcript', $transcript);
        $this->assertCount(3, $transcript['transcript']);
    }

    public function test_get_call_recording(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/calls/call_abc123/recording', [
            'url' => 'https://storage.vapi.ai/recordings/call_abc123.mp3',
            'duration_seconds' => 180,
            'expires_at' => '2025-12-24T10:00:00Z',
        ]);

        $url = $this->iris->vapi->getRecording('call_abc123');

        $this->assertStringContainsString('recordings', $url);
    }

    // ========================================
    // Outbound Calls
    // ========================================

    public function test_initiate_call(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/calls/initiate', [
            'call_id' => 'call_new123',
            'status' => 'initiating',
            'agent_id' => 335,
            'to_phone_number' => '+15551234567',
            'message' => 'Call initiated',
        ]);

        $call = $this->iris->vapi->initiateCall(335, '+15551234567', [
            'context' => [
                'lead_id' => 412,
                'purpose' => 'Follow-up on proposal',
            ],
        ]);

        $this->assertEquals('call_new123', $call['call_id']);
        $this->assertEquals('initiating', $call['status']);
    }

    public function test_initiate_call_with_from_number(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/calls/initiate', [
            'call_id' => 'call_from123',
            'status' => 'initiating',
        ]);

        $call = $this->iris->vapi->initiateCall(335, '+15551234567', [
            'from_phone_number_id' => 'dd3905f2-08d6-4dc2-a50f-f0c937ada251',
        ]);

        $this->assertArrayHasKey('call_id', $call);
    }

    public function test_end_call(): void
    {
        $this->mockResponse('POST', '/api/v1/vapi/calls/call_active/end', [
            'success' => true,
            'call_id' => 'call_active',
            'status' => 'ended',
        ]);

        $result = $this->iris->vapi->endCall('call_active');

        $this->assertTrue($result['success']);
        $this->assertEquals('ended', $result['status']);
    }

    // ========================================
    // Usage Statistics
    // ========================================

    public function test_get_vapi_usage(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/usage', [
            'total_minutes' => 450,
            'total_calls' => 120,
            'inbound_calls' => 80,
            'outbound_calls' => 40,
            'estimated_cost' => 45.00,
            'period' => '2025-12',
        ]);

        $usage = $this->iris->vapi->usage();

        $this->assertEquals(450, $usage['total_minutes']);
        $this->assertEquals(120, $usage['total_calls']);
        $this->assertEquals(45.00, $usage['estimated_cost']);
    }

    public function test_get_vapi_usage_with_date_range(): void
    {
        $this->mockResponse('GET', '/api/v1/vapi/usage', [
            'total_minutes' => 200,
            'total_calls' => 50,
            'period' => 'custom',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-15',
        ]);

        $usage = $this->iris->vapi->usage([
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-15',
        ]);

        $this->assertEquals(200, $usage['total_minutes']);
        $this->assertEquals('custom', $usage['period']);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Agents\AgentSettings;

/**
 * AgentSettings Tests
 *
 * Tests for agent configuration settings class.
 */
class AgentSettingsTest extends TestCase
{
    // ========================================
    // Constructor and Initialization
    // ========================================

    public function test_construct_with_defaults(): void
    {
        $settings = new AgentSettings();

        $this->assertIsArray($settings->agentIntegrations);
        $this->assertIsArray($settings->enabledFunctions);
        $this->assertNull($settings->schedule);
        $this->assertNull($settings->voiceSettings);
        $this->assertEquals('balanced', $settings->responseMode);
        $this->assertEquals('professional', $settings->communicationStyle);
        $this->assertTrue($settings->memoryPersistence);
        $this->assertEquals('10', $settings->contextWindow);
    }

    public function test_construct_with_custom_data(): void
    {
        $data = [
            'agentIntegrations' => ['gmail' => true, 'slack' => true],
            'enabledFunctions' => ['manageLeads' => true],
            'responseMode' => 'creative',
            'communicationStyle' => 'friendly',
            'memoryPersistence' => false,
            'contextWindow' => '20',
        ];

        $settings = new AgentSettings($data);

        $this->assertEquals($data['agentIntegrations'], $settings->agentIntegrations);
        $this->assertEquals($data['enabledFunctions'], $settings->enabledFunctions);
        $this->assertEquals('creative', $settings->responseMode);
        $this->assertEquals('friendly', $settings->communicationStyle);
        $this->assertFalse($settings->memoryPersistence);
        $this->assertEquals('20', $settings->contextWindow);
    }

    // ========================================
    // Integration Management
    // ========================================

    public function test_enable_integration(): void
    {
        $settings = new AgentSettings();
        $result = $settings->enableIntegration('gmail');

        $this->assertSame($settings, $result); // Fluent interface
        $this->assertTrue($settings->agentIntegrations['gmail']);
    }

    public function test_disable_integration(): void
    {
        $settings = new AgentSettings(['agentIntegrations' => ['gmail' => true]]);
        $result = $settings->disableIntegration('gmail');

        $this->assertSame($settings, $result); // Fluent interface
        $this->assertFalse($settings->agentIntegrations['gmail']);
    }

    public function test_has_integration_when_enabled(): void
    {
        $settings = new AgentSettings(['agentIntegrations' => ['gmail' => true]]);

        $this->assertTrue($settings->hasIntegration('gmail'));
    }

    public function test_has_integration_when_disabled(): void
    {
        $settings = new AgentSettings(['agentIntegrations' => ['gmail' => false]]);

        $this->assertFalse($settings->hasIntegration('gmail'));
    }

    public function test_has_integration_when_not_set(): void
    {
        $settings = new AgentSettings();

        $this->assertFalse($settings->hasIntegration('gmail'));
    }

    public function test_chain_multiple_integrations(): void
    {
        $settings = new AgentSettings();
        $settings->enableIntegration('gmail')
                 ->enableIntegration('slack')
                 ->enableIntegration('google-calendar');

        $this->assertTrue($settings->hasIntegration('gmail'));
        $this->assertTrue($settings->hasIntegration('slack'));
        $this->assertTrue($settings->hasIntegration('google-calendar'));
    }

    // ========================================
    // Function Management
    // ========================================

    public function test_enable_function(): void
    {
        $settings = new AgentSettings();
        $result = $settings->enableFunction('manageLeads');

        $this->assertSame($settings, $result); // Fluent interface
        $this->assertTrue($settings->enabledFunctions['manageLeads']);
    }

    public function test_disable_function(): void
    {
        $settings = new AgentSettings(['enabledFunctions' => ['manageLeads' => true]]);
        $result = $settings->disableFunction('manageLeads');

        $this->assertSame($settings, $result); // Fluent interface
        $this->assertFalse($settings->enabledFunctions['manageLeads']);
    }

    public function test_has_function_when_enabled(): void
    {
        $settings = new AgentSettings(['enabledFunctions' => ['deepResearch' => true]]);

        $this->assertTrue($settings->hasFunction('deepResearch'));
    }

    public function test_has_function_when_disabled(): void
    {
        $settings = new AgentSettings(['enabledFunctions' => ['deepResearch' => false]]);

        $this->assertFalse($settings->hasFunction('deepResearch'));
    }

    public function test_chain_multiple_functions(): void
    {
        $settings = new AgentSettings();
        $settings->enableFunction('manageLeads')
                 ->enableFunction('deepResearch')
                 ->enableFunction('webSearch');

        $this->assertTrue($settings->hasFunction('manageLeads'));
        $this->assertTrue($settings->hasFunction('deepResearch'));
        $this->assertTrue($settings->hasFunction('webSearch'));
    }

    // ========================================
    // Schedule Configuration
    // ========================================

    public function test_with_schedule(): void
    {
        $scheduleData = [
            'enabled' => true,
            'timezone' => 'America/New_York',
            'recurring_tasks' => [
                ['time' => '09:00', 'frequency' => 'daily', 'message' => 'Test'],
            ],
        ];

        $settings = new AgentSettings();
        $result = $settings->withSchedule($scheduleData);

        $this->assertSame($settings, $result); // Fluent interface
        $this->assertEquals($scheduleData, $settings->schedule);
    }

    // ========================================
    // Voice Settings
    // ========================================

    public function test_with_voice_settings(): void
    {
        $voiceData = [
            'language' => 'en-US',
            'speaking_rate' => 0.9,
            'pitch' => 0.0,
        ];

        $settings = new AgentSettings();
        $result = $settings->withVoiceSettings($voiceData);

        $this->assertSame($settings, $result); // Fluent interface
        $this->assertEquals($voiceData, $settings->voiceSettings);
    }

    // ========================================
    // Array Conversion
    // ========================================

    public function test_to_array_includes_all_properties(): void
    {
        $settings = new AgentSettings();
        $settings->enableIntegration('gmail')
                 ->enableFunction('manageLeads')
                 ->withVoiceSettings(['language' => 'en-US']);

        $array = $settings->toArray();

        $this->assertArrayHasKey('agentIntegrations', $array);
        $this->assertArrayHasKey('enabledFunctions', $array);
        $this->assertArrayHasKey('schedule', $array);
        $this->assertArrayHasKey('voiceSettings', $array);
        $this->assertArrayHasKey('responseMode', $array);
        $this->assertArrayHasKey('communicationStyle', $array);
        $this->assertArrayHasKey('memoryPersistence', $array);
        $this->assertArrayHasKey('contextWindow', $array);
    }

    public function test_to_array_with_null_values(): void
    {
        $settings = new AgentSettings();
        // schedule and voiceSettings are null by default

        $array = $settings->toArray();

        // null values should still be present but as null
        $this->assertNull($array['schedule']);
        $this->assertNull($array['voiceSettings']);
    }

    public function test_to_array_preserves_enabled_integrations(): void
    {
        $settings = new AgentSettings();
        $settings->enableIntegration('gmail')
                 ->enableIntegration('slack');

        $array = $settings->toArray();

        $this->assertTrue($array['agentIntegrations']['gmail']);
        $this->assertTrue($array['agentIntegrations']['slack']);
    }

    // ========================================
    // Complex Configuration
    // ========================================

    public function test_full_configuration(): void
    {
        $settings = new AgentSettings();
        
        // Integrations
        $settings->enableIntegration('gmail')
                 ->enableIntegration('google-calendar')
                 ->enableIntegration('slack');

        // Functions
        $settings->enableFunction('manageLeads')
                 ->enableFunction('deepResearch');

        // Schedule
        $settings->withSchedule([
            'enabled' => true,
            'timezone' => 'UTC',
            'recurring_tasks' => [
                [
                    'time' => '09:00',
                    'frequency' => 'daily',
                    'message' => 'Daily briefing',
                    'channels' => ['email'],
                ],
            ],
        ]);

        // Voice
        $settings->withVoiceSettings([
            'language' => 'en-US',
            'speaking_rate' => 1.0,
        ]);

        // Styles
        $settings->responseMode = 'creative';
        $settings->communicationStyle = 'friendly';
        $settings->memoryPersistence = true;
        $settings->contextWindow = '15';

        $array = $settings->toArray();

        // Verify all components
        $this->assertEquals(3, count(array_filter($array['agentIntegrations'])));
        $this->assertEquals(2, count(array_filter($array['enabledFunctions'])));
        $this->assertTrue($array['schedule']['enabled']);
        $this->assertEquals('en-US', $array['voiceSettings']['language']);
        $this->assertEquals('creative', $array['responseMode']);
        $this->assertEquals('friendly', $array['communicationStyle']);
        $this->assertTrue($array['memoryPersistence']);
        $this->assertEquals('15', $array['contextWindow']);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function test_enable_already_enabled_integration(): void
    {
        $settings = new AgentSettings(['agentIntegrations' => ['gmail' => true]]);
        $settings->enableIntegration('gmail');

        $this->assertTrue($settings->hasIntegration('gmail'));
    }

    public function test_disable_already_disabled_integration(): void
    {
        $settings = new AgentSettings(['agentIntegrations' => ['gmail' => false]]);
        $settings->disableIntegration('gmail');

        $this->assertFalse($settings->hasIntegration('gmail'));
    }

    public function test_mixed_enable_disable_integrations(): void
    {
        $settings = new AgentSettings();
        $settings->enableIntegration('gmail')
                 ->enableIntegration('slack')
                 ->disableIntegration('gmail')
                 ->enableIntegration('google-drive');

        $this->assertFalse($settings->hasIntegration('gmail'));
        $this->assertTrue($settings->hasIntegration('slack'));
        $this->assertTrue($settings->hasIntegration('google-drive'));
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\Templates\ElderlyCareTemplate;
use IRIS\SDK\Resources\Agents\Templates\CustomerSupportTemplate;
use IRIS\SDK\Resources\Agents\Templates\SalesAssistantTemplate;
use IRIS\SDK\Resources\Agents\Templates\ResearchAgentTemplate;
use IRIS\SDK\Resources\Agents\Templates\EducationalTutorTemplate;
use IRIS\SDK\Resources\Agents\Templates\LeadershipCoachTemplate;

/**
 * Agent Template Tests
 *
 * Tests for agent template system and built-in templates.
 */
class AgentTemplatesTest extends TestCase
{
    // ========================================
    // Elderly Care Template
    // ========================================

    public function test_elderly_care_template_basic_info(): void
    {
        $template = new ElderlyCareTemplate();

        $this->assertEquals('Elderly Care Assistant', $template->getName());
        $this->assertIsString($template->getDescription());
        $this->assertNotEmpty($template->getDescription());
    }

    public function test_elderly_care_template_default_config(): void
    {
        $template = new ElderlyCareTemplate();
        $config = $template->getDefaultConfig();

        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('prompt', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('settings', $config);

        // Check default settings
        $settings = $config['settings'];
        $this->assertArrayHasKey('agentIntegrations', $settings);
        $this->assertArrayHasKey('schedule', $settings);
        $this->assertArrayHasKey('voiceSettings', $settings);
        $this->assertArrayHasKey('communicationStyle', $settings);
    }

    public function test_elderly_care_template_build_with_customization(): void
    {
        $template = new ElderlyCareTemplate();
        $config = $template->build([
            'name' => 'Care Assistant for Mom',
            'medication_times' => ['08:00', '12:00', '18:00', '22:00'],
            'timezone' => 'America/New_York',
        ]);

        $this->assertEquals('Care Assistant for Mom', $config['name']);
        $this->assertEquals('America/New_York', $config['settings']['schedule']['timezone']);
        $this->assertCount(5, $config['settings']['schedule']['recurring_tasks']); // 4 med + 1 check-in
    }

    public function test_elderly_care_template_validation(): void
    {
        $template = new ElderlyCareTemplate();
        
        // Valid input - should not throw
        try {
            $template->validate([
                'name' => 'Test',
                'medication_times' => ['08:00', '20:00'],
                'timezone' => 'UTC',
            ]);
            $this->assertTrue(true); // No exception thrown
        } catch (\Exception $e) {
            $this->fail('Valid customization should not throw exception');
        }

        // Invalid time format - may throw or be silently handled depending on implementation
        // This is implementation-specific validation
    }

    // ========================================
    // Customer Support Template
    // ========================================

    public function test_customer_support_template_basic_info(): void
    {
        $template = new CustomerSupportTemplate();

        $this->assertEquals('Customer Support Agent', $template->getName());
        $this->assertIsString($template->getDescription());
    }

    public function test_customer_support_template_default_integrations(): void
    {
        $template = new CustomerSupportTemplate();
        $config = $template->getDefaultConfig();

        $integrations = $config['settings']['agentIntegrations'];
        $this->assertTrue($integrations['gmail'] ?? false);
        $this->assertTrue($integrations['slack'] ?? false);
    }

    public function test_customer_support_template_build(): void
    {
        $template = new CustomerSupportTemplate();
        $config = $template->build([
            'name' => 'Support Bot',
            'knowledge_base_id' => 123,
        ]);

        $this->assertEquals('Support Bot', $config['name']);
        $this->assertEquals(123, $config['bloq_id']);
    }

    // ========================================
    // Sales Assistant Template
    // ========================================

    public function test_sales_assistant_template_basic_info(): void
    {
        $template = new SalesAssistantTemplate();

        $this->assertEquals('Sales Assistant', $template->getName());
        $this->assertIsString($template->getDescription());
    }

    public function test_sales_assistant_template_has_manage_leads(): void
    {
        $template = new SalesAssistantTemplate();
        $config = $template->getDefaultConfig();

        $functions = $config['settings']['enabledFunctions'];
        $this->assertTrue($functions['manageLeads'] ?? false);
    }

    public function test_sales_assistant_template_build(): void
    {
        $template = new SalesAssistantTemplate();
        $config = $template->build([
            'name' => 'Sales AI',
            'company_name' => 'ACME Corp',
        ]);

        $this->assertEquals('Sales AI', $config['name']);
        $this->assertStringContainsString('ACME Corp', $config['prompt']);
    }

    // ========================================
    // Research Agent Template
    // ========================================

    public function test_research_agent_template_basic_info(): void
    {
        $template = new ResearchAgentTemplate();

        $this->assertEquals('Research Agent', $template->getName());
        $this->assertIsString($template->getDescription());
    }

    public function test_research_agent_template_has_deep_research(): void
    {
        $template = new ResearchAgentTemplate();
        $config = $template->getDefaultConfig();

        $functions = $config['settings']['enabledFunctions'];
        $this->assertTrue($functions['deepResearch'] ?? false);
    }

    public function test_research_agent_template_context_window(): void
    {
        $template = new ResearchAgentTemplate();
        $config = $template->getDefaultConfig();

        // Research agents need larger context
        $contextWindow = $config['settings']['contextWindow'];
        $this->assertGreaterThanOrEqual('15', $contextWindow);
    }

    // ========================================
    // Educational Tutor Template
    // ========================================

    public function test_educational_tutor_template_basic_info(): void
    {
        $template = new EducationalTutorTemplate();

        $this->assertEquals('Educational Tutor', $template->getName());
        $this->assertIsString($template->getDescription());
    }

    public function test_educational_tutor_template_has_schedule(): void
    {
        $template = new EducationalTutorTemplate();
        $config = $template->getDefaultConfig();

        $schedule = $config['settings']['schedule'];
        $this->assertTrue($schedule['enabled']);
        $this->assertNotEmpty($schedule['recurring_tasks']);
    }

    public function test_educational_tutor_template_build_with_subject(): void
    {
        $template = new EducationalTutorTemplate();
        $config = $template->build([
            'name' => 'Math Tutor',
            'subject' => 'Mathematics',
            'grade_level' => '8th Grade',
        ]);

        $this->assertEquals('Math Tutor', $config['name']);
        $this->assertStringContainsString('Mathematics', $config['prompt']);
        $this->assertStringContainsString('8th Grade', $config['prompt']);
    }

    // ========================================
    // Leadership Coach Template
    // ========================================

    public function test_leadership_coach_template_basic_info(): void
    {
        $template = new LeadershipCoachTemplate();

        $this->assertEquals('Leadership Coach', $template->getName());
        $this->assertIsString($template->getDescription());
    }

    public function test_leadership_coach_template_communication_style(): void
    {
        $template = new LeadershipCoachTemplate();
        $config = $template->getDefaultConfig();

        $style = $config['settings']['communicationStyle'];
        $this->assertIsString($style);
        // Leadership coaches should have a thoughtful style
        $this->assertNotEquals('casual', $style);
    }

    public function test_leadership_coach_template_has_schedule(): void
    {
        $template = new LeadershipCoachTemplate();
        $config = $template->getDefaultConfig();

        $schedule = $config['settings']['schedule'];
        $this->assertTrue($schedule['enabled']);
        
        // Should have weekly reflection and monthly review
        $tasks = $schedule['recurring_tasks'];
        $this->assertGreaterThanOrEqual(2, count($tasks));
        
        $frequencies = array_column($tasks, 'frequency');
        $this->assertContains('weekly', $frequencies);
        $this->assertContains('monthly', $frequencies);
    }

    // ========================================
    // Template System Integration
    // ========================================

    public function test_all_templates_implement_required_methods(): void
    {
        $templates = [
            new ElderlyCareTemplate(),
            new CustomerSupportTemplate(),
            new SalesAssistantTemplate(),
            new ResearchAgentTemplate(),
            new EducationalTutorTemplate(),
            new LeadershipCoachTemplate(),
        ];

        foreach ($templates as $template) {
            $this->assertInstanceOf(AgentTemplate::class, $template);
            $this->assertIsString($template->getName());
            $this->assertNotEmpty($template->getName());
            $this->assertIsString($template->getDescription());
            $this->assertNotEmpty($template->getDescription());
            
            $config = $template->getDefaultConfig();
            $this->assertIsArray($config);
            $this->assertArrayHasKey('name', $config);
            $this->assertArrayHasKey('prompt', $config);
            $this->assertArrayHasKey('model', $config);
            
            $built = $template->build([]);
            $this->assertIsArray($built);
            
            // Validate should not throw for empty customization
            try {
                $template->validate([]);
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('validate() should not throw on empty customization');
            }
        }
    }

    public function test_templates_have_unique_names(): void
    {
        $templates = [
            new ElderlyCareTemplate(),
            new CustomerSupportTemplate(),
            new SalesAssistantTemplate(),
            new ResearchAgentTemplate(),
            new EducationalTutorTemplate(),
            new LeadershipCoachTemplate(),
        ];

        $names = array_map(fn($t) => $t->getName(), $templates);
        $uniqueNames = array_unique($names);

        $this->assertCount(count($templates), $uniqueNames);
    }

    public function test_all_templates_use_cost_effective_models(): void
    {
        $templates = [
            new ElderlyCareTemplate(),
            new CustomerSupportTemplate(),
            new SalesAssistantTemplate(),
            new ResearchAgentTemplate(),
            new EducationalTutorTemplate(),
            new LeadershipCoachTemplate(),
        ];

        $costEffectiveModels = [
            'gpt-4o-mini',
            'gpt-4.1-nano',
            'gpt-5-nano',
        ];

        foreach ($templates as $template) {
            $config = $template->getDefaultConfig();
            $model = $config['model'];
            
            $this->assertContains($model, $costEffectiveModels,
                "{$template->getName()} should use a cost-effective model");
        }
    }

    // ========================================
    // Template Configuration Consistency
    // ========================================

    public function test_all_templates_have_valid_settings_structure(): void
    {
        $templates = [
            new ElderlyCareTemplate(),
            new CustomerSupportTemplate(),
            new SalesAssistantTemplate(),
            new ResearchAgentTemplate(),
            new EducationalTutorTemplate(),
            new LeadershipCoachTemplate(),
        ];

        foreach ($templates as $template) {
            $config = $template->getDefaultConfig();
            $settings = $config['settings'];

            // All templates should have these keys
            $this->assertArrayHasKey('agentIntegrations', $settings);
            $this->assertArrayHasKey('responseMode', $settings);
            $this->assertArrayHasKey('communicationStyle', $settings);
            
            // Integrations should be array
            $this->assertIsArray($settings['agentIntegrations']);
            
            // Response mode should be valid
            $validModes = ['balanced', 'creative', 'precise'];
            $this->assertContains($settings['responseMode'], $validModes);
        }
    }

    public function test_schedule_enabled_templates_have_valid_tasks(): void
    {
        $templatesWithSchedule = [
            new ElderlyCareTemplate(),
            new EducationalTutorTemplate(),
            new LeadershipCoachTemplate(),
        ];

        foreach ($templatesWithSchedule as $template) {
            $config = $template->getDefaultConfig();
            $schedule = $config['settings']['schedule'];

            $this->assertTrue($schedule['enabled']);
            $this->assertArrayHasKey('timezone', $schedule);
            $this->assertArrayHasKey('recurring_tasks', $schedule);
            $this->assertIsArray($schedule['recurring_tasks']);
            $this->assertNotEmpty($schedule['recurring_tasks']);

            // Validate task structure
            foreach ($schedule['recurring_tasks'] as $task) {
                $this->assertArrayHasKey('time', $task);
                $this->assertArrayHasKey('frequency', $task);
                $this->assertArrayHasKey('message', $task);
                $this->assertArrayHasKey('channels', $task);
                
                // Time format validation
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $task['time']);
                
                // Frequency validation
                $validFrequencies = ['daily', 'weekly', 'monthly'];
                $this->assertContains($task['frequency'], $validFrequencies);
            }
        }
    }

    // ========================================
    // Custom Template Support
    // ========================================

    public function test_custom_template_can_extend_base(): void
    {
        $customTemplate = new class extends AgentTemplate {
            public function getName(): string
            {
                return 'Custom Test Template';
            }

            public function getDescription(): string
            {
                return 'Test template for unit testing';
            }

            public function getDefaultConfig(): array
            {
                return [
                    'name' => 'Custom Agent',
                    'prompt' => 'Test prompt',
                    'model' => 'gpt-4o-mini',
                    'settings' => [
                        'agentIntegrations' => [],
                        'responseMode' => 'balanced',
                        'communicationStyle' => 'professional',
                    ],
                ];
            }

            public function build(array $customization = []): array
            {
                $config = $this->getDefaultConfig();
                if (isset($customization['name'])) {
                    $config['name'] = $customization['name'];
                }
                return $config;
            }

            public function validate(array $customization): void
            {
                // No validation needed for test
            }
        };

        $this->assertInstanceOf(AgentTemplate::class, $customTemplate);
        $this->assertEquals('Custom Test Template', $customTemplate->getName());
        
        $config = $customTemplate->build(['name' => 'My Custom Agent']);
        $this->assertEquals('My Custom Agent', $config['name']);
    }

    // ========================================
    // Build Method Customization
    // ========================================

    public function test_templates_merge_customization_properly(): void
    {
        $template = new CustomerSupportTemplate();
        
        $customization = [
            'name' => 'Custom Support Bot',
            'knowledge_base_id' => 456,
        ];

        $config = $template->build($customization);

        // Custom values should override defaults
        $this->assertEquals('Custom Support Bot', $config['name']);
        $this->assertEquals(456, $config['bloq_id']);
        
        // Other defaults should remain
        $this->assertArrayHasKey('prompt', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('settings', $config);
    }

    public function test_templates_preserve_default_settings_on_build(): void
    {
        $template = new SalesAssistantTemplate();
        
        $defaultConfig = $template->getDefaultConfig();
        $builtConfig = $template->build(['name' => 'Test']);

        // Settings structure should be preserved
        $this->assertEquals(
            $defaultConfig['settings']['agentIntegrations'],
            $builtConfig['settings']['agentIntegrations']
        );
        
        $this->assertEquals(
            $defaultConfig['settings']['responseMode'],
            $builtConfig['settings']['responseMode']
        );
    }
}

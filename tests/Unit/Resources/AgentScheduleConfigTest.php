<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Resources;

use IRIS\SDK\Tests\TestCase;
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;
use InvalidArgumentException;

/**
 * AgentScheduleConfig Tests
 *
 * Tests for agent schedule configuration class.
 */
class AgentScheduleConfigTest extends TestCase
{
    // ========================================
    // Constructor and Factory Methods
    // ========================================

    public function test_construct_with_defaults(): void
    {
        $config = new AgentScheduleConfig();

        $this->assertFalse($config->enabled);
        $this->assertEquals('UTC', $config->timezone);
        $this->assertIsArray($config->recurringTasks);
        $this->assertEmpty($config->recurringTasks);
    }

    public function test_construct_with_custom_values(): void
    {
        $config = new AgentScheduleConfig(
            enabled: true,
            timezone: 'America/New_York',
            recurringTasks: [
                [
                    'name' => 'Test Task',
                    'time' => '09:00',
                    'message' => 'Test message',
                ],
            ]
        );

        $this->assertTrue($config->enabled);
        $this->assertEquals('America/New_York', $config->timezone);
        $this->assertCount(1, $config->recurringTasks);
    }

    public function test_from_array_with_recurring_tasks(): void
    {
        $data = [
            'enabled' => true,
            'timezone' => 'America/Los_Angeles',
            'recurring_tasks' => [
                ['name' => 'Task 1', 'time' => '08:00', 'message' => 'Morning'],
            ],
        ];

        $config = AgentScheduleConfig::fromArray($data);

        $this->assertTrue($config->enabled);
        $this->assertEquals('America/Los_Angeles', $config->timezone);
        $this->assertCount(1, $config->recurringTasks);
    }

    public function test_from_array_with_camelcase_recurring_tasks(): void
    {
        $data = [
            'enabled' => true,
            'timezone' => 'UTC',
            'recurringTasks' => [  // camelCase instead of snake_case
                ['name' => 'Task 1', 'time' => '10:00', 'message' => 'Test'],
            ],
        ];

        $config = AgentScheduleConfig::fromArray($data);

        $this->assertCount(1, $config->recurringTasks);
    }

    public function test_from_array_with_defaults(): void
    {
        $config = AgentScheduleConfig::fromArray([]);

        $this->assertFalse($config->enabled);
        $this->assertEquals('UTC', $config->timezone);
        $this->assertEmpty($config->recurringTasks);
    }

    // ========================================
    // Enable/Disable
    // ========================================

    public function test_enable(): void
    {
        $config = new AgentScheduleConfig();
        $this->assertFalse($config->enabled);

        $result = $config->enable();

        $this->assertSame($config, $result); // Fluent interface
        $this->assertTrue($config->enabled);
    }

    public function test_disable(): void
    {
        $config = new AgentScheduleConfig(enabled: true);
        $this->assertTrue($config->enabled);

        $result = $config->disable();

        $this->assertSame($config, $result); // Fluent interface
        $this->assertFalse($config->enabled);
    }

    // ========================================
    // Timezone
    // ========================================

    public function test_with_timezone(): void
    {
        $config = new AgentScheduleConfig();
        $result = $config->withTimezone('America/Chicago');

        $this->assertSame($config, $result); // Fluent interface
        $this->assertEquals('America/Chicago', $config->timezone);
    }

    // ========================================
    // Task Management
    // ========================================

    public function test_add_task(): void
    {
        $config = new AgentScheduleConfig();
        
        $result = $config->addTask([
            'name' => 'Morning Reminder',
            'time' => '09:00',
            'message' => 'Good morning!',
        ]);

        $this->assertSame($config, $result); // Fluent interface
        $this->assertCount(1, $config->recurringTasks);
        
        $task = $config->recurringTasks[0];
        $this->assertEquals('Morning Reminder', $task['name']);
        $this->assertEquals('09:00', $task['time']);
        $this->assertEquals('Good morning!', $task['message']);
        $this->assertEquals('daily', $task['frequency']); // Default
        $this->assertEquals(['sms', 'email'], $task['channels']); // Default
    }

    public function test_add_task_with_custom_frequency_and_channels(): void
    {
        $config = new AgentScheduleConfig();
        
        $config->addTask([
            'name' => 'Weekly Report',
            'time' => '14:00',
            'message' => 'Weekly status',
            'frequency' => 'weekly',
            'channels' => ['email'],
        ]);

        $task = $config->recurringTasks[0];
        $this->assertEquals('weekly', $task['frequency']);
        $this->assertEquals(['email'], $task['channels']);
    }

    public function test_add_task_missing_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Task must have name, time, and message');

        $config = new AgentScheduleConfig();
        $config->addTask([
            'time' => '09:00',
            'message' => 'Test',
        ]);
    }

    public function test_add_task_missing_time_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Task must have name, time, and message');

        $config = new AgentScheduleConfig();
        $config->addTask([
            'name' => 'Test',
            'message' => 'Test',
        ]);
    }

    public function test_add_task_missing_message_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Task must have name, time, and message');

        $config = new AgentScheduleConfig();
        $config->addTask([
            'name' => 'Test',
            'time' => '09:00',
        ]);
    }

    public function test_add_task_invalid_time_format_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Time must be in HH:MM format');

        $config = new AgentScheduleConfig();
        $config->addTask([
            'name' => 'Test',
            'time' => '9:00', // Invalid: should be 09:00
            'message' => 'Test',
        ]);
    }

    public function test_add_task_invalid_time_format_with_seconds_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Time must be in HH:MM format');

        $config = new AgentScheduleConfig();
        $config->addTask([
            'name' => 'Test',
            'time' => '09:00:00', // Invalid: includes seconds
            'message' => 'Test',
        ]);
    }

    public function test_add_multiple_tasks(): void
    {
        $config = new AgentScheduleConfig();
        
        $config->addTask([
            'name' => 'Task 1',
            'time' => '08:00',
            'message' => 'Morning',
        ])->addTask([
            'name' => 'Task 2',
            'time' => '12:00',
            'message' => 'Noon',
        ])->addTask([
            'name' => 'Task 3',
            'time' => '18:00',
            'message' => 'Evening',
        ]);

        $this->assertCount(3, $config->recurringTasks);
    }

    public function test_add_tasks_array(): void
    {
        $config = new AgentScheduleConfig();
        
        $tasks = [
            ['name' => 'Task 1', 'time' => '08:00', 'message' => 'Morning'],
            ['name' => 'Task 2', 'time' => '20:00', 'message' => 'Evening'],
        ];

        $result = $config->addTasks($tasks);

        $this->assertSame($config, $result); // Fluent interface
        $this->assertCount(2, $config->recurringTasks);
    }

    public function test_clear_tasks(): void
    {
        $config = new AgentScheduleConfig();
        $config->addTask([
            'name' => 'Task 1',
            'time' => '09:00',
            'message' => 'Test',
        ]);

        $this->assertCount(1, $config->recurringTasks);

        $result = $config->clearTasks();

        $this->assertSame($config, $result); // Fluent interface
        $this->assertEmpty($config->recurringTasks);
    }

    // ========================================
    // Helper Methods
    // ========================================

    public function test_medication_reminders(): void
    {
        $times = ['08:00', '12:00', '18:00', '22:00'];
        
        $config = AgentScheduleConfig::medicationReminders($times);

        $this->assertTrue($config->enabled);
        $this->assertCount(4, $config->recurringTasks);

        foreach ($config->recurringTasks as $i => $task) {
            $this->assertEquals($times[$i], $task['time']);
            $this->assertEquals('Time to take your medication', $task['message']);
            $this->assertEquals(['voice', 'sms'], $task['channels']);
            $this->assertEquals('daily', $task['frequency']);
            $this->assertStringContainsString('Medication Reminder', $task['name']);
        }
    }

    public function test_medication_reminders_with_custom_message(): void
    {
        $config = AgentScheduleConfig::medicationReminders(
            ['09:00', '21:00'],
            'Take your pills now',
            ['sms']
        );

        $this->assertCount(2, $config->recurringTasks);
        $this->assertEquals('Take your pills now', $config->recurringTasks[0]['message']);
        $this->assertEquals(['sms'], $config->recurringTasks[0]['channels']);
    }

    public function test_daily_check_in(): void
    {
        $config = AgentScheduleConfig::dailyCheckIn();

        $this->assertTrue($config->enabled);
        $this->assertCount(1, $config->recurringTasks);

        $task = $config->recurringTasks[0];
        $this->assertEquals('Daily Check-in', $task['name']);
        $this->assertEquals('20:00', $task['time']);
        $this->assertEquals('How was your day? Need anything?', $task['message']);
        $this->assertEquals(['voice', 'sms'], $task['channels']);
        $this->assertEquals('daily', $task['frequency']);
    }

    public function test_daily_check_in_with_custom_parameters(): void
    {
        $config = AgentScheduleConfig::dailyCheckIn(
            '18:00',
            'Evening check-in call',
            ['voice']
        );

        $task = $config->recurringTasks[0];
        $this->assertEquals('18:00', $task['time']);
        $this->assertEquals('Evening check-in call', $task['message']);
        $this->assertEquals(['voice'], $task['channels']);
    }

    // ========================================
    // Array Conversion
    // ========================================

    public function test_to_array(): void
    {
        $config = new AgentScheduleConfig(
            enabled: true,
            timezone: 'America/New_York',
        );

        $config->addTask([
            'name' => 'Test Task',
            'time' => '09:00',
            'message' => 'Test message',
        ]);

        $array = $config->toArray();

        $this->assertArrayHasKey('enabled', $array);
        $this->assertArrayHasKey('timezone', $array);
        $this->assertArrayHasKey('recurring_tasks', $array);
        
        $this->assertTrue($array['enabled']);
        $this->assertEquals('America/New_York', $array['timezone']);
        $this->assertCount(1, $array['recurring_tasks']);
    }

    public function test_to_array_uses_snake_case(): void
    {
        $config = new AgentScheduleConfig();
        $array = $config->toArray();

        // Should be snake_case, not camelCase
        $this->assertArrayHasKey('recurring_tasks', $array);
        $this->assertArrayNotHasKey('recurringTasks', $array);
    }

    // ========================================
    // Complex Scenarios
    // ========================================

    public function test_fluent_interface_chain(): void
    {
        $config = new AgentScheduleConfig();
        
        $result = $config
            ->enable()
            ->withTimezone('America/Los_Angeles')
            ->addTask([
                'name' => 'Morning',
                'time' => '08:00',
                'message' => 'Good morning',
            ])
            ->addTask([
                'name' => 'Evening',
                'time' => '20:00',
                'message' => 'Good evening',
            ]);

        $this->assertSame($config, $result);
        $this->assertTrue($config->enabled);
        $this->assertEquals('America/Los_Angeles', $config->timezone);
        $this->assertCount(2, $config->recurringTasks);
    }

    public function test_build_elderly_care_schedule(): void
    {
        $config = new AgentScheduleConfig();
        $config->enable()
               ->withTimezone('America/New_York');

        // Add medication reminders
        $medTimes = ['08:00', '12:00', '18:00', '22:00'];
        foreach ($medTimes as $time) {
            $config->addTask([
                'name' => "Medication - {$time}",
                'time' => $time,
                'message' => 'Time for your medication',
                'channels' => ['voice', 'sms'],
            ]);
        }

        // Add daily check-in
        $config->addTask([
            'name' => 'Evening Check-in',
            'time' => '20:00',
            'message' => 'How was your day?',
            'channels' => ['voice'],
        ]);

        $this->assertCount(5, $config->recurringTasks);
        $this->assertTrue($config->enabled);

        $array = $config->toArray();
        $this->assertEquals(5, count($array['recurring_tasks']));
    }

    public function test_roundtrip_conversion(): void
    {
        // Create config
        $original = new AgentScheduleConfig(
            enabled: true,
            timezone: 'America/Chicago',
        );
        $original->addTask([
            'name' => 'Test',
            'time' => '10:00',
            'message' => 'Hello',
        ]);

        // Convert to array
        $array = $original->toArray();

        // Create from array
        $restored = AgentScheduleConfig::fromArray($array);

        // Verify equality
        $this->assertEquals($original->enabled, $restored->enabled);
        $this->assertEquals($original->timezone, $restored->timezone);
        $this->assertCount(1, $restored->recurringTasks);
        $this->assertEquals('Test', $restored->recurringTasks[0]['name']);
    }
}

<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents\Templates;

use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\AgentSettings;
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;

/**
 * Elderly Care Assistant Template
 *
 * Pre-configured template for elderly care and medication management.
 */
class ElderlyCareTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'elderly-care';
    }

    public function getDescription(): string
    {
        return 'Elderly care assistant with medication reminders, health monitoring, and family contact management';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Care Assistant',
            'type' => 'content',
            'initial_prompt' => <<<'PROMPT'
You are a caring and patient assistant designed to help elderly individuals with their daily routines, 
medication schedules, and general well-being. 

Your responsibilities include:
- Reminding about medication times in a warm, encouraging manner
- Answering questions about daily schedules and appointments
- Providing companionship and emotional support
- Helping with calendar management and reminders
- Connecting with family members when needed
- Monitoring general well-being through regular check-ins

Communication style:
- Speak clearly and at an appropriate pace
- Use simple, straightforward language
- Be patient and understanding
- Show genuine care and warmth
- Repeat information if needed
- Confirm understanding frequently

Always prioritize safety and encourage contacting family members or healthcare providers for medical concerns.
PROMPT
        ];
    }

    public function getRequiredIntegrations(): array
    {
        return ['google-calendar', 'gmail'];
    }

    public function getDefaultSettings(): AgentSettings
    {
        $settings = new AgentSettings(
            communicationStyle: 'warm',
            responseMode: 'conversational',
            memoryPersistence: true,
        );

        // Enable calendar and email integrations
        $settings->enableIntegrations(['google-calendar', 'gmail']);

        return $settings;
    }

    public function getDefaultSchedule(): ?AgentScheduleConfig
    {
        // Default medication reminder times
        return AgentScheduleConfig::medicationReminders(
            times: ['08:00', '12:00', '18:00', '21:00'],
            message: 'Time for your medication. Have you taken it yet?',
            channels: ['voice', 'sms']
        );
    }

    /**
     * Build elderly care agent with customizations.
     *
     * @param array{
     *     name?: string,
     *     medication_times?: array,
     *     family_contacts?: array,
     *     timezone?: string,
     *     voice_settings?: array,
     *     additional_tasks?: array
     * } $customizations
     */
    public function build(array $customizations = []): array
    {
        // Handle medication times customization
        if (isset($customizations['medication_times'])) {
            $schedule = AgentScheduleConfig::medicationReminders(
                times: $customizations['medication_times'],
                channels: $customizations['channels'] ?? ['voice', 'sms']
            );

            if (isset($customizations['timezone'])) {
                $schedule->withTimezone($customizations['timezone']);
            }

            // Add daily check-in
            $schedule->addTask([
                'name' => 'Evening Check-in',
                'time' => '20:00',
                'message' => 'How was your day? Is there anything you need?',
                'channels' => ['voice'],
                'frequency' => 'daily',
            ]);

            // Add any additional tasks
            if (isset($customizations['additional_tasks'])) {
                $schedule->addTasks($customizations['additional_tasks']);
            }

            $customizations['settings']['schedule'] = $schedule->toArray();
            unset($customizations['medication_times'], $customizations['additional_tasks']);
        }

        // Handle voice settings
        if (isset($customizations['voice_settings'])) {
            if (!isset($customizations['settings'])) {
                $customizations['settings'] = [];
            }
            $customizations['settings']['voiceSettings'] = array_merge([
                'language' => 'en-US',
                'speaking_rate' => 0.9, // Slower for clarity
                'pitch' => 0,
                'volume' => 1.0,
            ], $customizations['voice_settings']);
            unset($customizations['voice_settings']);
        }

        // Build with parent logic
        return parent::build($customizations);
    }

    public function validate(array $customizations): void
    {
        // Validate medication times format
        if (isset($customizations['medication_times'])) {
            if (!is_array($customizations['medication_times'])) {
                throw new \InvalidArgumentException('medication_times must be an array');
            }

            foreach ($customizations['medication_times'] as $time) {
                if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
                    throw new \InvalidArgumentException("Invalid time format: {$time}. Must be HH:MM");
                }
            }
        }

        // Validate timezone
        if (isset($customizations['timezone'])) {
            $validTimezones = \DateTimeZone::listIdentifiers();
            if (!in_array($customizations['timezone'], $validTimezones, true)) {
                throw new \InvalidArgumentException("Invalid timezone: {$customizations['timezone']}");
            }
        }
    }
}

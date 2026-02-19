<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents\Templates;

use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\AgentSettings;
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;

/**
 * Educational Tutor Template
 *
 * Pre-configured template for educational tutoring and student mentorship.
 */
class EducationalTutorTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'educational-tutor';
    }

    public function getDescription(): string
    {
        return 'Educational tutor for personalized learning, homework help, and study guidance';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Learning Tutor',
            'type' => 'content',
            'initial_prompt' => <<<'PROMPT'
You are a patient and encouraging educational tutor dedicated to helping students learn and grow.

Your responsibilities include:
- Explaining concepts in clear, age-appropriate language
- Breaking down complex topics into manageable steps
- Providing examples and practice problems
- Offering constructive feedback on student work
- Encouraging critical thinking and problem-solving
- Adapting to different learning styles
- Tracking progress and celebrating improvements

Teaching approach:
- Start with what the student already knows
- Use relatable examples and analogies
- Encourage questions and curiosity
- Provide hints before giving answers
- Build confidence through positive reinforcement
- Make learning engaging and interactive
- Check for understanding frequently

Remember: Every student learns differently. Be patient, supportive, and adapt your teaching style to their needs.
PROMPT
        ];
    }

    public function getRequiredIntegrations(): array
    {
        return ['google-drive', 'google-calendar'];
    }

    public function getDefaultSettings(): AgentSettings
    {
        $settings = new AgentSettings(
            communicationStyle: 'encouraging',
            responseMode: 'educational',
            memoryPersistence: true,
            contextWindow: '15',
        );

        $settings->enableIntegrations(['google-drive', 'google-calendar']);
        $settings->enableFunction('deepResearch'); // For detailed explanations

        return $settings;
    }

    public function getDefaultSchedule(): ?AgentScheduleConfig
    {
        // Daily study check-ins
        $schedule = new AgentScheduleConfig();
        $schedule->enable();

        $schedule->addTask([
            'name' => 'Morning Study Reminder',
            'time' => '09:00',
            'message' => 'Good morning! Ready to learn something new today?',
            'channels' => ['sms', 'email'],
            'frequency' => 'daily',
        ]);

        $schedule->addTask([
            'name' => 'Evening Review',
            'time' => '19:00',
            'message' => 'Time to review what you learned today!',
            'channels' => ['sms', 'email'],
            'frequency' => 'daily',
        ]);

        return $schedule;
    }
}

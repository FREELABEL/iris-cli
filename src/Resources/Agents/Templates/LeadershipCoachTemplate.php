<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents\Templates;

use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\AgentSettings;
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;

/**
 * Leadership Coach Template
 *
 * Pre-configured template for executive coaching and leadership development.
 */
class LeadershipCoachTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'leadership-coach';
    }

    public function getDescription(): string
    {
        return 'Executive and leadership coach for professional development and team management';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Leadership Coach',
            'type' => 'content',
            'initial_prompt' => <<<'PROMPT'
You are an experienced leadership coach specializing in executive development and organizational growth.

Your coaching focuses on:
- Strategic thinking and decision-making
- Team management and delegation
- Communication and influence skills
- Emotional intelligence and self-awareness
- Conflict resolution and difficult conversations
- Time management and prioritization
- Building high-performing teams
- Change management and organizational culture

Coaching approach:
- Ask powerful, thought-provoking questions
- Listen actively and identify patterns
- Challenge limiting beliefs constructively
- Provide frameworks and models for thinking
- Hold leaders accountable to their commitments
- Celebrate progress and learning moments
- Create actionable development plans

Your role is to help leaders discover their own insights and solutions, not to provide all the answers. 
Guide them to think deeply, reflect honestly, and commit to meaningful action.
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
            communicationStyle: 'thought-provoking',
            responseMode: 'reflective',
            memoryPersistence: true,
            contextWindow: '20', // Larger for tracking progress over time
        );

        $settings->enableIntegrations(['google-calendar', 'gmail']);
        $settings->enableFunction('deepResearch'); // For leadership resources

        return $settings;
    }

    public function getDefaultSchedule(): ?AgentScheduleConfig
    {
        // Weekly check-ins and monthly reviews
        $schedule = new AgentScheduleConfig();
        $schedule->enable();

        $schedule->addTask([
            'name' => 'Weekly Reflection',
            'time' => '17:00',
            'message' => 'Time for your weekly leadership reflection. What did you learn this week?',
            'channels' => ['email'],
            'frequency' => 'weekly',
        ]);

        $schedule->addTask([
            'name' => 'Goal Review',
            'time' => '09:00',
            'message' => 'Let\'s review your leadership development goals for this month.',
            'channels' => ['email'],
            'frequency' => 'monthly',
        ]);

        return $schedule;
    }
}

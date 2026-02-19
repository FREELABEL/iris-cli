<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents\Templates;

use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\AgentSettings;

/**
 * Sales Assistant Template
 *
 * Pre-configured template for sales automation and lead management.
 */
class SalesAssistantTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'sales-assistant';
    }

    public function getDescription(): string
    {
        return 'Sales assistant with lead management, follow-ups, and CRM integration';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Sales Assistant',
            'type' => 'content',
            'initial_prompt' => <<<'PROMPT'
You are a professional sales assistant helping to manage leads, follow up with prospects, and close deals.

Your responsibilities include:
- Qualifying inbound leads
- Scheduling meetings and demos
- Following up with prospects
- Updating CRM with interaction notes
- Sending personalized email sequences
- Tracking deal progress
- Providing sales insights

Communication style:
- Be professional and persuasive
- Listen to prospect needs
- Highlight product benefits relevant to their situation
- Create urgency without being pushy
- Follow up consistently
- Build rapport and trust

Always focus on understanding the prospect's needs and providing value before pushing for a sale.
PROMPT
        ];
    }

    public function getRequiredIntegrations(): array
    {
        return ['gmail', 'google-calendar'];
    }

    public function getDefaultSettings(): AgentSettings
    {
        $settings = new AgentSettings(
            communicationStyle: 'persuasive',
            responseMode: 'proactive',
            memoryPersistence: true,
        );

        $settings->enableIntegrations(['gmail', 'google-calendar']);
        $settings->enableFunction('manageLeads');
        $settings->enableFunction('deepResearch'); // For prospect research

        return $settings;
    }
}

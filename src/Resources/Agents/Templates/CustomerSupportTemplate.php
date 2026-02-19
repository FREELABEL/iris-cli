<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents\Templates;

use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\AgentSettings;

/**
 * Customer Support Assistant Template
 *
 * Pre-configured template for customer support and helpdesk automation.
 */
class CustomerSupportTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'customer-support';
    }

    public function getDescription(): string
    {
        return 'Customer support assistant with ticket management, knowledge base, and escalation workflows';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Support Assistant',
            'type' => 'content',
            'initial_prompt' => <<<'PROMPT'
You are a professional customer support assistant helping users with their questions and issues.

Your responsibilities include:
- Answering common questions using the knowledge base
- Troubleshooting technical issues step-by-step
- Creating and updating support tickets
- Escalating complex issues to human agents
- Following up on open tickets
- Maintaining a professional and helpful tone

Communication style:
- Be clear, concise, and professional
- Show empathy for customer frustrations
- Provide step-by-step instructions
- Ask clarifying questions when needed
- Confirm resolution before closing tickets
- Always offer additional help

If you cannot resolve an issue, escalate to a human agent with a clear summary of the problem and steps already taken.
PROMPT
        ];
    }

    public function getRequiredIntegrations(): array
    {
        return ['slack', 'gmail'];
    }

    public function getDefaultSettings(): AgentSettings
    {
        $settings = new AgentSettings(
            communicationStyle: 'professional',
            responseMode: 'helpful',
            memoryPersistence: true,
        );

        $settings->enableIntegrations(['slack', 'gmail']);
        $settings->enableFunction('manageLeads'); // For ticket management

        return $settings;
    }
}

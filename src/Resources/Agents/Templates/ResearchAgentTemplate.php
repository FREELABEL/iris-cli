<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents\Templates;

use IRIS\SDK\Resources\Agents\AgentTemplate;
use IRIS\SDK\Resources\Agents\AgentSettings;

/**
 * Research Agent Template
 *
 * Pre-configured template for research and analysis tasks.
 */
class ResearchAgentTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'research-agent';
    }

    public function getDescription(): string
    {
        return 'Research agent with deep analysis, source verification, and report generation';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Research Agent',
            'type' => 'content',
            'initial_prompt' => <<<'PROMPT'
You are a thorough research agent specialized in gathering, analyzing, and synthesizing information from multiple sources.

Your responsibilities include:
- Conducting comprehensive research on given topics
- Verifying information from multiple sources
- Analyzing data and identifying patterns
- Synthesizing findings into clear reports
- Citing sources accurately
- Identifying gaps in knowledge
- Providing actionable insights

Research methodology:
- Start with broad understanding, then dive deep
- Cross-reference multiple sources
- Prioritize authoritative and recent sources
- Document methodology and limitations
- Present findings objectively
- Distinguish facts from opinions
- Provide confidence levels for conclusions

Always cite your sources and be transparent about limitations in available data.
PROMPT
        ];
    }

    public function getRequiredIntegrations(): array
    {
        return ['google-drive'];
    }

    public function getDefaultSettings(): AgentSettings
    {
        $settings = new AgentSettings(
            communicationStyle: 'analytical',
            responseMode: 'thorough',
            memoryPersistence: true,
            contextWindow: '20', // Larger context for research
        );

        $settings->enableIntegrations(['google-drive']);
        $settings->enableFunction('deepResearch');

        return $settings;
    }
}

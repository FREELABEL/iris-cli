<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Agent Settings Configuration
 *
 * Comprehensive settings structure matching the REST API capabilities.
 * Used to configure agent behavior, integrations, schedules, and more.
 */
class AgentSettings
{
    /**
     * Create a new agent settings configuration.
     *
     * @param array $agentIntegrations Map of integration names to enabled status
     * @param array $enabledFunctions Map of function names to enabled status
     * @param array $schedule Schedule configuration
     * @param string $responseMode Response generation mode
     * @param string $contextWindow Context window size
     * @param bool $memoryPersistence Whether to persist memory across sessions
     * @param string $communicationStyle Communication style preference
     * @param array $voiceSettings Voice AI configuration
     * @param array $customSettings Additional custom settings
     */
    public function __construct(
        public array $agentIntegrations = [],
        public array $enabledFunctions = [],
        public array $schedule = [],
        public string $responseMode = 'balanced',
        public string $contextWindow = '10',
        public bool $memoryPersistence = true,
        public string $communicationStyle = 'professional',
        public array $voiceSettings = [],
        public array $customSettings = [],
    ) {}

    /**
     * Create from existing settings array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            agentIntegrations: $data['agentIntegrations'] ?? [],
            enabledFunctions: $data['enabledFunctions'] ?? [],
            schedule: $data['schedule'] ?? [],
            responseMode: $data['responseMode'] ?? 'balanced',
            contextWindow: $data['contextWindow'] ?? '10',
            memoryPersistence: $data['memoryPersistence'] ?? true,
            communicationStyle: $data['communicationStyle'] ?? 'professional',
            voiceSettings: $data['voiceSettings'] ?? [],
            customSettings: array_diff_key($data, array_flip([
                'agentIntegrations',
                'enabledFunctions',
                'schedule',
                'responseMode',
                'contextWindow',
                'memoryPersistence',
                'communicationStyle',
                'voiceSettings',
            ])),
        );
    }

    /**
     * Convert to array for API submission.
     */
    public function toArray(): array
    {
        $base = [
            'agentIntegrations' => $this->agentIntegrations,
            'enabledFunctions' => $this->enabledFunctions,
            'schedule' => $this->schedule,
            'responseMode' => $this->responseMode,
            'contextWindow' => $this->contextWindow,
            'memoryPersistence' => $this->memoryPersistence,
            'communicationStyle' => $this->communicationStyle,
            'voiceSettings' => $this->voiceSettings,
        ];

        // Merge custom settings
        return array_merge($base, $this->customSettings);
    }

    /**
     * Enable an integration.
     */
    public function enableIntegration(string $integration): self
    {
        $this->agentIntegrations[$integration] = true;
        return $this;
    }

    /**
     * Disable an integration.
     */
    public function disableIntegration(string $integration): self
    {
        $this->agentIntegrations[$integration] = false;
        return $this;
    }

    /**
     * Enable multiple integrations.
     */
    public function enableIntegrations(array $integrations): self
    {
        foreach ($integrations as $integration) {
            $this->agentIntegrations[$integration] = true;
        }
        return $this;
    }

    /**
     * Enable a function.
     */
    public function enableFunction(string $function): self
    {
        $this->enabledFunctions[$function] = true;
        return $this;
    }

    /**
     * Disable a function.
     */
    public function disableFunction(string $function): self
    {
        $this->enabledFunctions[$function] = false;
        return $this;
    }

    /**
     * Configure schedule settings.
     */
    public function withSchedule(array $schedule): self
    {
        $this->schedule = array_merge($this->schedule, $schedule);
        return $this;
    }

    /**
     * Configure voice settings.
     */
    public function withVoiceSettings(array $voiceSettings): self
    {
        $this->voiceSettings = array_merge($this->voiceSettings, $voiceSettings);
        return $this;
    }

    /**
     * Set response mode.
     */
    public function withResponseMode(string $mode): self
    {
        $this->responseMode = $mode;
        return $this;
    }

    /**
     * Set communication style.
     */
    public function withCommunicationStyle(string $style): self
    {
        $this->communicationStyle = $style;
        return $this;
    }

    /**
     * Get list of enabled integrations.
     */
    public function getEnabledIntegrations(): array
    {
        return array_keys(array_filter($this->agentIntegrations, fn($v) => $v === true));
    }

    /**
     * Get list of enabled functions.
     */
    public function getEnabledFunctions(): array
    {
        return array_keys(array_filter($this->enabledFunctions, fn($v) => $v === true));
    }

    /**
     * Check if integration is enabled.
     */
    public function hasIntegration(string $integration): bool
    {
        return ($this->agentIntegrations[$integration] ?? false) === true;
    }

    /**
     * Check if function is enabled.
     */
    public function hasFunction(string $function): bool
    {
        return ($this->enabledFunctions[$function] ?? false) === true;
    }
}

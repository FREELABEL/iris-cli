<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Agent Template Base Class
 *
 * Base class for agent templates that provide pre-configured agent setups.
 */
abstract class AgentTemplate
{
    /**
     * Get template name/identifier.
     */
    abstract public function getName(): string;

    /**
     * Get template description.
     */
    abstract public function getDescription(): string;

    /**
     * Get default agent configuration.
     */
    abstract public function getDefaultConfig(): array;

    /**
     * Get required integrations for this template.
     */
    public function getRequiredIntegrations(): array
    {
        return [];
    }

    /**
     * Get default settings for this template.
     */
    public function getDefaultSettings(): AgentSettings
    {
        return new AgentSettings();
    }

    /**
     * Get default schedule configuration.
     */
    public function getDefaultSchedule(): ?AgentScheduleConfig
    {
        return null;
    }

    /**
     * Build agent configuration with customizations.
     *
     * @param array $customizations Custom overrides
     * @return array Complete agent configuration
     */
    public function build(array $customizations = []): array
    {
        $config = $this->getDefaultConfig();
        
        // Apply customizations
        $config = array_merge($config, $customizations);

        // Ensure settings structure
        if (!isset($config['settings'])) {
            $config['settings'] = [];
        }

        // Merge default settings
        $defaultSettings = $this->getDefaultSettings()->toArray();
        $config['settings'] = array_merge($defaultSettings, $config['settings']);

        // Apply required integrations
        $requiredIntegrations = $this->getRequiredIntegrations();
        if (!empty($requiredIntegrations)) {
            if (!isset($config['settings']['agentIntegrations'])) {
                $config['settings']['agentIntegrations'] = [];
            }
            foreach ($requiredIntegrations as $integration) {
                if (!isset($config['settings']['agentIntegrations'][$integration])) {
                    $config['settings']['agentIntegrations'][$integration] = true;
                }
            }
        }

        // Apply default schedule if present
        $defaultSchedule = $this->getDefaultSchedule();
        if ($defaultSchedule !== null) {
            if (!isset($config['settings']['schedule'])) {
                $config['settings']['schedule'] = $defaultSchedule->toArray();
            }
        }

        return $config;
    }

    /**
     * Validate customizations.
     *
     * Override this to add custom validation logic.
     *
     * @param array $customizations
     * @throws \InvalidArgumentException
     */
    public function validate(array $customizations): void
    {
        // Base validation - override in subclasses
        // $customizations is intentionally unused here - subclasses implement validation
    }
}

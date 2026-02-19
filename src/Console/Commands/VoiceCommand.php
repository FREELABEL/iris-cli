<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;

/**
 * Voice Management Command
 * 
 * Provides CLI tools for managing agent voice settings across providers.
 */
class VoiceCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('voice')
            ->setDescription('Manage agent voice settings')
            ->setHelp('Configure voice settings for agents using various providers (VAPI, ElevenLabs, Azure, OpenAI, Twilio)')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: set, get, list, providers, check', 'list')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent ID')
            ->addOption('voice', null, InputOption::VALUE_REQUIRED, 'Voice ID (e.g., Lily, eleven_multilingual_v2)')
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Provider name (vapi, elevenlabs, azure, openai, twilio)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';
        
        try {
            // Initialize SDK
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }
            
            $iris = new IRIS($configOptions);
            
            // Route to appropriate handler
            switch ($action) {
                case 'set':
                    return $this->setVoice($iris, $io, $input);
                case 'get':
                    return $this->getVoice($iris, $io, $input);
                case 'list':
                    return $this->listVoices($iris, $io, $input);
                case 'providers':
                    return $this->showProviders($iris, $io);
                case 'check':
                    return $this->checkProvider($iris, $io, $input);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: set, get, list, providers, check");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
    
    private function setVoice(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getOption('agent');
        $voiceId = $input->getOption('voice');
        $provider = $input->getOption('provider');
        
        if (!$agentId) {
            $io->error('Agent ID is required');
            $io->text('Usage: <info>iris voice set --agent 335 --voice Lily --provider vapi</info>');
            return Command::FAILURE;
        }
        
        if (!$voiceId) {
            $io->error('Voice ID is required');
            $io->text('Usage: <info>iris voice set --agent 335 --voice Lily --provider vapi</info>');
            return Command::FAILURE;
        }
        
        $io->section("🎤 Setting voice for Agent #{$agentId}");
        
        $result = $iris->voice->set((int)$agentId, $voiceId, $provider);
        
        if ($result['success'] ?? false) {
            $io->success("Voice set successfully!");
            
            if (!empty($result['data']['settings'])) {
                $settings = $result['data']['settings'];
                $io->definitionList(
                    ['Voice ID' => $settings['voiceId'] ?? 'N/A'],
                    ['Provider' => $settings['voiceProvider'] ?? 'N/A']
                );
            }
            
            if (!empty($result['warnings'])) {
                $io->warning('Warnings:');
                foreach ($result['warnings'] as $warning) {
                    $io->text('  • ' . $warning);
                }
            }
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to set voice');
        return Command::FAILURE;
    }
    
    private function getVoice(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getOption('agent');
        
        if (!$agentId) {
            $io->error('Agent ID is required');
            $io->text('Usage: <info>iris voice get --agent 335</info>');
            return Command::FAILURE;
        }
        
        $io->section("🎤 Voice Configuration for Agent #{$agentId}");
        
        $result = $iris->voice->get((int)$agentId);
        
        if ($result['success'] ?? false) {
            $config = $result['data'] ?? [];
            
            if (empty($config['voiceId'])) {
                $io->warning('No voice configured for this agent');
                return Command::SUCCESS;
            }
            
            $io->definitionList(
                ['Voice ID' => $config['voiceId'] ?? 'Not set'],
                ['Provider' => $config['provider'] ?? 'Not set'],
                ['Agent ID' => $config['agentId'] ?? 'N/A']
            );
            
            if (!empty($config['settings'])) {
                $io->newLine();
                $io->text('<comment>Full Settings:</comment>');
                $io->text(json_encode($config['settings'], JSON_PRETTY_PRINT));
            }
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to get voice configuration');
        return Command::FAILURE;
    }
    
    private function listVoices(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $provider = $input->getOption('provider');
        
        if (!$provider) {
            $io->error('Provider is required');
            $io->text('Usage: <info>iris voice list --provider vapi</info>');
            $io->newLine();
            $io->text('Available providers: <info>iris voice providers</info>');
            return Command::FAILURE;
        }
        
        $io->section("🎤 Available Voices from {$provider}");
        
        $result = $iris->voice->list($provider);
        
        if ($result['success'] ?? false) {
            $voices = $result['data']['voices'] ?? [];
            
            if (empty($voices)) {
                $io->warning("No voices available from {$provider}");
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($voices as $voice) {
                if (is_array($voice)) {
                    $rows[] = [
                        $voice['id'] ?? $voice['voice_id'] ?? 'N/A',
                        $voice['name'] ?? 'N/A',
                        $voice['provider'] ?? $provider,
                        $voice['language'] ?? 'N/A',
                    ];
                } else {
                    $rows[] = [$voice, '-', $provider, '-'];
                }
            }
            
            $io->table(
                ['Voice ID', 'Name', 'Provider', 'Language'],
                $rows
            );
            
            $io->newLine();
            $io->text(sprintf('Total: <info>%d</info> voice(s)', count($voices)));
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to list voices');
        
        if (!empty($result['available'])) {
            $io->warning("Provider '{$provider}' is not connected");
            $io->text('Connect it first: <info>iris integrations connect ' . $provider . '</info>');
        }
        
        return Command::FAILURE;
    }
    
    private function showProviders(IRIS $iris, SymfonyStyle $io): int
    {
        $io->section('🎤 Available Voice Providers');
        
        $result = $iris->voice->getProviders();
        
        if ($result['success'] ?? false) {
            $providers = $result['data'] ?? [];
            
            $rows = [];
            foreach ($providers as $name => $info) {
                $supports = implode(', ', $info['supports'] ?? []);
                $rows[] = [
                    $name,
                    $info['integration_type'] ?? $name,
                    $supports,
                ];
            }
            
            $io->table(
                ['Provider', 'Integration Type', 'Supports'],
                $rows
            );
            
            $io->newLine();
            $io->text('Check provider status: <info>iris voice check --provider vapi</info>');
            $io->text('List voices: <info>iris voice list --provider vapi</info>');
            
            return Command::SUCCESS;
        }
        
        $io->error('Failed to fetch providers');
        return Command::FAILURE;
    }
    
    private function checkProvider(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $provider = $input->getOption('provider');
        
        if (!$provider) {
            $io->error('Provider is required');
            $io->text('Usage: <info>iris voice check --provider vapi</info>');
            return Command::FAILURE;
        }
        
        $io->section("🔍 Checking {$provider} Provider");
        
        $available = $iris->voice->isProviderAvailable($provider);
        
        if ($available) {
            $io->success("✓ {$provider} is connected and available");
            
            $status = $iris->voice->getProviderStatus($provider);
            if (!empty($status['integration'])) {
                $io->definitionList(
                    ['Integration ID' => $status['integration']['id'] ?? 'N/A'],
                    ['Status' => $status['integration']['status'] ?? 'N/A']
                );
            }
            
            return Command::SUCCESS;
        }
        
        $io->warning("✗ {$provider} is not connected");
        $io->text('Connect it first: <info>iris integrations connect ' . $provider . '</info>');
        
        return Command::FAILURE;
    }
}

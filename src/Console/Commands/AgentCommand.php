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
 * Agent Scope Command
 * 
 * Display comprehensive agent configuration including voice, phone, and integration settings.
 */
class AgentCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('agent:scope')
            ->setDescription('Show complete agent configuration')
            ->setHelp('Display comprehensive configuration for an agent including voice settings, phone numbers, and integrations')
            ->addArgument('agent-id', InputArgument::REQUIRED, 'Agent ID')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agentId = (int)$input->getArgument('agent-id');
        
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
            
            $io->title("🤖 Agent #{$agentId} Configuration");
            
            // Get agent details
            try {
                $agent = $iris->agents->get($agentId);
                
                $io->section('Basic Information');
                $io->definitionList(
                    ['Name' => $agent->name ?? 'N/A'],
                    ['ID' => $agent->id ?? $agentId],
                    ['Type' => $agent->type ?? 'N/A'],
                    ['Status' => $agent->status ?? 'N/A']
                );
            } catch (\Exception $e) {
                $io->warning('Could not fetch agent details: ' . $e->getMessage());
            }
            
            // Get voice configuration
            $io->section('🎤 Voice Configuration');
            try {
                $voiceResult = $iris->voice->get($agentId);
                
                if ($voiceResult['success'] ?? false) {
                    $voiceConfig = $voiceResult['data'] ?? [];
                    
                    if (!empty($voiceConfig['voiceId'])) {
                        $io->definitionList(
                            ['Voice ID' => $voiceConfig['voiceId'] ?? 'Not set'],
                            ['Provider' => $voiceConfig['provider'] ?? 'Not set']
                        );
                        
                        if (!empty($voiceConfig['settings'])) {
                            $io->text('<comment>Additional Settings:</comment>');
                            foreach ($voiceConfig['settings'] as $key => $value) {
                                if (in_array($key, ['voiceId', 'voiceProvider', 'vapiVoice', 'vapiProvider'])) {
                                    $io->text("  • {$key}: " . (is_array($value) ? json_encode($value) : $value));
                                }
                            }
                        }
                    } else {
                        $io->text('❌ No voice configured');
                        $io->text('Set voice: <info>iris voice set --agent ' . $agentId . ' --voice Lily --provider vapi</info>');
                    }
                } else {
                    $io->text('❌ Voice configuration unavailable');
                }
            } catch (\Exception $e) {
                $io->text('❌ Error: ' . $e->getMessage());
            }
            
            // Get phone configuration
            $io->section('📞 Phone Numbers');
            try {
                // Try to get agent settings which may contain phone numbers
                $agent = $iris->agents->get($agentId);
                $settings = is_object($agent->settings) ? (array)$agent->settings : ($agent->settings ?? []);
                
                if (!empty($settings['phoneNumbers']) && is_array($settings['phoneNumbers'])) {
                    $io->text(sprintf('Configured: <info>%d</info> phone number(s)', count($settings['phoneNumbers'])));
                    
                    foreach ($settings['phoneNumbers'] as $phoneId) {
                        $io->text("  • {$phoneId}");
                    }
                    
                    if (!empty($settings['phoneNumberId'])) {
                        $io->text('Primary: <info>' . $settings['phoneNumberId'] . '</info>');
                    }
                    if (!empty($settings['phoneProvider'])) {
                        $io->text('Provider: <info>' . $settings['phoneProvider'] . '</info>');
                    }
                } else {
                    $io->text('❌ No phone numbers configured');
                    $io->text('Configure phone: <info>iris phone configure --agent ' . $agentId . ' --phone PHONE_ID --provider vapi</info>');
                }
            } catch (\Exception $e) {
                $io->text('❌ Error: ' . $e->getMessage());
            }
            
            // Get integrations status
            $io->section('🔌 Provider Status');
            try {
                $providers = ['vapi', 'elevenlabs', 'twilio'];
                $rows = [];
                
                foreach ($providers as $provider) {
                    try {
                        $voiceAvailable = $iris->voice->isProviderAvailable($provider);
                        $phoneAvailable = $iris->phone->isProviderAvailable($provider);
                        
                        $voiceStatus = $voiceAvailable ? '<info>✓</info>' : '<comment>✗</comment>';
                        $phoneStatus = $phoneAvailable ? '<info>✓</info>' : '<comment>✗</comment>';
                        
                        $rows[] = [
                            $provider,
                            $voiceStatus,
                            $phoneStatus,
                        ];
                    } catch (\Exception $e) {
                        $rows[] = [
                            $provider,
                            '<comment>?</comment>',
                            '<comment>?</comment>',
                        ];
                    }
                }
                
                $io->table(
                    ['Provider', 'Voice', 'Phone'],
                    $rows
                );
                
                $io->text('Connect a provider: <info>iris integrations connect vapi</info>');
            } catch (\Exception $e) {
                $io->text('❌ Error: ' . $e->getMessage());
            }
            
            $io->newLine();
            $io->success('Agent scope displayed successfully');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}
